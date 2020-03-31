<?php
class EtsyOrderItem extends OrderItemCommon
{
    public static $collection_name='order_items';
    public static $default_query_params=array(
        'market'=>'etsy',
    );

    public function __construct($data=null, $in_db=FALSE)
    {
        parent::__construct($data, $in_db);
    }

    public function qty()
    {
        return intval($this->get('etsy.quantity'));
    }

    public function title()
    {
        //append variation name and value to tile
        $order_item_variations=$this->get('etsy.variations') ?: array();
        $variations_string_array=[];
        foreach($order_item_variations as $ov)
            $variations_string_array[]="{$ov['formatted_name']} : {$ov['formatted_value']}";
        $variations_string=implode(', ', $variations_string_array);

        return $this->get('etsy.title').' ## '.$variations_string.' ##';
    }

    /**
     * @override
     */
    public function getItemImageUrl()
    {
        return $this->get('etsy.MainImage.url_75x75');
    }

    /**
     * @override
     */
    public function item_tax()
    {
        return floatval($this->get('etsy.Receipt.total_tax_cost'));
    }

    /**
     * @override
     */
    public function item_shipping()
    {
        return $this->get('etsy.Receipt.total_shipping_cost');
    }

    /**
     * @override
     */
    public function item_commission()
    {
        return $this->item_subtotal()*0.035;
    }

    /**
     * @override
     */
    public function item_subtotal()
    {
        return $this->get('etsy.Receipt.subtotal');
    }

    /**
     * @override
     */
    public function item_total()
    {
        return $this->get('etsy.Receipt.grandtotal');
    }

    public function item_revenue()
    {
        return $this->item_total();
    }

    public function getItemListing()
    {
        return EtsyListingItem::findOne(array('listing_id'=>$this->get('etsy.listing_id')));
    }

    public function get_item_market_url(){
        return "https://www.etsy.com/listing/{$this->get('etsy.Listing.listing_id')}/";
    }


    public function syncShipping2Market()
    {
        if(!$this->get('tracking.tracking') || !$this->get('tracking.carrier'))
            return array('result'=>'error','msg'=>'Shipping Carrier or Tracking code is not valid!');

        if(strtoupper($this->get('tracking.carrier')) == "AMAZON"){
            $order = EtsyOrder::find_one(['order_id'=> $this->order_id]);
            $order->updateReceipt();
            return array('result'=>'error','msg'=>'Unable to Ship due to Fake Tracking ID');
        }
        $seller=$this->getSeller();

        $apiresult=EtsyFeatures::submitTracking($seller, array(
            'receipt_id'=>$this->get('etsy.receipt_id'),
            'carrier_name'=>$this->get('tracking.carrier'),
            'tracking_code'=>$this->get('tracking.tracking')
        ));

        if($apiresult)
        {
            $this->set('tracking.upload_time', time());
            $this->save();

            //重新获取最新订单数据
            $order=EtsyFeatures::getTransaction($seller, ['transaction_id'=>$this->order_item_id]);
            EtsyOrder::addOrUpdateFromRaw($seller, $order['results']);
        }

        return $apiresult;
    }

    public static function addOrUpdateFromRaw($seller, $order, $items_info)
    {
        $etsy=OrderItemCommon::getOrderItem(Agent\Tools::order_item_guid('etsy', $seller->id(), (string)$items_info['receipt_id'], (string)$items_info['transaction_id'])) ?: new EtsyOrderItem();
        $etsy->saveOrderItem($seller, $order, $items_info);
    }

    public function saveOrderItem($seller, $order, $item)
    {
        $this->guid=Agent\Tools::order_item_guid('etsy', $seller->id(), (string)$item['receipt_id'], (string)$item['transaction_id']);
        $this->market='etsy';
        $this->seller=$seller->id();
        $this->order_id=(string)$item['receipt_id'];
        $this->order_item_id=(string)$item['transaction_id'];//modified: order_item_id != order_id

        // Etsy订单不包含SKU信息，必须从Listing记录中寻找
        // 对于有variation的listing, 重新构造ListingId
        if(count($item['Listing']['sku']) > 1){
            $etsylisting = EtsyListingItem::find_one(['listing_id'=>$item['listing_id']."-".$item['product_data']['product_id']]);
        }else{
            $etsylisting=EtsyListingItem::findOne(['listing_id'=>['$regex'=>(string)$item['listing_id']]]);

        }

        // 尝试重新下载订单相关的Listing数据
        if(!$etsylisting){
            $r=EtsyFeatures::findAllReceiptListings($seller, ['receipt_id'=>$item['receipt_id']]);
            if($r) EtsyListingItem::addOrUpdateFromRaw($seller, $r['results']);
        }

        // 重新判断是否获取到
        if(count($item['Listing']['sku']) > 1){
            $etsylisting = EtsyListingItem::find_one(['listing_id'=>$item['listing_id']."-".$item['product_data']['product_id']]);
        }else{
            $etsylisting=EtsyListingItem::findOne(['listing_id'=>['$regex'=>(string)$item['listing_id']]]);

        }

        if(!$etsylisting){
            if($this->is_new()){
                // TODO: 更新Listing
                notify_developer(
                    "Etsy listing not found ({$item['listing_id']})",
                    "Receipt ID: {$item['receipt_id']}<br/>Transaction ID: {$item['transaction_id']}"
                );
            }
        }
        else{
            $sku=$etsylisting->get('sku', $etsylisting->sku);
            if(!$sku){
                if($this->is_new()) notify_developer(
                    "Etsy listing didn't have SKU", "Listing ID: {$this->etsy['listing_id']}, Transaction ID: {$item['transaction_id']}"
                );
            }

            $this->set('sku', $sku);
        }

        $this->set('qty', intval($item['quantity']));
        $this->set('etsy', Agent\Tools::use_string_for_id_fields($item));

        $this->save();

        if(VERBOSE) error_log("[ETSY] transaction_id={$this->order_item_id}, sku={$this->sku}, qty={$this->qty}");
    }
}
