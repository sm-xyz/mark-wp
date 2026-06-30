<?php
include_once dirname(__FILE__) . '/../includes/duitku/wc-gateway-duitku-sanitized.php';
include_once dirname(__FILE__) . '/../includes/duitku/wc-gateway-duitku-validation.php';

class WC_Gateway_Duitku_Pop extends WC_Payment_Gateway
{
  /** @var bool whether or not logging is enabled */
  public static $log_enabled = false;

  public static $option_prefix = 'duitku';

  /** @var WC_Logger Logger instance */
  public static $log = false;

  /** you can control it with Sanitized (default: true) */
  public static $sanitized = true;
  public static $validation = true;
	
  function __construct()
  {

    $this->id           = 'duitku_pop';
    $this->icon = plugins_url('/assets/logo.png', dirname(__FILE__));
    $this->method_title = __('Duitku Payment', 'woocommerce');
    $this->has_fields   = true;
    $this->payment_method     = '';
    $this->redirect_url = WC()->api_request_url('WC_Gateway_' . $this->id);

    $this->init_form_fields();
    $this->init_settings();

    $this->title              = $this->get_option('title');
    $this->description        = $this->get_option('description');
    $this->apiKey             = $this->get_option('duitku_api_key');
    $this->merchantCode       = $this->get_option('duitku_merchant_code');
    $this->expiryPeriod       = $this->get_option('expiry_period');
    $this->prefix			  = $this->get_option('prefix');
    $this->pluginStatus       = $this->get_option('plugin_status');
    $this->lang               = $this->get_option('duitku_language');
    $this->currency           = $this->get_option('duitku_currency');

    self::$log_enabled = true;

    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));

    add_action('woocommerce_order_details_before_order_table', array($this, 'add_duitku_reference_to_order_details'), 10, 1);

    add_action('woocommerce_api_wc_gateway_duitku_pop', array(&$this, 'check_duitku_response'));
  }

  public function admin_options()
  { ?>
    <h3><?php _e('Duitku', 'woocommerce'); ?></h3>
    <p><?php _e('Allows payments using Duitku.', 'woocommerce'); ?></p>
    <table class="form-table">
      <?php
      // Generate the HTML For the settings form. generated from `init_form_fields`
      $this->generate_settings_html();
      ?>
    </table>
    <!--/.form-table-->
  <?php }

  function init_form_fields()
  {

    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable/Disable', 'woocommerce'),
        'type' => 'checkbox',
        'label' => __('Enable Duitku Payment', 'woocommerce'),
        'default' => 'yes'
      ),
      'title' => array(
        'title' => __('Title', 'woocommerce'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
        'default' => __('Online Payment via Duitku', 'woocommerce'),
        'desc_tip'      => true,
      ),
      'description' => array(
        'title' => __('Customer Message', 'woocommerce'),
        'type' => 'textarea',
        'description' => __('This controls the description which the user sees during checkout', 'woocommerce'),
        'default' => ''
      ),
      'plugin_status' => array(
        'title' => __('Plugin Status'),
        'type' => 'select',
        'description' => __('Select the plugin usage status.', 'woocommerce'),
        'required' => true,
        'options' => array(
          'production' => __('Production'),
          'sandbox' => __('Sandbox')
        ),
        'default' => 'sandbox'
      ),
      'duitku_language' => array(
        'title' => __('Language'),
        'type' => 'select',
        'description' => __('Select default language on payment page.', 'woocommerce'),
        'required' => true,
        'options' => array(
          'id' => __('Indonesia'),
          'en' => __('English')
        ),
        'default' => 'id'
      ),
      'duitku_currency' => array(
        'title' => __('Shown Estimation Currency'),
        'type' => 'select',
        'description' => __('Select currency for payment page display.</br> <span style="color:red;">These will only shows estimation rate and not an actual payment amount. Payment Amount is still paid in Indonesian Rupiah.</span>', 'woocommerce'),
        'required' => true,
        'options' => array(
          'idr' => __('-'),
          'usd' => __('USD - United States Dollar'),
          'eur' => __('EUR - Euro')
        ),
        'default' => 'idr'
      ),
      'duitku_merchant_code' => array(
        'title' => __('Merchant Code', 'woocommerce'),
        'type' => 'text',
        'description' => __('Input your Duitku Merchant Code (e.g D0001).', 'woocommerce'),
        'default' => ''
      ),
      'duitku_api_key' => array(
        'title' => __('API Key', 'woocommerce'),
        'type' => 'text',
        'description' => __('Input your Duitku API Key (e.g 732B39FC61796845775D2C4FB05332AF).', 'woocommerce'),
        'default' => ''
      ),
      'prefix' => array(
		'title' => __('Duitku Prefix', 'wc_duitku'),
		'type' => 'text',
		'description' => __('Prefix order id. Dapat digunakan untuk custom order id', 'woocommerce'),
		'id' => self::$option_prefix . '_prefix',
		'default' => ''
	  ),
      'expiry_period' => array(
        'title' => __('Expiry Period', 'woocommerce'),
        'type' => 'text',
        'description' => '<br />' . sprintf(__('The validity period of the transaction before it expires. (e.g 1 - 1440 ( min ))<br /><br /><br /><br />Log Path: <code>%s</code>', 'woocommerce'), wc_get_log_file_path('duitku')),
        'default' => '1440'
      )
    );
  }

  function process_payment($order_id)
  {
    global $woocommerce;
    $order = wc_get_order($order_id);

    $this->log('Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->redirect_url);

    $plugin_status = $this->pluginStatus;

    if ($plugin_status == 'sandbox') {
      $endpoint_url = "https://api-sandbox.duitku.com";
    }elseif ($plugin_status == 'production') {
      $endpoint_url = "https://api-prod.duitku.com";
    }

    $url = $endpoint_url . '/api/merchant/createInvoice';
    $this->log("url process payment " . $url);
    $lang = $this->lang;
    $currency = $this->currency;
    $tstamp = round(microtime(true) * 1000);
    $mcode = $this->merchantCode;
    $header_signature = hash('sha256', $mcode . $tstamp . $this->apiKey);
    $current_user = $order->get_billing_first_name() . " " . $order->get_billing_last_name();

    $item_details = [];

    foreach ($order->get_items() as $item_key => $item) {
      $item_name    = $item->get_name();
      $quantity     = $item->get_quantity();
      $product_price  = $item->get_subtotal();

      $item_details[] = array(
        'name' => $item_name,
        'price' => intval($product_price),
        'quantity' => $quantity
      );
    }

    // Shipping fee as item_details
    if ($order->get_total_shipping() > 0) {
      $item_details[] = array(
        'name' => 'Shipping Fee',
        'price' => ceil($order->get_total_shipping()),
        'quantity' => 1
      );
    }

    // Tax as item_details
    if ($order->get_total_tax() > 0) {
      $item_details[] = array(
        'name' => 'Tax',
        'price' => ceil($order->get_total_tax()),
        'quantity' => 1
      );
    }

    // Discount as item_details
    if ($order->get_total_discount() > 0) {
      $item_details[] = array(
        'name' => 'Total Discount',
        'price' => ceil($order->get_total_discount())  * -1,
        'quantity' => 1
      );
    }

    // Fees as item_details
    if (sizeof($order->get_fees()) > 0) {
      $fees = $order->get_fees();
      $i = 0;
      foreach ($fees as $item) {
        $item_details[] = array(
          'name' => $item['name'],
          'price' => ceil($item['line_total']),
          'quantity' => 1
        );
        $i++;
      }
    }

    $customer_details = array(
      'firstName' => $order->get_billing_first_name(),
      'lastName' => $order->get_billing_last_name(),
      'email' => $order->get_billing_email(),
      'phoneNumber' => $order->get_billing_phone(),
    );

    $shipping_address = array(
      'firstName' => $order->get_shipping_first_name(),
      'lastName' => $order->get_shipping_last_name(),
      'address' => $order->get_shipping_address_1() . " " . $order->get_shipping_address_2(),
      'city' => $order->get_shipping_city(),
      'postalCode' => $order->get_shipping_postcode(),
      'phone' => $order->get_billing_phone(),
      'countryCode' => $order->get_shipping_country()
    );

    $billing_address = array(
      'firstName' => $order->get_billing_first_name(),
      'lastName' => $order->get_billing_last_name(),
      'address' => $order->get_billing_address_1() . " " . $order->get_billing_address_2(),
      'city' => $order->get_billing_city(),
      'postalCode' => $order->get_billing_postcode(),
      'phone' => $order->get_billing_phone(),
      'countryCode' => $order->get_billing_country()
    );

    $customer_details['billingAddress'] = $billing_address;
    $customer_details['shippingAddress'] = $shipping_address;

    $params = array(
      'merchantOrderId' => $this->prefix . $order_id,
      'merchantUserInfo' => $current_user,
      'customerVaName' => $current_user,
      'paymentAmount' => intval($order->order_total),
      'paymentMethod' => $this->payment_method,
      'expiryPeriod' => intval($this->expiryPeriod),
      'productDetails' => get_bloginfo() . ' Order : #' . $order_id,
      'additionalParam' => '',
      'email' => $order->get_billing_email(),
      'phoneNumber' => $order->get_billing_phone(),
      'returnUrl' => esc_url_raw($this->redirect_url) . '?status=notify',
	  'callbackUrl' => esc_url_raw($this->redirect_url),
      'customerDetail' => $customer_details,
      'itemDetails' => $item_details
    );

    if (self::$validation) {
      WC_Gateway_Duitku_Pop_Validation::duitkuRequest($params);
    }
    
    if (self::$sanitized) {
      WC_Gateway_Duitku_Pop_Sanitized::duitkuRequest($params);
    }

    $this->log("Create a request for inquiry");
    $this->log(json_encode($params, true));
    
    $headers = array(
      'Content-Type' => 'application/json',
      'x-duitku-signature' => $header_signature,
      'x-duitku-timestamp' => $tstamp,
      'x-duitku-merchantCode' => $mcode
    );

    $args = array(
      'body'        => json_encode($params),
      'timeout'     => '90',
      'httpversion' => '1.0',
      'headers'     => $headers,
  );
  

    // Receive server response ...
    $response = wp_remote_post($url, $args);

    $this->log('raw response: ' . json_encode($response));
    $httpcode = wp_remote_retrieve_response_code($response);//curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $server_output = wp_remote_retrieve_body($response);//curl_exec($ch);

    $successResponse = array(
      'result'  => 'success',
      'redirect' => ''
    );

    if (!empty($server_output)) {
      $resp = json_decode($server_output);
      if (isset($resp->statusCode)) {
        if ($resp->statusCode == "00") {

          $this->log('Inquiry Success for order Id ' . $order->get_order_number() . ' with reference number ' . $resp->reference);

          $order->update_meta_data('_duitku_pg_reference', $resp->reference);
          $order->save();

          if ($currency !== "idr") {
            $redirectUrl = $resp->paymentUrl . "&lang=" . $lang . "&currency=" . $currency;
          } else {
            $redirectUrl = $resp->paymentUrl . "&lang=" . $lang;
          }

          $successResponse["redirect"] = $redirectUrl;
          WC()->cart->empty_cart();
          return $successResponse;
        } else {
          wc_add_notice($server_output, "notice", array());
          WC()->cart->empty_cart();
        }
      } else {
        if ($server_output == "Minimum Payment 10000") {
          wc_add_notice("Minimum Payment Rp.10000", "notice", array());
        } else {
          wc_add_notice($server_output, "notice", array());
        }
      }
    } else {
      $this->log('Inquiry failed for order Id ' . $order->get_order_number());
      // Transaction was not succesful Add notice to the cart

      if ($httpcode == "400") {
        // Add note to the order for your reference
        $order->add_order_note('Error:' .  $resp->Message);
        throw new Exception($resp->Message);
      } else {
        // Add note to the order for your reference
        $order->add_order_note('Error: error processing payment.');
        throw new Exception("Error processing payment."); 
      }
      return;
    }

    //log response from server
    $this->log('response: ' . $server_output);
    $this->log('response code: ' . $httpcode);
    $this->log($url);
  }

  protected function validate_transaction($order_id)
  {
    $order = wc_get_order( $order_id ); 
    $plugin_status = $this->pluginStatus;

    if ($plugin_status == 'sandbox') {
      $endpoint_url = "https://api-sandbox.duitku.com";
    }elseif ($plugin_status == 'production') {
      $endpoint_url = "https://api-prod.duitku.com";
    }

    $url = $endpoint_url . '/api/merchant/transactionStatus';
    $this->log("url validate transaction " . $url);
    $signature = md5($this->merchantCode . $this->prefix . $order_id . $this->apiKey);
    $params = array(
      'merchantCode' => $this->merchantCode, // API Key Merchant /
      'merchantOrderId' => $this->prefix . $order_id,
      'signature' => $signature
    );

    $headers = array(
      'Content-Type' => 'application/json',
    );

    $this->log("validate transaction:");
    $this->log(var_export(json_encode($params), true));
    $this->log("validate url: " . $url);

    // Receive server response ...
    $response = wp_remote_post($url, array(
      'body' => json_encode($params),
      'httpversion' => '1.0',
      'timeout' => '90',
      'sslverify' => false,
      'headers'     => $headers,
    ));
    $httpcode = wp_remote_retrieve_response_code($response);//curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $server_output = wp_remote_retrieve_body($response);//curl_exec($ch);
    $resp = json_decode($server_output);

    $this->log("response body: " . $server_output);
    $this->log("receive response HTTP Code: " . $httpcode . " with status code check transaction: " . $resp->statusCode);

    if ($httpcode == '200') {
		return $server_output;
	} else {
		$this->log($server_output);
	}
    exit;
  }

  function check_duitku_response(){

    $params = [];
    $params['resultCode'] = isset($_REQUEST['resultCode']) ? sanitize_text_field($_REQUEST['resultCode']) : null;
    $params['status'] = isset($_REQUEST['status']) ? sanitize_text_field($_REQUEST['status']) : null;
    $params['merchantOrderId'] = isset($_REQUEST['merchantOrderId']) ? sanitize_text_field($_REQUEST['merchantOrderId']) : null;
    $params['reference'] = isset($_REQUEST['reference']) ? sanitize_text_field($_REQUEST['reference']) : null;

    $params['merchantOrderId'] = str_replace($this->prefix,'',$params['merchantOrderId']);

    if (empty($params['resultCode']) || empty($params['merchantOrderId']) || empty($params['reference'])) {
		throw new Exception(__('wrong query string please contact admin.',
			'duitku'));
		return;
	}
    $this->log("param status" . $params['status']);

    if (!empty($params['status']) && $params['status'] == 'notify') {
		$this->notify_response($params);
		exit;
	}

    $order_id = wc_clean(stripslashes($params['merchantOrderId']));
    $result_Code = wc_clean(stripslashes($params['resultCode']));
    $reference = wc_clean(stripslashes($params['reference']));

    $params['signature']= isset($_REQUEST['signature'])? sanitize_text_field($_REQUEST['signature']): null;
	$reqSignature = wc_clean(stripslashes($params['signature']));

    $order = wc_get_order( $order_id ); 
    $amount = intval($order->order_total);

    //signature validation
	$signature = md5($this->merchantCode . $amount . $this->prefix . $order_id . $this->apiKey);

    // Log raw signature
	if($reqSignature == $signature){
		$this->log("Signature valid");
	}else{
		$this->log("Invalid signature!");
		exit;
	}
    $respon = json_decode($this->validate_transaction($order_id));
    $order->update_meta_data('_duitku_result_code', $respon->statusCode);
    $order->update_meta_data('_duitku_reference_number', $reference);

    if($result_Code == "00"){
        $respon = json_decode($this->validate_transaction($order_id));
        if($respon->statusCode == "00"){
            $order->payment_complete();
            $order->add_order_note(__('Pembayaran telah dilakukan melalui duitku dengan order ID ' . $order_id . ' Dan No Reference ' . $reference, 'woocommerce'));
            $this->log("Callback diterima. Pembayaran dengan order ID " . $order_id . " telah berhasil.");
        }
        else if($respon->statusCode == "01"){
            $order->add_order_note( "Pembayaran menggunakan Duitku dengan order ID " . $order_id . "reference " . $reference_number . " tertunda.");
            $this->log("Callback diterima. Pembayaran dengan order ID " . $order_id . " tertunda.");
        }
        else{
            $order->add_order_note("Callback diterima dengan result code " . $result_Code . " untuk order ID " . $order_id . " dan hasil validasi cek transaksi status code " . $respon->statusCode);
            $order->save();
            $this->log("Callback diterima dengan result code " . $result_Code . " untuk order ID " . $order_id . " dan hasil validasi cek transaksi status code " . $respon->statusCode);
        }
    }
    else if($result_Code == "01"){
        $respon = json_decode($this->validate_transaction($order_id));
        if($respon->statusCode == "02"){
            $order->update_status("failed");
            $order->add_order_note("Pembayaran menggunakan Duitku dengan order ID " . $order_id . "gagal");
            $this->log("Callback diterima. Pembayaran dengan order ID " . $order_id . "gagal");
        }
        else if($respon->statusCode == "01"){
            $order->add_order_note("Pembayaran menggunakan Duitku dengan order ID " . $order_id . "reference " . $reference_number . "tertunda");
            $this->log("Callback diterima. Pembayaran dengan order ID " . $order_id . " tertunda.");
        }
        else{
            $order->add_order_note("Callback diterima dengan result code " . $result_Code . " untuk order ID " . $order_id . " dan hasil validasi cek transaksi status code " . $respon->statusCode);
            $this->log("Callback diterima dengan result code " . $result_Code . " untuk Order ID " . $order_id . " dan hasil validasi cek transaksi status code " . $respon->statusCode);
        }
    }
    else{
        $order->add_order_note("Callback diterima dengan result code " . $result_Code . " untuk Order ID " . $order_id);
        $this->log("Callback diterima dengan result code " . $result_Code . " untuk Order ID " . $order_id);
    }
    $order->save();
    exit;
  }

  function notify_response($params){
      $this->log(var_export($params, true));

      if (empty($params['resultCode']) || empty($params['merchantOrderId'])) {
		throw new Exception(__('wrong query string please contact admin.', 'duitku'));
			return false;
	  }	

      $order_id = wc_clean(stripslashes($params['merchantOrderId']));
      $order_id = str_replace($this->prefix,'',$order_id);
      $order = wc_get_order($order_id);

      $order->update_meta_data('_duitku_result_code', $params['resultCode']);
      $order->update_meta_data('_duitku_reference_number', $params['reference']);

      if ($params['resultCode'] == '00') {
            $order->add_order_note("Transaksi untuk order ID " . $order_id . " telah diproses, menunggu verifikasi hasil pembayaran");
            $this->log('Notify Response. Transaksi untuk order ID ' .$order_id . " result code " .$params['resultCode']);

			WC()->cart->empty_cart();
			wc_add_notice('pembayaran dengan duitku telah diproses, menunggu verifikasi hasil pembayaran untuk order ID ' . $order_id);
            
            return wp_redirect($order->get_checkout_order_received_url());
	  }else if ($params['resultCode'] == '01') {
            $order->add_order_note("Transaksi untuk order ID " . $order_id . " sedang diproses");												
			wc_add_notice('pembayaran dengan duitku sedang diproses.');
            
            $this->log('Notify Response. Transaksi untuk order ID ' .$order_id . " result code " .$params['resultCode']);
			return wp_redirect($order->get_view_order_url());
	  }else if($params['resultCode'] == '02'){
            $this->log('back to checkout page');
            $order->add_order_note("Pembayaran dengan Duitku untuk order ID" . $order_id . " dibatalkan/tidak terbayar, dengan result code " .$params['resultCode']);
            $this->log('Notify Response. Transaksi untuk order ID ' .$order_id . " result code " .$params['resultCode']);

            wc_add_notice('Melakukan pembatalan pembayaran untuk order ID ' . $order_id);
            WC()->cart->empty_cart();
            
            return wp_redirect(home_url('/my-account/orders/'));
      }
      else {
            $order->add_order_note("Pembayaran dengan Duitku untuk order ID" . $order_id . " pembayaran mendapatkan result code " .$params['resultCode']);
            $this->log('Notify Response. Transaksi untuk order ID ' .$order_id . " result code " .$params['resultCode']);
            $this->log('back to checkout page');
            
            WC()->cart->empty_cart();
            return wp_redirect(home_url('/my-account/orders/'));	
	  }
      $order->save();
  }

  /**
  * function to add detail duitku reference number in order detail page
  */

  public function add_duitku_reference_to_order_details($order){
      $order_id = $order->get_id();
      $duitku_reference_number = get_post_meta($order_id, '_duitku_reference_number', true);
      if ($duitku_reference_number) {
            ?>
                <p><strong><?php _e('Reference:', 'wc_duitku'); ?></strong> <?php echo esc_html($duitku_reference_number); ?></p>
            <?php
      }
  }

  /**
   * function to generate log for debugging
   * to activate loggin please set debug to true in admin configuration
   * @param type $message
   * @return type
   */
  public static function log($message)
  {
    if (self::$log_enabled) {
      if (empty(self::$log)) {
        self::$log = new WC_Logger();
      }
      self::$log->add('duitku-pop', $message);
    }
  }
}
