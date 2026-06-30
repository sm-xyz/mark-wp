<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    register_rest_route('canvas-app/v1', '/verify', [
        'methods' => ['POST', 'OPTIONS'],
        'callback' => 'cl_rest_verify',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('canvas-app/v1', '/generate', [
        'methods' => ['POST', 'OPTIONS'],
        'callback' => 'cl_rest_generate',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('canvas-app/v1', '/webhook', [
        'methods' => ['POST', 'OPTIONS'],
        'callback' => 'cl_rest_webhook_payment',
        'permission_callback' => '__return_true'
    ]);
});





function cl_rest_webhook_payment(WP_REST_Request $req) {
    global $wpdb;
    $p = $req->get_json_params() ?: $req->get_body_params();
    $user_key = sanitize_text_field($req->get_param('user_key') ?? $req->get_param('secret_key') ?? ($p['user_key'] ?? ''));
    
    $log_webhook = function($user_id, $code, $res_data, $source = 'lynk.id') use ($wpdb, $p) {
        $wpdb->insert($wpdb->prefix . 'cl_webhook_logs', [
            'user_id' => $user_id,
            'event_source' => $source,
            'payload' => wp_json_encode($p),
            'response' => wp_json_encode($res_data),
            'status_code' => $code,
            'created_at' => current_time('mysql')
        ]);
    };

    // Ensure we handle test payloads from URL where test_mode=true but user_key is secret_key
    if (!$user_key && isset($p['test_mode'])) {
        $user_key = sanitize_text_field($req->get_param('secret_key'));
    }

    if (!$user_key) {
        $res = ['valid'=>false, 'message'=>'Ditolak. User key tidak ada.'];
        $log_webhook(0, 401, $res);
        return new WP_REST_Response($res, 401);
    }
    
    $users = get_users(['meta_key' => 'cl_webhook_secret', 'meta_value' => $user_key]);
    if (empty($users)) {
        $res = ['valid'=>false, 'message'=>'Ditolak. User key tidak dikenali.'];
        $log_webhook(0, 401, $res);
        return new WP_REST_Response($res, 401);
    }
    $seller = $users[0];
    $merchant_key = get_user_meta($seller->ID, 'cl_lynk_merchant_key', true);

    $buyer_name = 'Pelanggan';
    $buyer_email = '';
    $buyer_phone = '';
    $product_name_from_webhook = '';
    $source_name = 'lynk.id';
    
    // Check if Mayar or Lynk.id
    $is_mayar = isset($p['data']['customerName']) && isset($p['data']['productName']);
    $signature_scalev = $req->get_header('x_scalev_hmac_sha256');
    $is_scalev = !empty($signature_scalev) || (isset($p['test_mode']) && isset($p['type']) && $p['type'] === 'scalev');

    // Catch Mayar testing
    if (isset($p['event']) && $p['event'] === 'testing') {
        $source_name = 'mayar.id';
        $res = ['valid'=>true, 'message'=>'Test webhook Mayar diterima.'];
        $log_webhook($seller->ID, 200, $res, $source_name);
        return new WP_REST_Response($res, 200);
    }

    if ($is_scalev) {
        $source_name = 'scalev';
        $scalev_signing_secret = get_user_meta($seller->ID, 'cl_scalev_signing_secret', true);
        
        if (!isset($p['test_mode'])) {
            $raw_body = $req->get_body();
            if ($scalev_signing_secret && $signature_scalev) {
                $calculated_signature = base64_encode(hash_hmac('sha256', $raw_body, $scalev_signing_secret, true));
                if (!hash_equals($calculated_signature, $signature_scalev)) {
                    $res = ['valid'=>false, 'message'=>'Invalid Scalev Signature.'];
                    $log_webhook($seller->ID, 401, $res, $source_name);
                    return new WP_REST_Response($res, 401);
                }
            }
        }

        $payment_status = strtolower($p['payment_status'] ?? '');
        if ($payment_status !== 'paid') {
            $res = ['valid'=>true, 'message'=>'Diabaikan karena status pembayaran bukan paid. (' . $payment_status . ')'];
            $log_webhook($seller->ID, 200, $res, $source_name);
            return new WP_REST_Response($res, 200);
        }

        $buyer_name = sanitize_text_field($p['destination_address']['name'] ?? ($p['customer_name'] ?? 'Pelanggan'));
        $buyer_email = sanitize_email($p['destination_address']['email'] ?? ($p['customer_email'] ?? ''));
        $buyer_phone = cl_normalize_wa(sanitize_text_field($p['destination_address']['phone'] ?? ($p['customer_phone'] ?? '')));
        
        if (!empty($p['orderlines']) && is_array($p['orderlines'])) {
            $product_name_from_webhook = sanitize_text_field($p['orderlines'][0]['product_name'] ?? '');
        } else {
            $product_name_from_webhook = sanitize_text_field($p['product_name'] ?? '');
        }

    } elseif ($is_mayar && isset($p['event']) && $p['event'] === 'payment.received') {
        $source_name = 'mayar.id';
        // Flow for Mayar actual event
        $status = $p['data']['status'] ?? '';
        if ($status !== 'SUCCESS') {
            $res = ['valid'=>true, 'message'=>'Diabaikan karena status bukan SUCCESS. (' . $status . ')'];
            $log_webhook($seller->ID, 200, $res, $source_name);
            return new WP_REST_Response($res, 200);
        }
        $buyer_name = sanitize_text_field($p['data']['customerName'] ?? 'Pelanggan');
        $buyer_email = sanitize_email($p['data']['customerEmail'] ?? '');
        $buyer_phone = cl_normalize_wa(sanitize_text_field($p['data']['customerMobile'] ?? ''));
        $product_name_from_webhook = sanitize_text_field($p['data']['productName'] ?? '');
    } elseif (isset($p['data']['message_action']) || $req->get_header('x_lynk_signature')) {
        // Flow for Lynk.id actual event
        $signature = $req->get_header('x_lynk_signature');
        
        $amount = isset($p['data']['message_data']['totals']['grandTotal']) ? (string)$p['data']['message_data']['totals']['grandTotal'] : '';
        $ref_id = $p['data']['message_data']['refId'] ?? '';
        $message_id = $p['data']['message_id'] ?? '';

            if (!isset($p['test_mode']) && $merchant_key && $signature) {
                 $signature_string = $amount . $ref_id . $message_id . $merchant_key;
                 $calculated_signature = hash('sha256', $signature_string);
                 if ($calculated_signature !== $signature) {
                     $res = ['valid'=>false, 'message'=>'Invalid Lynk Signature.'];
                     $log_webhook($seller->ID, 401, $res, $source_name);
                     return new WP_REST_Response($res, 401);
                 }
            }

            $status = $p['data']['message_action'] ?? '';
            if ($status !== 'SUCCESS') {
                $res = ['valid'=>true, 'message'=>'Diabaikan karena status bukan SUCCESS. (' . $status . ')'];
                $log_webhook($seller->ID, 200, $res, $source_name);
                return new WP_REST_Response($res, 200);
            }

            $buyer_name = sanitize_text_field($p['data']['message_data']['customer']['name'] ?? 'Pelanggan');
            $buyer_email = sanitize_email($p['data']['message_data']['customer']['email'] ?? '');
            $buyer_phone = cl_normalize_wa(sanitize_text_field($p['data']['message_data']['customer']['phone'] ?? ''));
            
            if (!empty($p['data']['message_data']['items']) && is_array($p['data']['message_data']['items'])) {
                $product_name_from_webhook = sanitize_text_field($p['data']['message_data']['items'][0]['title'] ?? '');
            }
    } else {
        // Fallback / Postman Test Mode
        if ($is_mayar) {
            $source_name = 'mayar.id';
            $status = $p['data']['status'] ?? '';
            // If it's mayar and notSUCCESS, ignore
        }
        
        $status = sanitize_text_field($p['status'] ?? ($p['data']['status'] ?? ''));
        if (strtolower($status) !== 'paid' && strtolower($status) !== 'settlement' && strtolower($status) !== 'success') {
            $res = ['valid'=>true, 'message'=>'Diabaikan karena status bukan berbayar lunas.'];
            $log_webhook($seller->ID, 200, $res, $source_name);
            return new WP_REST_Response($res, 200);
        }
        
        $buyer_name = sanitize_text_field($p['customer_name'] ?? ($p['data']['customerName'] ?? 'Pelanggan'));
        $buyer_email = sanitize_email($p['customer_email'] ?? ($p['data']['customerEmail'] ?? ''));
        $buyer_phone = cl_normalize_wa(sanitize_text_field($p['customer_phone'] ?? ($p['data']['customerMobile'] ?? '')));
        
        $product_name_from_webhook = sanitize_text_field($p['product_name'] ?? ($p['item_name'] ?? ($p['data']['productName'] ?? '')));
        if (empty($product_name_from_webhook) && !empty($p['items']) && is_array($p['items'])) {
            $product_name_from_webhook = sanitize_text_field($p['items'][0]['name'] ?? ($p['items'][0]['product_name'] ?? ''));
        }
    }
    
    // Setup proper log user ID so SaaS test shows up in Admin Log, License shows in Member Log
    $saas_name = get_option('cl_admin_saas_product_name', 'Sharelink SaaS Workspace');
    $is_saas_order = !empty($product_name_from_webhook) && strtolower(trim($product_name_from_webhook)) === strtolower(trim($saas_name)) && user_can($seller->ID, 'manage_options');
    $log_uid = $is_saas_order ? 0 : $seller->ID;
    
    // FORK: Workspace (SaaS Admin Area) vs License (Member Area)
    if ($is_saas_order) {
        
        if (!email_exists($buyer_email)) {
            $user_pass = $buyer_phone ?: wp_generate_password(8, false);
            $user_id = wp_create_user($buyer_email, $user_pass, $buyer_email);
            
            if (!is_wp_error($user_id)) {
                $u = new WP_User($user_id);
                $u->set_role('subscriber');
                wp_update_user(['ID' => $user_id, 'display_name' => $buyer_name]);
                
                if ($buyer_phone) {
                    update_user_meta($user_id, 'cl_wa_number', $buyer_phone);
                }
                update_user_meta($user_id, 'cl_quota_limit', 100);
                
                $login_url = home_url('/login');
                
                $wa_tpl = get_option('cl_wa_tpl_workspace', '');
                $wa_msg = str_replace(['{buyer_name}', '{login_url}', '{buyer_email}', '{user_pass}'], 
                                      [$buyer_name, $login_url, $buyer_email, $user_pass], $wa_tpl);
                
                $em_tpl = get_option('cl_email_tpl_workspace', '');
                $email_html = str_replace(['{buyer_name}', '{login_url}', '{buyer_email}', '{user_pass}'], 
                                          [$buyer_name, $login_url, $buyer_email, $user_pass], $em_tpl);

                $fonnte_token = get_option('cl_fonnte_token');
                if ($fonnte_token && $buyer_phone && $wa_msg) {
                    wp_remote_post('https://api.fonnte.com/send', [
                        'headers' => ['Authorization' => $fonnte_token],
                        'body' => [
                            'target' => $buyer_phone,
                            'message' => $wa_msg
                        ]
                    ]);
                }
                if ($buyer_email && $email_html) {
                    cl_send_email($buyer_email, "Pesanan Anda Berhasil - Sharelink AI", $email_html);
                }
                
                $res = ['valid'=>true, 'message'=>"Akun workspace berhasil dibuat via webhook $source_name."];
                $log_webhook($log_uid, 200, $res, $source_name);
                return new WP_REST_Response($res, 200);

            } else {
                $res = ['valid'=>false, 'message'=>$user_id->get_error_message()];
                $log_webhook($log_uid, 400, $res, $source_name);
                return new WP_REST_Response($res, 400);
            }
        } else {
            $res = ['valid'=>true, 'message'=>'Akun sudah pernah dibuat untuk email ini.'];
            $log_webhook($log_uid, 200, $res, $source_name);
            return new WP_REST_Response($res, 200);
        }
    }
    
    // Flow: Retail License (Member Area)

    $aid = intval($p['app_id'] ?? 0);
    $lt = $wpdb->prefix . CL_LICS;
    $at = $wpdb->prefix . CL_APPS;
    $app_name = "Global";

    // Sync to App User Contacts (cl_customers)
    if (!empty($buyer_email) || !empty($buyer_phone) || !empty($buyer_name)) {
        $ct = $wpdb->prefix . 'cl_customers';
        $exists_cust = false;
        if (!empty($buyer_email) || !empty($buyer_phone)) {
            $exists_cust = $wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE user_id=%d AND ((email=%s AND email != '') OR (wa_number=%s AND wa_number != ''))", $seller->ID, $buyer_email, $buyer_phone));
        } else {
            $exists_cust = $wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE user_id=%d AND name=%s AND email='' AND wa_number=''", $seller->ID, $buyer_name));
        }
        
        if (!$exists_cust) {
            $wpdb->insert($ct, [
                'user_id' => $seller->ID,
                'name' => $buyer_name,
                'email' => $buyer_email,
                'wa_number' => $buyer_phone,
                'created_at' => current_time('mysql')
            ]);
        }
    }
    
    // Auto-detect app by Lynk.id product name if app_id is not provided
    if ($aid === 0) {
        $found_app = null;
        if (!empty($product_name_from_webhook)) {
             $found_app = $wpdb->get_row($wpdb->prepare("SELECT id, app_name, canvas_link, custom_slug, gk_config FROM $at WHERE user_id=%d AND app_name = %s LIMIT 1", $seller->ID, $product_name_from_webhook));
             if (!$found_app) {
                  $found_app = $wpdb->get_row($wpdb->prepare("SELECT id, app_name, canvas_link, custom_slug, gk_config FROM $at WHERE user_id=%d AND app_name LIKE %s LIMIT 1", $seller->ID, '%' . $wpdb->esc_like($product_name_from_webhook) . '%'));
             }
        }
        
        if ($found_app) {
            $aid = $found_app->id;
            $app_name = $found_app->app_name;
        } else {
             // **Failover**: Exact app not found
             if (!empty($p['test_mode'])) {
                  // If it's a test mode from Dashboard, allow global license generation
                  $app_name = "Global (Test Mode)";
             } else {
                  // Actual traffic without valid app name
                  $seller_phone = get_user_meta($seller->ID, 'cl_wa_number', true);
                  $seller_email = $seller->user_email;
                  
                  $prod_name_display = empty($product_name_from_webhook) ? "(Tanpa Nama Produk)" : $product_name_from_webhook;
                  
                  $alert_msg = "⚠️ *Perhatian Webhook - Sharelink*\n\n";
                  $alert_msg .= "Ada transaksi pembelian lisensi untuk produk *{$prod_name_display}* dari *{$buyer_name}* ({$buyer_email}), TETAPI Sharelink tidak dapat menemukan Canvas App Anda yang bernama tersebut.\n\n";
                  $alert_msg .= "Lisensi *TIDAK DIKIRIM*. Harap Anda generate dan kirim secara manual, lalu pastikan nama Canvas App Anda dirubah persis sama dengan nama produk di Lynk.id agar proses selanjutnya berjalan otomatis.";
                  
                  $fonnte_token = get_option('cl_fonnte_token');
                  if ($fonnte_token && $seller_phone) {
                      wp_remote_post('https://api.fonnte.com/send', [
                          'headers' => ['Authorization' => $fonnte_token],
                          'body' => [
                              'target' => $seller_phone,
                              'message' => $alert_msg
                          ]
                      ]);
                  }
                  
                  if ($seller_email) {
                      cl_send_email($seller_email, "⚠️ Peringatan Transaksi Webhook Tidak Dikenali", nl2br($alert_msg));
                  }
                  
                  // Return 200 so Lynk.id marks it delivered, but log warning
                  $res = ['valid'=>true, 'message'=>"Lisensi ditangguhkan karena aplikasi '$prod_name_display' tidak ditemukan. Kontak pembeli telah disimpan ke daftar App User."];
                  $log_webhook($seller->ID, 200, $res, $source_name);
                  return new WP_REST_Response($res, 200);
             }
        }
    }
    
    // Quota Enforcement
    $used_lics = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $lt WHERE user_id=%d", $seller->ID));
    $quota_limit = (int)get_user_meta($seller->ID, 'cl_quota_limit', true);
    if ($quota_limit < 1) $quota_limit = 100;
    
    if ($used_lics >= $quota_limit) {
        $res = ['valid'=>false, 'message'=>'Gagal di-generate. Kuota lisensi ' . $seller->display_name . ' telah habis.'];
        $log_webhook($seller->ID, 403, $res, $source_name);
        return new WP_REST_Response($res, 403);
    }
    
    $max_devices = 100;
    $expires_at = null;

    if ($aid > 0 && $app_name === "Global") {
        $found_app2 = $wpdb->get_row($wpdb->prepare("SELECT id, app_name, canvas_link, custom_slug, gk_config FROM $at WHERE id=%d", $aid));
        if ($found_app2) {
            $app_name = $found_app2->app_name;
            $found_app = $found_app2;
        }
    }

    if (isset($found_app) && $found_app) {
        $gk = json_decode($found_app->gk_config, true) ?: [];
        $max_devices_req = intval($gk['webhook_default_limit'] ?? 100);
        if ($max_devices_req > 0) $max_devices = $max_devices_req;
        
        $exp_days = intval($gk['webhook_default_expired'] ?? 0);
        if ($exp_days > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime("+$exp_days days"));
        }
    }
    
    $key = 'CL' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 12)) . rand(10,99);
    
    $lbl_prefix = $is_scalev ? 'Scalev Order ' : ($is_mayar ? 'Mayar.id Order ' : 'Lynk.id Order ');
    $lbl_order = $is_scalev ? ($p['order_id'] ?? time()) : ($is_mayar ? ($p['data']['id'] ?? time()) : ($p['data']['message_data']['refId'] ?? ($p['order_id'] ?? time())));
    
    $wpdb->insert($lt, [
        'user_id' => $seller->ID,
        'license_key' => $key,
        'app_id' => $aid,
        'status' => 'active',
        'label' => $lbl_prefix . $lbl_order . (empty($product_name_from_webhook) ? '' : ' - ' . $product_name_from_webhook),
        'assignee_name' => $buyer_name,
        'assignee_email'=> $buyer_email,
        'assignee_wa'   => $buyer_phone,
        'max_devices'   => $max_devices,
        'expires_at'    => $expires_at,
        'created_at' => current_time('mysql')
    ]);
    
    cl_insert_history($seller->ID, "Lisensi $key berhasil di-generate via Webhook. Label/Order: $lbl_prefix" . "$lbl_order" . ". Penerima: " . ($buyer_name ?: 'Pengguna') . ($buyer_email ? " ($buyer_email)" : "") . ($buyer_phone ? " ($buyer_phone)" : ""));
    
    $member_wa = get_user_meta($seller->ID, 'cl_wa_number', true) ?: '-';
    
    // Fallback default templates if user hasn't set custom autoresponder
    $def_wa_msg = "Halo *{buyer_name}*,\nSelamat, pesanan akses untuk aplikasi *{app_name}* Anda telah diaktifkan! 🎉\nBerikut adalah informasi detail untuk mengakses canvas kami dengan aman:\n================================\nKunci Lisensi Pribadi: `{license_key}`\nLink URL Akses     : {link_gemini_CANVAS}\nLink Custom    : {custom_link}\n================================\nCatatan Penting:\nSilahkan kunjungi link akses di atas, lalu masukkan kunci lisensi untuk mulai menggunakan fitur lengkap aplikasi ini. Lisensi ini bersifat eksklusif untuk Anda.\n> Jika ada kendala, jangan ragu menghubungi tim seller *{app_name}* di whatsapp: https://wa.me/{workspace_owner_wa}\n\nTerima kasih dan selamat berkarya! ✨";
    
    $def_em_msg = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Aktivasi Lisensi {app_name}</title>
    <style type="text/css">
        /* Client-specific Styles untuk memastikan tampilan konsisten di semua aplikasi email */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f8fafc; }

        /* CSS Responsif untuk Smartphone */
        @media screen and (max-width: 600px) {
            .wrapper { width: 100% !important; max-width: 100% !important; padding: 0 !important; }
            .container { padding: 32px 20px !important; }
            .button-wrapper { width: 100% !important; text-align: center !important; }
            .button { display: block !important; padding: 14px 20px !important; }
            .credential-col { display: block !important; width: 100% !important; padding-right: 0 !important; padding-bottom: 20px !important; }
            .credential-col-last { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

    <div style="display: none; max-height: 0px; overflow: hidden; font-size: 1px; line-height: 1px; color: #ffffff; opacity: 0;">
        Terima kasih atas pesanan Anda. Akses resmi untuk aplikasi {app_name} kini telah aktif dan siap digunakan.
    </div>

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; table-layout: fixed;">
        <tr>
            <td align="center" style="padding: 40px 10px;">
                
                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="wrapper" style="max-width: 580px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                    
                    <tr>
                        <td class="container" style="padding: 48px 40px; color: #1e293b;">
                            
                            <h2 style="font-size: 20px; font-weight: 700; color: #0d2870; margin-top: 0; margin-bottom: 24px; line-height: 1.4; letter-spacing: -0.01em;">
                                Akses Aplikasi Anda Sudah Siap, {buyer_name}!
                            </h2>

                            <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 16px;">
                                Terima kasih banyak atas kepercayaan serta pesanan Anda. Kami menginformasikan bahwa tim kami telah mengaktifkan akses penuh Anda ke dalam aplikasi <strong>{app_name}</strong>.
                            </p>

                            <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 24px;">
                                Berikut adalah rincian kredensial lisensi serta tautan akses resmi yang dapat langsung Anda gunakan:
                            </p>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td class="credential-col" valign="top" width="50%" style="padding-right: 16px;">
                                                    <p style="margin: 0 0 6px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">
                                                        Tautan Kustom Anda
                                                    </p>
                                                    <p style="margin: 0; font-size: 14px; color: #0d2870; font-weight: 600; word-break: break-all;">
                                                        <a href="{custom_link}" target="_blank" style="color: #0d2870; text-decoration: underline;">
                                                            {custom_link}
                                                        </a>
                                                    </p>
                                                </td>
                                                <td class="credential-col-last" valign="top" width="50%">
                                                    <p style="margin: 0 0 8px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">
                                                        Kode Lisensi Eksklusif
                                                    </p>
                                                    <table border="0" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td style="background-color: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 10px;">
                                                                <code style="font-family: Menlo, Monaco, Consolas, \'Courier New\', monospace; font-size: 14px; font-weight: bold; color: #0f172a;">
                                                                    {license_key}
                                                                </code>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 32px;">
                                <tr>
                                    <td align="left">
                                        <table border="0" cellspacing="0" cellpadding="0" class="button-wrapper">
                                            <tr>
                                                <td align="center" bgcolor="#0d2870" style="border-radius: 4px;">
                                                    <a href="{link_gemini_CANVAS}" target="_blank" class="button" style="font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; color: #ffffff; text-decoration: none; border-radius: 4px; padding: 12px 28px; border: 1px solid #0d2870; display: inline-block; font-weight: 600; letter-spacing: 0.02em;">
                                                        Akses Aplikasi Anda &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 14.5px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 24px;">
                                Silakan gunakan <strong>Kode Lisensi Eksklusif</strong> di atas ketika sistem memintanya pada halaman login awal aplikasi Anda. Demi kenyamanan Anda, akses ini bersifat aman dan dibatasi khusus untuk penggunaan personal.
                            </p>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 16px 20px; font-size: 13.5px; line-height: 1.5; color: #166534;">
                                        <strong style="color: #14532d;">Rekomendasi Keamanan:</strong> Harap jaga kerahasiaan Kode Lisensi Anda dan hindari membagikannya ke publik. Sistem kami melakukan monitoring otomatis guna mencegah penyalahgunaan lisensi oleh pihak yang tidak bertanggung jawab.
                                    </td>
                                </tr>
                            </table>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 32px;">
                                <tr>
                                    <td style="font-size: 14.5px; line-height: 1.6; color: #475569;">
                                        Jika Anda memiliki pertanyaan teknis, jangan ragu untuk menghubungi tim seller resmi <strong>{app_name}</strong> melalui WhatsApp di: 
                                        <a href="https://wa.me/{workspace_owner_wa}" target="_blank" style="color: #0d2870; text-decoration: underline; font-weight: 600;">
                                            wa.me/{workspace_owner_wa}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td height="1" style="background-color: #e2e8f0; line-height: 1px; font-size: 1px;">&nbsp;</td>
                                </tr>
                            </table>

                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 24px;">
                                <tr>
                                    <td>
                                        <p style="font-size: 13.5px; line-height: 1.6; color: #64748b; margin: 0 0 12px 0;">
                                            Email ini dikirimkan secara otomatis oleh sistem administrasi platform untuk memvalidasi pembelian lisensi {app_name} Anda. Kami sangat menyarankan Anda menyimpan pesan transaksional ini sebagai bukti kepemilikan lisensi yang sah.
                                        </p>
                                        <p style="font-size: 13.5px; line-height: 1.6; color: #64748b; margin: 0;">
                                            Privasi dan keamanan data transaksi Anda sepenuhnya dilindungi secara ketat di bawah kepatuhan kebijakan layanan resmi kami.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px 40px; background-color: #fafafa; border-top: 1px solid #f1f5f9; color: #64748b;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="font-size: 13.5px; line-height: 1.6;">
                                        <strong style="color: #334155; font-size: 15px;">ShareLink AI</strong><br />
                                        Pati, Jawa Tengah, Indonesia<br />
                                        <a href="https://sharelink.web.id" style="color: #0d2870; text-decoration: none; font-weight: 600;">https://sharelink.web.id</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 24px; font-size: 13px; line-height: 1.6; color: #94a3b8;">
                                        Apabila Anda ingin membatasi atau menghentikan penerimaan email komunikasi non-transaksional dari sistem kami, silakan akses halaman <a href="{unsubscribe_url}" style="color: #64748b; text-decoration: underline; font-weight: normal;">berhenti berlangganan</a>.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>';

    // Retrieve Member's Autoresponder (or fallback to admin global ticket if not set, or default)
    $wa_tpl = get_user_meta($seller->ID, 'cl_ar_wa', true) ?: get_option('cl_wa_tpl_license', $def_wa_msg);
    $em_tpl = get_user_meta($seller->ID, 'cl_ar_email', true) ?: get_option('cl_email_tpl_license', $def_em_msg);
    
    $acc_link = (isset($found_app) && $found_app && !empty($found_app->canvas_link)) ? $found_app->canvas_link : 'Cek URL akses dari admin.';
    $c_link = '';
    if (isset($found_app) && $found_app && !empty($found_app->custom_slug)) {
        $c_link = rtrim(home_url(), '/') . '/ai/' . $found_app->custom_slug;
    } else {
        $c_link = $acc_link;
    }
    
    $wa_msg = str_replace(['{app_name}', '{license_key}', '{access_link}', '{link_gemini_CANVAS}', '{custom_link}', '{buyer_name}', '{workspace_owner_wa}'], 
                          [$app_name, $key, $acc_link, $acc_link, $c_link, $buyer_name, $member_wa], $wa_tpl);
    
    $email_html = str_replace(['{app_name}', '{license_key}', '{access_link}', '{link_gemini_CANVAS}', '{custom_link}', '{buyer_name}', '{workspace_owner_wa}'], 
                              [$app_name, $key, $acc_link, $acc_link, $c_link, $buyer_name, $member_wa], $em_tpl);
    
    // Blast to Notification Gateways
    $fonnte_token = get_option('cl_fonnte_token');
    if ($fonnte_token && $buyer_phone && $wa_msg) {
        wp_remote_post('https://api.fonnte.com/send', [
            'headers' => ['Authorization' => $fonnte_token],
            'body' => [
                'target' => $buyer_phone,
                'message' => $wa_msg
            ]
        ]);
    }
    
    if ($buyer_email && $email_html) {
        cl_send_email($buyer_email, "Pesanan Anda Berhasil - Sharelink AI", $email_html);
    }
    
    $res = ['valid'=>true, 'message'=>'Webhook dieksekusi dengan sukses.'];
    $log_webhook($seller->ID, 200, $res);
    return new WP_REST_Response($res, 200);
}

// Removed cl_rest_webhook_admin

function cl_rest_generate(WP_REST_Request $req) {
    global $wpdb;
    $lt = $wpdb->prefix . CL_LICS; 
    $p = $req->get_json_params();
    $secret = sanitize_text_field($p['secret_key'] ?? '');
    $qty = intval($p['qty'] ?? 1);
    $aid = intval($p['app_id'] ?? 0);
    $lbl = sanitize_text_field($p['label'] ?? 'API Gen');

    if (!$secret) return new WP_REST_Response(['valid'=>false, 'message'=>'Secret key kosong.'], 401);
    
    $users = get_users(['meta_key' => 'cl_webhook_secret', 'meta_value' => $secret]);
    if (empty($users)) return new WP_REST_Response(['valid'=>false, 'message'=>'Secret key tidak valid.'], 401);
    
    $uid = $users[0]->ID;

    if ($aid > 0) {
        $at = $wpdb->prefix . CL_APPS;
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $at WHERE id=%d", $aid));
        if ($owner != $uid) return new WP_REST_Response(['valid'=>false, 'message'=>'Aplikasi bukan milik Anda.'], 403);
    }

    if ($qty < 1) $qty = 1;
    if ($qty > 100) $qty = 100;
    
    // Quota check
    $used_lics = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $lt WHERE user_id=%d", $uid));
    $quota_limit = (int)get_user_meta($uid, 'cl_quota_limit', true);
    if ($quota_limit < 1) $quota_limit = 100;
    
    if (($used_lics + $qty) > $quota_limit) {
        $avail = max(0, $quota_limit - $used_lics);
        return new WP_REST_Response(['valid'=>false, 'message'=>"Gagal. Sisa kuota hanya $avail."], 403);
    }

    $keys = [];
    for($i=0; $i<$qty; $i++) {
        $key = 'CL' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 12)) . rand(10,99);
        $wpdb->insert($lt, [
            'user_id' => $uid,
            'license_key' => $key,
            'app_id' => $aid,
            'status' => 'active',
            'label' => $lbl,
            'created_at' => current_time('mysql')
        ]);
        $keys[] = $key;
    }

    return new WP_REST_Response(['valid'=>true, 'message'=>"$qty Lisensi berhasil di-generate.", 'keys'=>$keys], 200);
}

function cl_rest_verify(WP_REST_Request $req) {
    global $wpdb;
    $lt = $wpdb->prefix . CL_LICS; 
    $at = $wpdb->prefix . CL_APPS;
    $p = $req->get_json_params();
    $key = strtoupper(sanitize_text_field($p['license'] ?? ''));
    $aid = intval($p['app_id'] ?? 0);
    $fingerprint = sanitize_text_field($p['fingerprint'] ?? '');
    
    if (!$key) return new WP_REST_Response(['valid'=>false, 'message'=>'Kunci kosong.'], 400);
    
    $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM $lt WHERE license_key=%s LIMIT 1", $key));
    
    if (!$l) return new WP_REST_Response(['valid'=>false, 'message'=>'Kunci tidak ditemukan.'], 401);
    if ($l->status !== 'active') return new WP_REST_Response(['valid'=>false, 'message'=>'Lisensi tidak aktif.'], 403);
    if ($l->expires_at && strtotime($l->expires_at) < current_time('timestamp')) return new WP_REST_Response(['valid'=>false, 'message'=>'Lisensi telah kadaluarsa.'], 403);
    if ($l->app_id != 0 && $aid != 0 && $l->app_id != $aid) return new WP_REST_Response(['valid'=>false, 'message'=>'Lisensi tidak valid untuk app ini.'], 403);
    
    // Fingerprint check (Multi-device limits)
    if ($fingerprint) {
        $stored_fp_arr = json_decode($l->device_fingerprint, true);
        if (!is_array($stored_fp_arr)) {
            if (!empty($l->device_fingerprint)) {
                $stored_fp_arr = [$l->device_fingerprint];
            } else {
                $stored_fp_arr = [];
            }
        }
        
        $max_dev = isset($l->max_devices) ? (int)$l->max_devices : 1;
        if ($max_dev < 1) $max_dev = 1;
        
        if (!in_array($fingerprint, $stored_fp_arr)) {
            if (count($stored_fp_arr) < $max_dev) {
                $stored_fp_arr[] = $fingerprint;
                $wpdb->update($lt, ['device_fingerprint' => json_encode($stored_fp_arr)], ['id' => $l->id]);
            } else {
                return new WP_REST_Response(['valid'=>false, 'message'=>"Lisensi limit perangkat ($max_dev) tercapai."], 403);
            }
        }
    }

    $tid = $l->app_id ?: $aid;
    $app_row = $tid ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $at WHERE id=%d", $tid)) : null;
    
    $gk = json_decode($app_row->gk_config, true) ?: [];
    $key_b64 = $gk['app_secret'] ?? '';
    
    // Check App Origin Constraints explicitly to block empty origins if strictly requested
    $req_origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $app_origins = trim($gk['allowed_origins'] ?? '');
    if ($app_origins) {
        $origins = array_map('trim', explode(',', $app_origins));
        if (!in_array('*', $origins)) {
            if (empty($req_origin) || !in_array($req_origin, $origins)) {
                return new WP_REST_Response(['valid'=>false, 'message'=>'Domain (Origin) tidak diizinkan.'], 403);
            }
        }
    }

    $payload = $app_row ? $app_row->payload : '';
    if (!$payload) {
        $payload = '<p style="text-align:center;padding:40px;font-family:sans-serif">App tidak ditemukan. Hubungi admin.</p>';
    } else {
        $payload = trim($payload);
        if (preg_match('/```[a-zA-Z0-9-]*\s+(.*?)```/is', $payload, $matches)) {
            $payload = trim($matches[1]);
        }
    }

    if (get_option('cl_track_usage', 1)) {
        $wpdb->query($wpdb->prepare("UPDATE $lt SET usage_count=usage_count+1, last_used=%s WHERE id=%d", current_time('mysql'), $l->id));
        $wpdb->insert($wpdb->prefix . 'cl_api_logs', [
            'user_id' => $l->user_id,
            'app_id' => $tid,
            'license_id' => $l->id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'origin' => $req_origin,
            'action' => 'verify',
            'created_at' => current_time('mysql')
        ]);
    }
    
    return new WP_REST_Response(['valid'=>true, 'message'=>'Selamat datang!', 'payload'=>$payload], 200);
}

add_action('init', 'cl_cors_early', 1);
function cl_cors_early() {
    $u = urldecode($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($u, 'canvas-app/v1') === false) return;
    if (!headers_sent()) {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? sanitize_text_field($_SERVER['HTTP_ORIGIN']) : '*';
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { status_header(200); header('Content-Length: 0'); exit(); }
}
