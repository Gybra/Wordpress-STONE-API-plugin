<?php

/**
 * Http class used to deal with DATAEXIT APIs
 */
class Http{
  const ENDPOINT = [
    "live" => "https://ama-api-laslrpj4uh.datexit.com/StoneMLMService.svc/json/",
    "staging" => "https://ama-api-laslrpj4uh.datexit.com/test/StoneMLMService.svc/json/"
  ];
  const CREDENTIALS = [
    "user" => "DATAEXIT_USERNAME_API",
    "password" => "DATAEXIT_PASSWORD_API"
  ];
  const IS_LIVE = false;
  const ROUTE_INS_CUSTOMER = "ins_customer";
  const ROUTE_REFERRAL_DECODE = "referral_decode";
  const ROUTE_INSERT_ORDER = "ins_ord";
  const ROUTE_MOD_ORDER = "mod_ord";
  private $endpoint = "";

  function __construct(){
    $this->endpoint = self::ENDPOINT[self::IS_LIVE ? "live" : "staging"];
  }

  /**
   * Parse the JSON response into an object from a POST/GET request
   */
  public function getJSONResponse($route,$params,$isPost=false,$isJSONBodyRequest=false){
    $ch = curl_init($this->endpoint.$route.(!$isPost ? $this->buildGETParams($params) : ""));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if($isPost){
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $isJSONBodyRequest ? json_encode($params) : http_build_query($params));
    }

    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, self::CREDENTIALS["user"].":".self::CREDENTIALS["password"]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Content-Type: application/json',
      'Accept: application/json'
    ));
    
    $response = curl_exec($ch);
    
    curl_close($ch);

    return json_decode($response);
  }

  /**
   * Returns the GET params concatenated from the associative array passed as argument
   */
  private function buildGETParams($params=[]){
    if(!count($params)) return "";
    $queryParams = "?";
    foreach($params as $name => $paramValue) $queryParams .= "{$name}={$paramValue}&";
    return substr($queryParams,0,-1);
  }
}