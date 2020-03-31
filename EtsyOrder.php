<?php
class EtsyOrder extends OrderCommon
{
    public static $collection_name='orders';
    public static $default_query_params=array(
        'market'=>'etsy',
    );

    public static function getOne($key){
        return self::find_one(['$or'=>[
            ['order_id'=>$key]
        ]]);
    }

    public function __construct($data=null, $in_db=FALSE)
    {
        parent::__construct($data, $in_db);
    }

    /**
     * @override
     */
    public function customer_name()
    {
        return $this->get('etsy.Buyer.login_name');
    }

    /**
     * @override
     */
    public function buyer_email()
    {
        return $this->get('etsy.Receipt.buyer_email');
    }

    /**
     * @override
     */
    public function order_commission()
    {
        return $this->order_subtotal()*0.035;
    }

    /**
     * @override
     */
    public function order_tax()
    {
        return floatval($this->get('etsy.Receipt.total_tax_cost', 0));
    }

    /**
     * @override
     */
    public function order_shipping()
    {
        return floatval($this->get('etsy.Receipt.total_shipping_cost', 0));
    }

    /**
     * @override
     */
    public function order_total()
    {
        return floatval($this->get('etsy.Receipt.grandtotal'));
    }

    /**
     * @override
     */
    public function order_subtotal()
    {
        return floatval($this->get('etsy.Receipt.subtotal'));
    }

    public function getRawOrderTime()
    {
        return $this->get('etsy.creation_tsz');
    }

    /**
     * @override
     */
    public function convertOrderTimeToTimestamp()
    {
        $timestamp=$this->getRawOrderTime();
        return $timestamp;
    }

    public function getOrderShippingMethodName()
    {
        return 'UPS/GND';
    }

    public static function addOrUpdateFromRaw($seller, $orders)
    {
        foreach($orders as $order)
        {
            if(VERBOSE) error_log(sprintf(
                "%s  %s  %s", $order['receipt_id'],
                date('c', $order['creation_tsz']), $order['title']
            ));
            $order_instance=OrderCommon::getOrder(Agent\Tools::order_guid('etsy', $seller->id(), (string)$order['receipt_id'])) ?: new EtsyOrder();
            $order_instance->saveOrderInfo($seller, $order);
        }
    }

    public function saveOrderInfo($seller, $order_info)
    {
        $is_new=!(bool)$this->order_id;
        if(!$this->order_id)
        {
            //not paid, do not save
            if(!$order_info['paid_tsz'])
                return true;

            if($order_info['paid_tsz'])
            {
                $this->updateStatus('Fetched');
            }

            if($order_info['shipped_tsz'])
            {
                $this->updateStatus('Shipped');
            }
        }
        else
        {
            if($order_info['shipped_tsz'])
            {
                $this->updateStatus('Shipped');
            }
        }

        $this->set('guid', Agent\Tools::order_guid('etsy', $seller->id(), (string)$order_info['receipt_id']));
        $this->seller=$seller->id();
        $this->market='etsy';
        $this->order_id=(string)$order_info['receipt_id'];
        $this->customer=(string)$order_info['Buyer']['login_name'];
        $this->buyer_email=(string)$order_info['Receipt']['buyer_email'];
        $this->etsy=Agent\Tools::use_string_for_id_fields((array)$order_info);

        //save order_status, it inferred from some other status
        if($this->get('etsy.Receipt.shipments.0.current_step')=='delivered')
            $this->order_status='Delivered';
        else if($this->get('etsy.shipped_tsz'))
            $this->order_status='Shipped';
        else if($this->get('etsy.paid_tsz'))
            $this->order_status='Unshipped';
        else
            $this->order_status='Pending';

        $this->save();

        if(!$this->shipping_address)
        {
            $this->set('shipping_address', array(
                "reference"=>'',
                "name"=>$this->get('etsy.Receipt.name'),
                "phone"=>'',
                "zip"=>$this->get('etsy.Receipt.zip'),
                "country"=>OrderAddress::etsyCountryIdToCountryCode($this->get('etsy.Receipt.country_id')),
                "state"=>$this->get('etsy.Receipt.state'),
                "street1"=>$this->get('etsy.Receipt.first_line'),
                "street2"=>$this->get('etsy.Receipt.second_line'),
                "city"=>$this->get('etsy.Receipt.city'),
            ));

            $this->save();
        }

        EtsyOrderItem::addOrUpdateFromRaw($seller, $this, $order_info);

        //do something for new orders
        if($is_new || !$this->order_timestamp)
            $this->processNewOrder();
    }

    public static function build_query_array()
    {
        $sellers=EtsyAccount::getNameIdMapping();
        $status=EtsyAccount::distinct('status');
        $status=array_combine($status, $status);
        $etsy_status=EtsyOrder::distinct('etsy.OrderStatus');
        $etsy_status=array_combine($etsy_status, $etsy_status);
        return array(
            'seller'=>array('type'=>'select', 'values'=>$sellers, 'title'=>'Seller'),
            'status'=>array('type'=>'select', 'values'=>$status, 'title'=>'Status'),
            'order_status'=>array('type'=>'select', 'values'=>$etsy_status, 'title'=>'Etsy Order Status'),
            'date_start'=>array('type'=>'date', 'title'=>'Date Start'),
            'date_end'=>array('type'=>'date', 'title'=>'Date End'),
            'q'=>array('type'=>'text', 'title'=>'Search'),
        );
    }

    public static function translate_query_array($input_array)
    {
        //date
        if(isset($input_array['date_start']) || isset($input_array['date_end']))
        {
            $date_start=@$input_array['date_start'];
            $date_end=@$input_array['date_end'];
            unset($input_array['date_start']);
            unset($input_array['date_end']);
            $start=strtotime($date_start);
            $end=strtotime($date_end);
            if($start<$end || !$end)
            {
                if($start && !$end)
                    $input_array['order_timestamp']=array('$gte'=>$start);
                else if($end && !$start)
                    $input_array['order_timestamp']=array('$lt'=>$end);
                else
                    $input_array['$and']=array(
                        array('order_timestamp'=>array('$gte'=>$start)),
                        array('order_timestamp'=>array('$lt'=>$end)),
                    );
            }
        }
        //keyword
        if(isset($input_array['q']))
        {
            $q=$input_array['q'];
            unset($input_array['q']);

            $query=SearchHelper::extract_search_modifier($q, array(
                'name'=>'etsy.Buyer.login_name',
                'email'=>'etsy.Buyer.buyer_email',
                'id'=>'order_id',
            ));

            //fall back to fuzzy search
            if(!$query)
            {
                $search_array[]=array('order_id'=>$q);
                $search_array[]=array('etsy.Buyer.login_name'=>$q);
                $search_array[]=array('etsy.Receipt.buyer_email'=>$q);
                $search_array[]=array('sku'=>$q);
                $search_array[]=array('shipping_address.name'=>$q);
                $input_array['$or']=$search_array;
            }
            else
            {
                foreach($query as $k=>$v)
                    $input_array[$k]=$v;
            }
        }

        return $input_array;
    }

    public static function get_sort_keys()
    {
        return array('order_timestamp'=>-1);
    }

    public function updateReceipt(){

        $seller=$this->getSeller();
        $apiresult=EtsyFeatures::updateReceipt($seller, array(
            'receipt_id'=>$this->get('etsy.receipt_id'),
            'was_paid'=>"true",
            'was_shipped'=>"true" // 被调用的时候状态必为Shipped
        ));
        if($apiresult && !isset($apiresult['code']))
        {
            $this->set('etsy.status_update_time', time());
            $this->save();
            //重新获取最新订单数据
            $orderItem = EtsyOrderItem::find_one(["order_id"=> $this->order_id]);
            $order=EtsyFeatures::getTransaction($seller, ["transaction_id"=>(int)$orderItem->get('order_item_id')]);
            EtsyOrder::addOrUpdateFromRaw($seller, $order['results']);
        }

        return $apiresult;
    }
}
