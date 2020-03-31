<?php
require('EtsyAccount.php');
require('EtsyFeatures.php');

function getInventory() {
	$account=new EtsyAccount();        
    $parameters=[
        'products'=>array(1),
        'price_on_property'=>array(1),
        'quantity_on_property'=>array(1),
        'sku_on_property'=>array(1),
    ];
    $listId = '772230236';//783967399
    EtsyFeatures::getInventory($account, $listId, $parameters);            
}

getInventory();