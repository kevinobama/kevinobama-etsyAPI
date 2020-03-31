<?php
require('EtsyAccount.php');
require('EtsyFeatures.php');
 
$account=new EtsyAccount();   
 
// $parameters=[
//     ['property_id'=>100,
//     'value_id'=>50005157808, 
//     'image_id'=> 2221393082
//     ]    
// ];


// $parameters=[
//     ['property_id'=>100,
//     'value_id'=>50005157808, 
//     'image_id'=> 2221393082
//     ]    
// ];

// $parameters['variation_images']=[
//     'json' => json_encode(
//         array('property_id'=>100,
//         'value_id'=>50005157808, 
//         'image_id'=> 2221393082
//         )
//     )
//];

$parameters['variation_images']='[{"property_id": 100, "value_id": 50005157808, "image_id": 2221393082}]';

EtsyFeatures::getInventory($account, '771938444', array()); 
print_r(EtsyFeatures::findAllListingImages($account, array('listing_id'=>'771938444'))); 
error_log('====================================================================');
//print_r(EtsyFeatures::updateVariationImages($account, '771938444', $parameters)); 