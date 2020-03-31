
<?php
require('EtsyAccount.php');
require('EtsyFeatures.php');

function updateInventory() {
	$account=new EtsyAccount();   

	// $property_size = 100;
	// $property_fastener = 200;

	// $sizes = [
	//     [
	//         'property_id' => $property_size,
	//         'property_name' => "Size",
	//         //'value_ids'   => [1396],
	//         'values'        => ['111 laces'],

	//     ],
	//     [
	//         'property_id' => $property_size,
	//         'property_name' => "Size",
	//         //'value_ids'   => [1397],
	//         'values'        => ['222 laces'],
	//     ],
	// ];

	// $fasteners = [
	//     [
	//         'property_id'   => $property_fastener,
	//         'property_name' => 'Fastener Type',
	//         'values'        => ['Hook and loop'],
	//     ],
	//     [
	//         'property_id'   => $property_fastener,
	//         'property_name' => 'Fastener Type',
	//         'values'        => ['Ribbon laces'],
	//     ],
	// ];

	// $products = [
	//     [
	//         'property_values' => [$sizes[0], $fasteners[0]],
	//         'sku'             => 'one',
	//         'offerings'       => [
	//                                  [
	//                                      'price'      => 42.00,
	//                                      'quantity'   => 10,
	//                                      'is_enabled' => 1
	//                                  ]
	//                              ]
	//     ],
	//     [
	//         'property_values' => [$sizes[0], $fasteners[1]],
	//         'sku'             => 'two',
	//         'offerings'       => [
	//                                  [
	//                                      'price'      => 40.00,
	//                                      'quantity'   => 10,
	//                                      'is_enabled' => 1
	//                                  ]
	//                              ]
	//     ],
	//     [
	//         'property_values' => [$sizes[1], $fasteners[0]],
	//         'sku'             => 'three',
	//         'offerings'       => [
	//                                  [
	//                                      'price'      => 42.00,
	//                                      'quantity'   => 5,
	//                                      'is_enabled' => 1
	//                                  ]
	//                              ]
	//     ],
	//     [
	//         'property_values' => [$sizes[1], $fasteners[1]],
	//         'sku'             => 'four',
	//         'offerings'       => [
	//                                  [
	//                                      'price'      => 40.00,
	//                                      'quantity'   => 5,
	//                                      'is_enabled' => 1
	//                                  ]
	//                              ]
	//     ],
	// ];

	// $parameters=[
	// 	'products'=> json_encode($products),
 //        'price_on_property'    => [100],
 //        'quantity_on_property' => [100],
 //        'sku_on_property'      => [100]
	// ];
 	

    $parameters=array(
    	'products' => [
            'json' => json_encode([
                [
                    'sku' => 'sku-1',
                    'property_values' => [
                        [
                            'property_id' => 200,
                            'values' => 'red'
                        ],
                        [
                            'property_id' => 52047899318,
                            'value' => '57 cm'
                        ]
                    ],
                    'offerings' => [
                        [
                            'price' => 10,
                            'quantity' => 3
                        ]
                    ]
                ],                                
                [
                    'sku' => 'sku-4',
                    'property_values' => [
                        [
                            'property_id' => 200,
                            'value' => 'blue'
                        ],
                        [
                            'property_id' => 52047899318,
                            'value' => '68 cm'
                        ]
                    ],
                    'offerings' => [
                        [
                            'price' => 13,
                            'quantity' => 6
                        ]
                    ]
                ],
            ])
        ],
        'price_on_property' => ['200,52047899318'],
        'quantity_on_property' => ['200,52047899318'],
        'sku_on_property' => ['200,52047899318']);
 	
    EtsyFeatures::updateInventory($account, '783967399', $parameters);            
}

updateInventory();