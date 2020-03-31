<?php
require('EtsyAccount.php');
require('EtsyFeatures.php');

$account=new EtsyAccount();
$listing=EtsyFeatures::getListing($account, '786147071');//462138863
print_r($listing);
