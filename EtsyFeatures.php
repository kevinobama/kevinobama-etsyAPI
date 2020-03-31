<?php
/**
 * @link https://www.etsy.com/developers/documentation/
 */
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

        print_r($result);

        if($result['count']==1){
            return $result['results'][0]['listing_id'];
        }
        else{
            return count($result);
        }
    }


    public static function updateInventory($seller, $listingId, $params=array())
    {
        $url=self::API_URL.'/listings/'.$listingId.'/inventory';
        $result=self::_call_api_oauth($seller, $url, $params, OAUTH_HTTP_METHOD_PUT);

        print_r($result);
    }

    public static function getInventory($seller, $listingId, $params=array())
    {
        $url=self::API_URL.'/listings/'.$listingId.'/inventory';
        $result=self::_call_api_oauth($seller, $url, $params, OAUTH_HTTP_METHOD_GET);

        print_r($result);
        print_r($result['results']['products']);
    }

    public static function updateVariationImages($seller, $listingId, $params=array())
    {
        $url=self::API_URL.'/listings/'.$listingId.'/variation-images';
        $result=self::_call_api_oauth($seller, $url, $params, OAUTH_HTTP_METHOD_POST);

        return $result;
    }

    public static function getVariationImages($seller, $listingId, $params=array())
    {
        $url=self::API_URL.'/listings/'.$listingId.'/variation-images';
        $result=self::_call_api_oauth($seller, $url, $params, OAUTH_HTTP_METHOD_GET);

        return $result;
    }

    public static function getListingVariations($seller, $params=array())
    {
        $listing_id=$params['listing_id'];
        $url=self::API_URL.'/listings/'.$listing_id.'/variations';
        $result=self::_call_api_oauth($seller, $url, $params,OAUTH_HTTP_METHOD_GET);
        return $result;
    }

    public static function findAllListingImages($seller, $params=array())
    {
        $listing_id=$params['listing_id'];

 
        $url=self::API_URL.'/listings/'.$listing_id.'/images';
        $result=self::_call_api_get($seller, $url);

    }

    public static function findTransactionsByReceipt($seller, $receipt_id){
        $params['includes']='Buyer,MainImage,Listing,Receipt';
        $url=self::API_URL.'/receipts/'.$receipt_id.'/transactions';
        $result=self::_call_api_oauth($seller, $url, $params);
        return $result;
    }    

    //this is a helper for those etsy api who requires oauth
    protected static function _call_api_oauth($seller, $url, $params=array(), $http_method=OAUTH_HTTP_METHOD_GET, $extra_headers=array())
    {   
        $oauth=new OAuth($seller->access_key, $seller->secret_access_key);
        $oauth->setToken($seller->oauth_token, $seller->oauth_token_secret);
        // $oauth->setVersion("1.1");   // 默认使用1.0版本
        $oauth->enableDebug();

        try
        {
            error_log($http_method.'   '.$url);
            print_r($params);
            error_log('----------------params end--------------------');
            $ok=$oauth->fetch($url, $params, $http_method, $extra_headers);
            $response_info=$oauth->getLastResponseInfo();
            $response_code=intval($response_info['http_code']);
            $response=$oauth->getLastResponse();
            error_log('----------------response Start--------------------');
            error_log($response);            
            error_log('----------------response End--------------------');
            $r=json_decode($response);

            //success on 20x
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

            // 解析头部
            $header=[];
            foreach(explode("\n", $oauth->getLastResponseHeaders()) as $line){
                if(!strpos($line, ':')) continue;

                list($k, $v)=explode(":", $line, 2);
                $header[strtoupper(trim($k))]=trim($v);
            }

            $error=$header['X-ERROR-DETAIL'] ?: $e->getMessage();

            error_log('[Etsy] API Exception ('.$e->getCode().'): '.$error);
            throw new Exception("Etsy Api Exception Message: $error", $e->getCode());        
        }
    } 

protected static function _call_api_get($seller, $url, $params=array())
    {
        $params['api_key']=$seller->access_key;
        $escaped_params=array();
        foreach($params as $k=>$v)
        {
            $k=rawurlencode($k);
            $v=rawurlencode($v);
            $escaped_params[]="$k=$v";
        }
        $param_string=implode('&', $escaped_params);

        $final_url=$url.'?'.$param_string;

        error_log($final_url);
 

        return $final_url;
    }    
}
