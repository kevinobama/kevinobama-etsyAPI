<?php
class EtsyAccount
{
    public $access_key = 'ld7ipnb04o06m6tjjezr2cee';
    public $secret_access_key = 'gjgbl755ta';
    public $oauth_token = '4e497d2f9601333571b65928625325';
    public $oauth_token_secret = '8214b19c8e'; 
}

class EtsyFeatures
{
    const API_URL='https://openapi.etsy.com/v2';
    const LOGGING=true;

    public static function getListing($seller,$listing_id)
    {
        $url=self::API_URL.'/listings/'.$listing_id;
        $result=self::_call_api_oauth($seller,$url,$params=array(),OAUTH_HTTP_METHOD_GET);
        return $result;
    }

    public static function createListing($seller, $params=array())
    {
        $url=self::API_URL.'/listings';
        $result=self::_call_api_oauth($seller, $url, $params, OAUTH_HTTP_METHOD_POST);

        if($result['count']==1){
            return $result['results'][0]['listing_id'];
        }
        else{
            return array_map(function($r){return $r['listing_id'];}, $result['results'][0]['listing_id']);
        }
    }

    protected static function _call_api_oauth($seller, $url, $params=array(), $http_method=OAUTH_HTTP_METHOD_GET, $extra_headers=array())
    {
        error_log("Etsy AccessKey  : $seller->access_key");
        error_log("Etsy Secret     : $seller->secret_access_key");
        error_log("Etsy Token      : $seller->oauth_token");
        error_log("Etsy TokenSecret: $seller->oauth_token_secret");
   
        $oauth=new OAuth($seller->access_key, $seller->secret_access_key);
        $oauth->setToken($seller->oauth_token, $seller->oauth_token_secret);
 
        $oauth->enableDebug();

        try
        {
            error_log($http_method.'   '.$url);
            $ok=$oauth->fetch($url, $params, $http_method, $extra_headers);

            $response_info=$oauth->getLastResponseInfo();
            $response_code=intval($response_info['http_code']);
            $response=$oauth->getLastResponse();

            error_log($response);

            $r=json_decode($response);
 
            if($response_code<300 && $response_code>=200)
            {
                return json_decode($response, true);
            }
            else
            {
                error_log("Etsy Api Call Return: ".$response_code.", ".$response);
                return null;
            }
        }
        catch (OAuthException $e)
        {
            error_log('[Etsy] API Call failed: '.$url);         
        }
    } 
}

$account=new EtsyAccount();
$listing=EtsyFeatures::getListing($account, '783967399');
print_r($listing);