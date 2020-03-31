<?php
require('EtsyAccount.php');
require('EtsyFeatures.php');

function pushToMarket() {
        $account=new EtsyAccount();        
 
            $data=[
                'title'=>'kevingates',
                'quantity'=>1,
                'description'=>'kevingates',
                'price'=>99.00,
                'taxonomy_id'=>'2078',
                'shipping_template_id'=>'11480223565',
                'state'=>'draft',
                'tags'=>"kevingates",
                'who_made'=>'i_did',
                'is_supply'=>0,
                'when_made'=>'made_to_order'
            ];

            $listing_id=strval(EtsyFeatures::createListing($account, $data));
            error_log($listing_id); 
}

pushToMarket();