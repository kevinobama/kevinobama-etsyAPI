<?php
require('EtsyAccount.php');
require('EtsyFeatures.php');
 
$account=new EtsyAccount();   

//$sizes = array('12x18" BLACK','16x24" BLACK','24x36" BLACK','32x48" BLACK');
$sizes = array('16x24','24x36');
$data = array();
// foreach ($sizes as $key=>$size) {
// 		$productsArray[]=[
//                'sku' => 'CVS-LISA-30-'.$size.'x1.50',
//                'property_values' => [
//                    [
// 					  "property_id"=>100,
// 	                  "property_name"=>"Size",
// 	                  "scale_id"=>327,
// 	                  "values"=>[$size],		                  
//                    ],[
//                        'property_id' => 200,
//                        'property_name' => "Primary color",
//                        'values' => ['Red']                          
//                    ]
//                ],
//                'offerings' => [
//                    [
//                        'price' => 99+$key,
//                        'quantity' => 10+$key,
//                    ]
//                ]
//            ];
// }

// $parameters=[
// 	'products' => json_encode($productsArray),
//        'price_on_property' => ['100,200'],
//        'quantity_on_property' => ['100,200'],
//        'sku_on_property' => ['100,200']
// ];
//FCV-MOUNTAIN-1909-M14-B.BK-16x24
foreach ($sizes as $key=>$size) {
        $productsArray[]=[
            'sku' => 'FCV-MOUNTAIN-1909-M14-B.WT-'.$size,
            'property_values' => [
                [
                      "property_id"=>100,
                      "property_name"=>"Size",
                      "values"=>[$size.' White'],                          
                ],[
                    'property_id' => 513,
                    'property_name' => "Frame Color",
                    'values' => ['White']                          
                ]
            ],
            'offerings' => [
                [
                    'price' => 99+$key,
                    'quantity' => 10+$key,
                ]
            ]
        ];
        $productsArray[]=[
            'sku' => 'FCV-MOUNTAIN-1909-M14-B.BK-'.$size,
            'property_values' => [
                [
                      "property_id"=>100,
                      "property_name"=>"Size",
                      "values"=>[$size.' Black'],                          
                ],[
                    'property_id' => 513,
                    'property_name' => "Frame Color",
                    'values' => ['Black']                          
                ]
            ],
            'offerings' => [
                [
                    'price' => 99+$key,
                    'quantity' => 10+$key,
                ]
            ]
        ];
        $productsArray[]=[
            'sku' => 'FCV-MOUNTAIN-1909-M14-B.WD-'.$size,
            'property_values' => [
                [
                      "property_id"=>100,
                      "property_name"=>"Size",
                      "values"=>[$size.' NATURAL'],                          
                ],[
                    'property_id' => 513,
                    'property_name' => "Frame Color",
                    'values' => ['NATURAL']                          
                ]
            ],
            'offerings' => [
                [
                    'price' => 99+$key,
                    'quantity' => 10+$key,
                ]
            ]
        ];        
}

$parameters=[
    'products' => json_encode($productsArray),
    'price_on_property' => ['100,513'],
    'quantity_on_property' => ['100,513'],
    'sku_on_property' => ['100,513']
];    
	
EtsyFeatures::updateInventory($account, '787641031', $parameters);             