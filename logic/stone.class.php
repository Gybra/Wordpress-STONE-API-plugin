<?php

/**
 * Class to send a new customer order to DATAEXIT API and assign commissions to referred agent if provided
 */
class StoneAPI{
  private const COOKIE_ID_ORDER_KEY = 'id_order';

  /**
   * Completes the order 
   */
  public static function completeOrder($id_order){
    if (!$id_order) return;

    // Allow code execution only once 
    if(!isset($_COOKIE[self::COOKIE_ID_ORDER_KEY]) || $id_order!=$_COOKIE[self::COOKIE_ID_ORDER_KEY]){
      setcookie(self::COOKIE_ID_ORDER_KEY,$id_order,strtotime("+1 year"),"/");
      
      $order = wc_get_order($id_order);
      $data = $order->get_data();
      $shipping = $data['shipping'];
      $fiscal_code = array_key_exists(0,$data['meta_data']) ? $data['meta_data'][0]->get_data()['value'] : '';
      $vat = array_key_exists(1,$data['meta_data']) ? $data['meta_data'][1]->get_data()['value'] : '';
      $customer = self::getCustomer($data['customer_id'],$data['billing']['email']);
      $referral = empty($customer) ? (isset($_COOKIE['referral']) ? $_COOKIE['referral'] : '') : $customer[0]->referral;

      if(empty($referral)) return;

      if(!empty($customer)){
        $agent = $referral;
      }else{
        $agent = $referral;//self::getAgent($referral); needed only if the referral was encrypted 
        if(false===$agent) return;
      }

      $params = [
        'login' => $data['billing']['email'],
        'ragione_sociale' => $data['billing']['company'],
        'cognome' => $data['billing']['last_name'],
        'nome' => $data['billing']['first_name'],
        'indirizzo' => $data['billing']['address_1'],
        'cap' => $data['billing']['postcode'],
        'citta' => $data['billing']['city'],
        'provincia' => $data['billing']['state'],
        'cellulare' => $data['billing']['phone'],
        'agente_presentante' => $agent,
        'email' => $data['billing']['email'],
        'codice_fiscale' => array_key_exists("meta_data",$data) ? $data["meta_data"][0]->get_data()["value"] : "",
        'partita_iva' => $vat,
        'country' => $data['billing']['country']
      ];

      self::createAgent($params,$id_order);

      if(empty($customer)){
        self::saveNewCustomer(
          $data['customer_id'],
          $params,
          $agent
        );
      }else{
        $customer = $customer[0];
        $id_user_stone = $customer->id_user_stone;
      }

      self::sendPurchase($id_order,$data);

    }
  }

  /**
   * Saves the unencrypted referral id inside the client's browser for further uses in the feature
   */
  public static function saveReferral(){
    if(isset($_GET['ref'])){
		  setcookie("referral",$_GET['ref'],strtotime("+1 year"),"/");
		  header("Location: ".str_replace("ref=".$_GET['ref'],"",$_SERVER['REQUEST_URI']));
    }
  }
  
  /**
   * Creates an agent if the kit bought is ID 520, 522, 524, 526
   */
  private static function createAgent($userParams,$id_order){
    $order = wc_get_order($id_order);
    $items = $order->get_items();
    foreach ( $items as $index => $item ) {
      $userParams['id_qualifica'] = $item->get_product_id();
      $isKitConsultant = $userParams['id_qualifica']==520 || $userParams['id_qualifica']==522;
      $isKitTeamBuilding = $userParams['id_qualifica']==524 || $userParams['id_qualifica']==526;
      $userParams['id_qualifica'] = intval($isKitTeamBuilding);
      if($isKitConsultant || $isKitTeamBuilding){
        $response = (new Http())->getJSONResponse(Http::ROUTE_INS_AGENT,$userParams,true,true);
      }
    }
  }

  /**
   * Send the new customer to DATAEXIT API
   */
  private static function saveNewCustomer($id_user,$params,$agent){
    global $wpdb;

    $response = (new Http())->getJSONResponse(Http::ROUTE_INS_CUSTOMER,$params,true,true);

    if($response->codice=="OK"){
      $id_user_stone = explode(" ",$response->descrizione)[1];
      $wpdb->insert($wpdb->prefix . 'stone', [
        'id_user' => $id_user,
        'id_user_stone' => $id_user_stone,
        'email' => $params['email'],
        'referral' => $agent,
      ]);
    }
  }

  /**
   * Returns the customer by passing a user id or user email
   */
  private static function getCustomer($id_user,$email){
    global $wpdb;

    return $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."stone WHERE (id_user={$id_user} && id_user<>0) || email='{$email}'");
  }

  /**
   * Returns the unencrypted agent id from an encrypted referral 
   */
  private static function getAgent($referral){
    $response = (new Http())->getJSONResponse(Http::ROUTE_REFERRAL_DECODE,[
      'code' => $referral
    ]);
    $response = $response->ReferralDecodeResult;
    
    if("OK"!=$response->esito->codice){
      return false;
    }

    return $response->dati[0]->codice_agente;
  }

  /**
   * Gets info from Woocommerce order and send the order details to DATAEXIT API
   */
  private static function sendPurchase($id_order,$data){
    $shipping = $data['shipping'];
    $order = wc_get_order($id_order);
    $items = $order->get_items();
    $details = [];
    $discount = $data["discount_total"]/count($items);

    foreach ( $items as $index => $item ) {
      $price = floatval(number_format($item->get_total()+$item->get_total_tax(),2,'.',','));
      $realPrice = $price-$discount;
      array_push($details,[
        "id" => $id_order."-".$index,
        "codice_prodotto" => $item->get_product_id(),
        "descr_prodotto" => substr($item->get_name(),0,49),
        "quantita" => $item->get_quantity(),
        "prezzo_listino" => $price,
        "prezzo_pagato" => $realPrice,
        "sconto_perc" => floatval(number_format(($discount*100)/$price,2,'.',',')),
        "netto" => $realPrice-$item->get_total_tax(),
        "punti" => 0
      ]);
    } 

    $params = [
      "login" => $data['billing']['email'],
      "data" => date("d/m/Y"),
      "ora" => date("H:i"),
      "data_pagamento" => strtolower($data['payment_method_title'])!="paypal" ? "" : date("d/m/Y"),
      "id" => $id_order,
      "tipo_pagamento" => $data['payment_method_title'],
      "destinatario" => $shipping['last_name']." ".$shipping['first_name'],
      "indirizzo" =>  $shipping['address_1'],
      "cap" => $shipping['postcode'],
      "provincia" => $shipping['state'],
      "citta" => $shipping['city'],
      "cellulare" => $data['billing']['phone'],
      "dettagli" => $details
    ];

    $http = new Http();
    $response = $http->getJSONResponse(Http::ROUTE_INSERT_ORDER,$params,true,true);
  }
}