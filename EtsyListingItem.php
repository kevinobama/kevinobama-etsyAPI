<?php
class EtsyListingItem extends ListingItemCommon
{
    public static $collection_name='etsy_listing_item';
    public static $TITLE_LENGTH_LIMIT=140;

    public static $collection_index=[
        [['seller'=>1]],
        [['status'=>1]],
        [['sku'=>1]],
        [['listing_id'=>1]],
        [['fetch_timestamp'=>1]],

        //sales
        [['sales_data.days_1'=>1]],
        [['sales_data.days_3'=>1]],
        [['sales_data.days_7'=>1]],
        [['sales_data.days_15'=>1]],
        [['sales_data.days_30'=>1]],
        [['sales_data.days_60'=>1]],
        [['sales_data.days_90'=>1]],
        [['sales_data.days_360'=>1]],
        [['sales_data.total'=>1]],
    ];

    public static function total_count()
    {
        return parent::count(array('item.state'=>'active', 'status'=>'active'));
    }

    public static function getOne($key, SalesChannel $account=null){
        return self::findOne(['$or'=>[['listing_id'=>['$regex'=>$key]], ['sku'=>$key]]]);
    }

    public function qty($sku='')
    {
        return 'na';//TODO
    }

    public function get_title(){
        return $this->get('item.title');
    }

    public function get_price(){
        return $this->get('item.price');
    }

    public function getSoldOrderItems($options=array())
    {
        $query=array(
            'seller'=>$this->seller,
            'etsy.listing_id'=>explode("-",$this->listing_id)[0],
        );
        $query=array_replace($query, $options);
        $sold_items = EtsyOrderItem::iterator($query);
//        $sold_items=new MongoModelIterator('EtsyOrderItem', $query);

        return $sold_items;
    }

    public static function build_query_array()
    {
        $sellers=EtsyAccount::getNameIdMapping();
        $sellers = Users::getSellersByUserStoresAndMarket($sellers,'etsy');
        return array(
            'seller'=>array('type'=>'select', 'values'=>$sellers, 'title'=>'Select Seller'),
            'problem'=>['type'=>'select', 'values'=>[''=>'No Problem', 'invalid_sku'=>'Invalid SKU', 'incorrect_price'=>'Incorrect Pirce'], 'title'=>'Listing Problem'],
            'q'=>array('type'=>'text', 'title'=>'Search'),
        );
    }

    public static function translate_sort_key($sort_key, $sort_value)
    {
        switch($sort_key){
            case 'sku':
                $sort_key='sku';
                break;
            case 'qty':
                $sort_key='item.quantity';
                break;
            case 'price':
                $sort_key='item.price';
                break;
            case '7d':
                $sort_key='sales_data.days_7';
                break;
            case '30d':
                $sort_key='sales_data.days_30';
                break;
            case '90d':
                $sort_key='sales_data.days_90';
                break;
            case 'sold':
                $sort_key='sales_data.total';
                break;
            case 'views':
                $sort_key="item.views";

        }
        $sort_value=($sort_value=='desc')?Agent\MongoDB\MongoModel::MONGODB_SORT_DESCENDING:Agent\MongoDB\MongoModel::MONGODB_SORT_ASCENDING;
        return array($sort_key=>$sort_value);
    }

    public static function translate_query_array($input_array)
    {
        //keyword
        if(isset($input_array['q']))
        {
            $q=$input_array['q'];
            unset($input_array['q']);

            $query=SearchHelper::extract_search_modifier($q, array(
                'sku'=>'sku',
                'sku-start-with'=>'sku',
                'sku-exact'=>'sku',
                'title'=>'item.title',
                'id'=>'listing_id',
            ));

            //fall back to fuzzy search
            if(!$query)
            {
                $regexp=array('$regex'=>"$q", '$options'=>'si');
                $search_array[]=array('listing_id'=>$regexp);
                $search_array[]=array('sku'=>$regexp);
                $search_array[]=array('item.title'=>$regexp);
                $input_array['$or']=$search_array;
            }
            else
            {
                foreach($query as $k=>$v)
                    $input_array[$k]=$v;
            }
        }

        if(isset($input_array['problem'])){
            if($input_array['problem']=='invalid_sku'){
                $input_array['sku']=null;
            }
            if($input_array['problem'] =='incorrect_price'){
                $input_array['problem.incorrect_price']= ['$exists'=>1];
            }
            unset($input_array['problem']);
        }

        Users::getUserStoresQuery($input_array);

        return $input_array;
    }

    public static function get_sort_keys()
    {
        return array('fetch_timestamp'=>-1);
    }

    public function update_qty($qty, $sku='')
    {
        // TODO: 需要转成异步工作的Tasklet
        $seller=$this->getSeller();
        if(empty($this->get("sku_on_property")) || empty($this->get("products")) ){
            $info= EtsyFeatures::getInventory($seller, intval(explode("-", $this->listing_id)[0]));
            if(isset($info['results'])) {
                $this->products = $info['results']['products'];
                $this->price_on_property = $info['results']['price_on_property'];
                $this->quantity_on_property = $info['results']['quantity_on_property'];
                $this->sku_on_property = $info['results']['sku_on_property'];
                $this->save();
            }
        }


        if(empty($this->get("sku_on_property"))){
            $this->set("item.quantity", $qty);
            $this->save();
            return EtsyFeatures::updateListing($seller, array(
                'listing_id'=>intval(explode("-", $this->listing_id)[0]),
                'quantity'=>min(99, intval($qty)),  // Etsy API限制最多999个
            ));
        }



        $products = $this->get("products");


        $new_products = [];
        foreach($products as $product){
            if($product['sku'] == $this->sku){
                $product['offerings'][0]['quantity']= $qty;
            }
            $product['property_values'][0]['values'][0] = str_replace("&quot;", "\"", $product['property_values'][0]['values'][0]);
            $new_products[]= $product;
        }

        $this->set("products", $new_products);
        $raw_qty = $this->get("item.quantity");
        $this->set("item.quantity", $qty);
        $this->save();

        try{
            EtsyFeatures::updateInventory($seller, $this);// 通过Inventory来管理
        }catch (Exception $e){
            $this->set("item.quantity", $raw_qty);
            $this->save();
            error_log("SKU: ".$this->sku." update qty failed");
            throw $e;
        }


//        return EtsyFeatures::updateListing($seller, array(
//            'listing_id'=>$this->listing_id,
//            'quantity'=>min(99, intval($qty)),  // Etsy API限制最多999个
//        ));
    }

    public function getListingTitle()
    {
        return $this->get('item.title');
    }

    public function getListingURL()
    {
        return $this->get('item.url', '');
    }

    public function getListingImageUrl()
    {
        return $this->get('thumb', '');
    }

    function is_active(){
        return $this->status=='active';
    }


    /**
     * 按照AmazonListing的内容进行更新
     */
    public function copyFromAmazonListing(AmazonListingItem $al, array $override=[]){
        $this->seller=@$override['seller'] ?: EtsyAccount::find_one();
        if($this->seller instanceof EtsyAccount) $this->seller=$this->seller->get_id();

        $this->sku=$al->sku;
        if(strlen($al->sku) > 32){
            throw new Exception("SKU Too Long for Etsy Listing\n", 400);
        }
        // Description中要包含SKU
        $description="";
        $description.=$al->get_title()."\n\n";
        $description.=$al->get('sku')."\n";
        foreach($al->get('attributes.Feature') as $feature) $description.="*{$feature}"."\n";
        $description.="\n";
        $description.=strip_tags($al->get('item.item-description'))."\n";

        $taxonomy_id=@$override['taxonomy_id'] ?: CategoryMapping::amazon_to_etsy($al->leaf_category_id)['id'] ?: panic(sprintf("Cannot find taxonomy_id: %s\n".$al->leaf_category_id));
        // update for creating variations: Size
        $title = @$override['title'] ?: $al->get_title();
        $title = preg_replace("/(.+?)-[ ]*?\d{2,}.*?x.*?\d{2,}.*/", "$1", $title);


        $this->set('item', [
            'title'=>$title,
            'quantity'=>10,
            'description'=>$description,
            'price'=>@$override['price'] ?: $al->get_price(),
            'taxonomy_id'=>intval($taxonomy_id),

            // 图片地址
            'Images'=>array_reverse(array_values($al->get_listing_images()), true),
            //TODO, find a better way to choose shipping template
            'shipping_template_id'=>'11480223565',
        ]);

        $this->save();
    }

    /**
     * @depends copyFromAmazonListing
     *
     */
    public function pushToMarket(){
        $account=EtsyAccount::getOne($this->seller);

        if(!$this->listing_id){
            // 新建的item，以默认方式上传
            $data=[
                'title'=>$this->get('item.title'),
                'quantity'=>10,
                'description'=>$this->get('item.description'),
                'price'=>$this->get('item.price'),
                'taxonomy_id'=>$this->get('item.taxonomy_id'),
                'shipping_template_id'=>$this->get('item.shipping_template_id'),
                'state'=>'active',
                'tags'=>"",
                'who_made'=>'i_did',
                'is_supply'=>0,
                'when_made'=>'made_to_order'
            ];

            $this->listing_id=strval(EtsyFeatures::createListing($account, $data));
            $this->save();

        }

        // 更新数量
        $this->update_qty($this->get('item.quantity'));
        // EtsyFeatures::updateListing($account, [
        //    'listing_id'=>$this->listing_id,
        //    'quantity'=>$this->get('item.quantity'),
        //    'price'=>$this->get('item.price')
        // ]);


        if($this->get('item.Images')){
            // 上传图片
            foreach($this->get('item.Images') as $url){
                $r=EtsyFeatures::uploadListingImage($account, [
                    'listing_id'=>strval(explode("-", $this->listing_id)[0]),
                    'image_url'=>$url
                ]);

                if(DEBUG) var_dump($r);
            }
        }
    }

    /**
     * 从市场更新
     */
    public function pullFromMarket(){
        $seller=EtsyAccount::getOne($this->seller);
        $listing=EtsyFeatures::getListing($seller, explode("-",$this->listing_id)[0]);
        EtsyListingItem::addOrUpdateFromRaw($seller, $listing['results']);
    }

    /**
     * 从市场删除
     */
    public function removeFromMarket(){
        $listing_id=$this->get("listing_id");
        if(empty($listing_id)) return false;
        $listing_id= explode("-",$listing_id)[0];
        if(count($this->get("products")) == 1 ){
            // 如果只有一个variation，直接删除商品
            try{
                $r=EtsyFeatures::deleteListing($this->getSeller(), $listing_id);
            }
            catch(Exception $e){
                if(strpos($e->getMessage(), 'Cannot delete a listing in state "removed"')!==false){
                    // 重复删除只是WARNING而已
                    $r=true;
                }
                else{
                    throw $e;
                }
            }
        }else{
            $products = $this->get("products");
            if(!$products){
                panic("No products, please fetch from online");
            }
            $new_products = [];




            foreach($products as $product){
                if($product['sku'] != $this->sku){
                    $product['property_values'][0]['values'][0] = str_replace("&quot;", "\"", $product['property_values'][0]['values'][0]);
                    $new_products[] = $product;
                }
            }
            $this->set("products", $new_products);
            $this->save();
            EtsyFeatures::updateInventory($this->getSeller(), $this);
        }



        $this->set('listing_id', null);
        $this->save();

        return $r;
    }

    public function start_selling()
    {
        $this->set('auto_stock', true)->save();
        $this->set("item.state", "active")->save();

        EtsyFeatures::updateListing($this->getSeller(), [
            'listing_id'=>intval(explode("-", $this->listing_id)[0]),
            'state'=>'active'
        ]);
        $this->update_qty(2);   //update marketplace qty to 2, auto_stock will further adjust qty
    }

    public function stop_selling()
    {
        $this->set('auto_stock', false)->save();
        $this->set("item.state", "inactive")->save();
        EtsyFeatures::updateListing($this->getSeller(), [
            'listing_id'=>intval(explode("-", $this->listing_id)[0]),
            'state'=>'inactive'
        ]);
//        $this->update_qty(0);
    }

    public static function addOrUpdateFromRaw($seller, $raw_items, $timestamp=0, $all=false)
    {
        foreach($raw_items as $list)
        {
            if(!$all && $timestamp && $list['last_modified_tsz'] < strtotime("yesterday")){
                return 1;
            }

            if(!$list['listing_id']) throw new Exception("Not a valid listing: ".json_encode($list));
            $item=EtsyListingItem::findOne(array(
                'seller'=>$seller->id(),
                'listing_id'=>['$regex'=>"^".(string)$list['listing_id']],
            )) ?: new EtsyListingItem();
            if(!$item->is_in_database()){
                file_put_contents(DIR_DATA."/etsy_new_listing.log", $list['listing_id']."\n", FILE_APPEND);
            }
            $item->status = "active";
            $item->saveItemInfo($seller, $list, $timestamp);

//            $real_listing_id = explode("-", $item->listing_id)[0];
            $real_listing_id = (string)$list['listing_id'];// 就是实际抓取的Listing_id
            // 为variation的情况
            $product_attr = EtsyFeatures::getInventory($seller, $real_listing_id)['results'];
            $item->products = $product_attr['products'];
            $item->save();
            if(count($product_attr['products']) ==1 && empty($product_attr['products'][0]['sku'])){
                continue;
            }


            $product_ids = [];//保留SKU和product的映射表
            foreach($product_attr['products'] as $product){
                $product_ids[$product['sku']] = ['product_id'=>$product['product_id'], 'price'=>$product['offerings'][0]['price']['currency_formatted_raw'], 'qty'=>$product['offerings'][0]['quantity']];
            }
            foreach($product_attr['products'] as $product_raw){
                $product=EtsyListingItem::findOne(array(
                    'seller'=>$seller->id(),
                    'sku'=>$product_raw['sku'],
                )) ?: new EtsyListingItem(['sku'=>$product_raw['sku'], 'listing_id'=>$real_listing_id."-".$product_ids[$product_raw['sku']]['product_id']]);
                if(!$product->is_in_database()){
                    file_put_contents(DIR_DATA."/etsy_new_product.log", $product_raw['sku']."\n", FILE_APPEND);
                }


                $list['price'] = $product_ids[$product->sku]['price'];
                $list['quantity'] = $product_ids[$product->sku]['qty'];

                $product->saveItemInfo($seller, $list, $timestamp);// 复用listing的保存Item，但是Price是不同的，在下面重写
                $product->products = $product_attr['products'];
                $product->price_on_property = $product_attr['price_on_property'];
                $product->quantity_on_property = $product_attr['quantity_on_property'];
                $product->sku_on_property = $product_attr['sku_on_property'];
                $product->set("listing_id", $real_listing_id."-".$product_ids[$product->sku]['product_id']);
                $product->set("product_id", $product_ids[$product->sku]['product_id']);
                $product->set("status", "active");
//                $product->set("item.price", $product_ids[$product->sku]['price']);
//                $product->set("item.quantity", $product_ids[$product->sku]['qty']);

                if(isset($list['Images'])){
                    $product->images=$list['Images'];
                }

                // 更新缩略图
                if(@$item->images){
                    $product->set('thumb', $item->images[0]['url_75x75']);
                }
                else{
                    $product->thumb=SkuInfo::sku_image($product->sku);
                }

                $product->save();

                if(VERBOSE) error_log("[ETSY] listing_id=$product->listing_id, sku=$product->sku, product_id=$product->product_id");

            }
        }
    }

    /**
     * 从产品描述中提取SKU
     * @ref: 原格式 control/listing::amazon_list_to_etsy()
     */
    public function detect_sku_from_description($text){
        // 描述中独立一行符合SKU格式的视作SKU
        if(preg_match('/\n([a-z0-9]{3,}\-[a-z0-9.\-@]+)\n/im', $text, $m)){
            return preg_replace('/@.+/', '', $m[1]);
        }

        return null;
    }

    public function saveItemInfo($seller, $list, $timestamp=0)
    {
        $this->seller=$seller->id();
        if(!$this->listing_id){
            $this->listing_id=(string)$list['listing_id'];
        }

        $this->item=Agent\Tools::use_string_for_id_fields($list);
        if(!$this->sku){
            if(!empty($list['sku'])){
                $this->sku = $list['sku'][0];
            }else{
                $this->sku=$this->detect_sku_from_description($list['description']);
            }
        }

        // 检查并规范SKU信息
        if(!$this->sku){
            notify_developer("SKU not defined for Etsy listing: $this->listing_id <br/><pre>".print_r($list)."</pre>");
            return false;
        }

        $this->fetch_timestamp=$timestamp ?: time();

//        if($this->get('item.has_variations')){
//            $this->get_listing_variations($seller);
//        }

        return $this->save();
    }

    public function get_listing_images($seller=null)
    {
        if(!$seller) $seller=$this->getSeller();

        if(!$this->images){
            $this->images=EtsyFeatures::findAllListingImages($seller, array(
                'listing_id'=>explode("-", $this->listing_id)[0],
            ));
            $this->save();
        }

        return $this->images;
    }

    public function get_listing_variations($seller=null)
    {
        if(!$seller)
            $seller=$this->getSeller();

        $variations=EtsyFeatures::getListingVariations($seller, array(
            'listing_id'=>$this->listing_id,
        ));

        if($variations['count'])
        {
            $variations=$variations['results'];
            $this->variations=$variations;

            $this->save();
        }
    }
//
//    public function validate(){
//        parent::validate();
//    }
}
?>
