<?php
require('EtsyAccount.php');
require('EtsyFeatures.php');

 
 
$listId = '772230236';//783967399
EtsyFeatures::getInventory($account, $listId, $parameters);            