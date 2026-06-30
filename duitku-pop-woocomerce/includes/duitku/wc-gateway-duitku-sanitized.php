<?php


/**
 * Request params filters.
 *
 * It truncate fields that have length limit, remove not allowed characters from other fields
 *
 * This feature is optional
 */
class WC_Gateway_Duitku_Pop_Sanitized

{
    //  private $filters;

    // public function __construct()
    // {
    //     $this->filters = array();
    // }
    
    /**
     * Validates and modify data
     * 
     * @param mixed[] $json
     */

    public static function duitkuRequest(&$json)
    {
        if (isset($json['merchantUserInfo'])){            
            $json['merchantUserInfo'] = preg_replace('/[^a-z0-9 \.]/i', '', $json['merchantUserInfo']); //remove special characters
            $json['merchantUserInfo'] = substr($json['merchantUserInfo'],0, 255);            
       }

       if (isset($json['customerVaName'])){            
            $json['customerVaName'] = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerVaName']); //remove special characters
            $json['customerVaName'] = substr($json['customerVaName'],0, 255);            
       }

       if (isset($json['productDetails'])){            
            $json['productDetails'] = preg_replace('/[^a-z0-9 \.]/i', '', $json['productDetails']); //remove special characters
            $json['productDetails'] = substr($json['productDetails'],0, 255);            
       }	   	   
	   
	     if (isset($json['expiryPeriod'])) {
		      $json['expiryPeriod'] = preg_replace('/[^0-9]/', '', $json['expiryPeriod']);
		      $json['expiryPeriod'] = intval($json['expiryPeriod']);
	     }	  	  

       if (isset($json['phoneNumber'])){
            $json['phoneNumber'] = preg_replace('/[^0-9]/', '', $json['phoneNumber']);            
       }

       if (isset($json['email'])){
        $json['email'] = filter_var($json['email'], FILTER_SANITIZE_EMAIL);
        $json['email'] = substr($json['email'],0, 255);        
       }

       foreach ($json['customerDetail'] as $key => $value) {

             if ($key == 'firstName') {
                $datafn = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['firstName']);
                $datafn2 = substr($datafn, 0, 50);
                $json['customerDetail']['firstName'] = $datafn2;
               }

            elseif ($key == 'lastName') {
              $dataln = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['lastName']);
              $dataln2 = substr($dataln, 0, 50);
              $json['customerDetail']['lastName'] = $dataln2;
              }

            elseif ($key == 'email') {
              $dataem = filter_var($json['email'], FILTER_SANITIZE_EMAIL);
              $dataem2 = substr($dataem, 0, 50);
              $json['customerDetail']['email'] = $dataem2;
              }

            elseif ($key == 'phoneNumber') {
              $datatl = preg_replace('/[^0-9]/', '', $json['phoneNumber']);
              $datatl2 = substr($datatl, 0, 50);
              $json['customerDetail']['phoneNumber'] = $datatl2;
              }

            foreach ($json['customerDetail']['billingAddress'] as $key_b => $value_b) {

              if ($key_b == 'firstName') {
                $datafnb = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['billingAddress']['firstName']);
                $datafnb2 = substr($datafnb, 0, 50);
                $json['customerDetail']['billingAddress']['firstName'] = $datafnb2;
               }

              elseif ($key_b == 'lastName') {
              $datalnb = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['billingAddress']['lastName']);
              $datalnb2 = substr($datalnb, 0, 50);
              $json['customerDetail']['billingAddress']['lastName'] = $datalnb2;
              }

              elseif ($key_b == 'address') {
              $dataadr2 = substr($json['customerDetail']['billingAddress']['address'], 0, 50);
              $json['customerDetail']['billingAddress']['address'] = $dataadr2;
              }

              elseif ($key_b == 'city') {
              $datacty = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['billingAddress']['city']);
              $datacty2 = substr($datacty, 0, 50);
              $json['customerDetail']['billingAddress']['city'] = $datacty2;
              }

              elseif ($key_b == 'postalCode') {
              $datapst = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['billingAddress']['postalCode']);
              $datapst2 = substr($datapst, 0, 50);
              $json['customerDetail']['billingAddress']['postalCode'] = $datapst2;
              }

              elseif ($key_b == 'phone') {
              $datatl = preg_replace('/[^0-9]/', '', $json['phoneNumber']);
              $datatl2 = substr($datatl, 0, 50);
              $json['customerDetail']['billingAddress']['phone'] = $datatl2;
              }

              elseif ($key_b == 'countryCode') {
              $datacnty= preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['billingAddress']['countryCode']);
              $datacnty2 = substr($datacnty, 0, 50);
              $json['customerDetail']['billingAddress']['countryCode'] = $datacnty2;
              }

            }

            foreach ($json['customerDetail']['shippingAddress'] as $key_s => $value_s) {

              if ($key_s == 'firstName') {
                $datafns = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['shippingAddress']['firstName']);
                $datafns2 = substr($datafns, 0, 50);
                $json['customerDetail']['shippingAddress']['firstName'] = $datafns2;
               }

              elseif ($key_s == 'lastName') {
              $datalns = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['shippingAddress']['lastName']);
              $datalns2 = substr($datalns, 0, 50);
              $json['customerDetail']['shippingAddress']['lastName'] = $datalns2;
              }

              elseif ($key_s == 'address') {
              $dataadrs2 = substr($json['customerDetail']['shippingAddress']['address'], 0, 50);
              $json['customerDetail']['shippingAddress']['address'] = $dataadrs2;
              }

              elseif ($key_s == 'city') {
              $datactys = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['shippingAddress']['city']);
              $datactys2 = substr($datacty, 0, 50);
              $json['customerDetail']['shippingAddress']['city'] = $datactys2;
              }

              elseif ($key_s == 'postalCode') {
              $datapsts = preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['shippingAddress']['postalCode']);
              $datapsts2 = substr($datapsts, 0, 50);
              $json['customerDetail']['shippingAddress']['postalCode'] = $datapsts2;
              }

              elseif ($key_s == 'phone') {
              $datatl = preg_replace('/[^0-9]/', '', $json['phoneNumber']);
              $datatl2 = substr($datatl, 0, 50);
              $json['customerDetail']['shippingAddress']['phone'] = $datatl2;
              }

              elseif ($key_s == 'countryCode') {
              $datacntys= preg_replace('/[^a-z0-9 \.]/i', '', $json['customerDetail']['shippingAddress']['countryCode']);
              $datacntys2 = substr($datacntys, 0, 50);
              $json['customerDetail']['shippingAddress']['countryCode'] = $datacntys2;
              }


            }
    
      
    }
     return $json;
}
}
