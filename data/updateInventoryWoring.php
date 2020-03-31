<?php
require('EtsyAccount.php');
require('EtsyFeatures.php');

function updateInventory() {
	$account=new EtsyAccount();   

	$parameters=[
		'products' => 
	             json_encode([                
	                [
	                    'sku' => 'kevin-sku-4',
	                    'property_values' => [
	                        [
	                            'property_id' => 200,
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
	            ]),
	        'price_on_property' => [200],
	        'quantity_on_property' => [200],
	        'sku_on_property' => [200],
	];


    EtsyFeatures::updateInventory($account, '783967399', $parameters);            
}

updateInventory();