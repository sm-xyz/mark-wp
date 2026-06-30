<?php
/**
 * Plugin Name:  Sharelink AI v1.6.9 (Multi-User)
 * Plugin URI:   https://solusimarketing.xyz
 * Description:  Sistem lisensi lengkap bergaya SaaS dengan isolasi Multi-User (Versi 1.6.9 - Revert to isolated app & default var Autoresponder).
 * Version:      1.6.9
 * Author:       Solusi Marketing
 * Text Domain:  sharelink-ai
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('CL_VERSION', '1.6.9');
define('CL_APPS', 'cl_apps');
define('CL_LICS', 'cl_licenses');
define('CL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CL_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once CL_PLUGIN_DIR . 'includes/class-db.php';
require_once CL_PLUGIN_DIR . 'includes/class-api.php';
require_once CL_PLUGIN_DIR . 'includes/class-admin.php';
require_once CL_PLUGIN_DIR . 'includes/class-login.php';

function cl_send_email($to, $subject, $html) {
    $token = get_option('cl_mailketing_token');
    $from_name  = get_option('cl_mailketing_sender', get_bloginfo('name'));
    $from_email = get_option('cl_mailketing_email', get_option('admin_email'));
    
    $mailketing_success = false;
    
    if ($token && !empty($to)) {
        $params = [
            'api_token'  => $token,
            'from_name'  => $from_name,
            'from_email' => $from_email,
            'recipient'  => $to,
            'subject'    => $subject,
            'content'    => $html
        ];
        
        $req = wp_remote_post('https://api.mailketing.co.id/api/v1/send', [
            'body' => $params,
            'timeout' => 15,
            'sslverify' => false
        ]);
        
        if (!is_wp_error($req)) {
            $body = wp_remote_retrieve_body($req);
            $json = json_decode($body, true);
            if (is_array($json) && isset($json['status']) && $json['status'] === 'success') {
                $mailketing_success = true;
                return $req; // Mailketing succeeded
            }
        }
    }
    
    // Fallback to standard wp_mail
    add_filter('wp_mail_content_type', function() { return 'text/html'; });
    
    // Apply custom SMTP settings if they are configured
    $use_smtp = get_option('cl_smtp_enabled');
    if ($use_smtp) {
        add_action('phpmailer_init', 'cl_custom_smtp_setup');
    }
    
    $headers = ["From: $from_name <$from_email>"];
    $mail_result = wp_mail($to, $subject, $html, $headers);
    remove_filter('wp_mail_content_type', function() { return 'text/html'; });
    
    if ($use_smtp) {
        remove_action('phpmailer_init', 'cl_custom_smtp_setup');
    }
    
    return $mail_result;
}

function cl_custom_smtp_setup($phpmailer) {
    $phpmailer->isSMTP();
    $phpmailer->Host = get_option('cl_smtp_host');
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = get_option('cl_smtp_port');
    $phpmailer->Username = get_option('cl_smtp_user');
    $phpmailer->Password = get_option('cl_smtp_pass');
    $phpmailer->SMTPSecure = get_option('cl_smtp_secure', 'tls');
    
    $from_name  = get_option('cl_mailketing_sender', get_bloginfo('name'));
    $from_email = get_option('cl_mailketing_email', get_option('admin_email'));
    $phpmailer->setFrom($from_email, $from_name);
}

// Custom /login url mapping and custom lostpassword email
add_action('init', function() {
    $req = $_SERVER['REQUEST_URI'];
    if (preg_match('/^\/login\/?(\?.*)?$/i', $req)) {
        wp_redirect(site_url('wp-login.php') . (strpos($req, '?') !== false ? substr($req, strpos($req, '?')) : ''));
        exit;
    }
});

// Service Worker for PWA
add_action('parse_request', function($wp) {
    $req = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($req, '/cl-sw.js') === 0) {
        header('Content-Type: application/javascript');
        echo "self.addEventListener('install', function(e) { self.skipWaiting(); });" . PHP_EOL;
        echo "self.addEventListener('activate', function(e) { e.waitUntil(self.clients.claim()); });" . PHP_EOL;
        echo "self.addEventListener('fetch', function(e) { });";
        exit;
    }
}, 1);

add_filter('retrieve_password_message', function($message, $key, $user_login, $user_data) {
    $reset_link = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
    $unsubscribe_url = network_site_url("wp-admin/profile.php");
    
    $html = get_option('cl_em_tpl_reset');
    
    if (empty($html)) {
        $html = "<div style='font-family:sans-serif;padding:20px;max-width:500px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;'>
            <h2 style='color:#003888;margin-top:0;'>Reset Password</h2>
            <p>Seseorang meminta reset password untuk akun Anda (<strong>$user_login</strong>).</p>
            <p>Jika ini adalah Anda, silakan klik tombol di bawah untuk mengubah password Anda:</p>
            <a href='$reset_link' style='display:inline-block;padding:12px 20px;background:#003888;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;margin:10px 0;'>Ubah Password</a>
            <p style='font-size:12px;color:#64748b;margin-top:20px;'>Jika Anda tidak meminta ini, abaikan email ini.</p>
        </div>";
    } else {
        $html = str_replace(
            ['{buyer_name}', '{user_email}', '{reset_link}', '{unsubscribe_url}'],
            [$user_data->display_name, $user_data->user_email, $reset_link, $unsubscribe_url],
            $html
        );
    }
    
    cl_send_email($user_data->user_email, 'Permintaan Reset Password', $html);
    return false; // return false to prevent default wp_mail
}, 99, 4);

// Redirect for /ai/<custom-slug> (without relying on rewrite rules cache)
add_action('parse_request', function($wp) {
    if (isset($wp->request) && strpos($wp->request, 'ai/') === 0) {
        $slug = substr($wp->request, 3);
        $slug = rtrim($slug, '/');
        if (!empty($slug)) {
            global $wpdb;
            $at = $wpdb->prefix . 'cl_apps';
            $app = $wpdb->get_row($wpdb->prepare("SELECT canvas_link FROM $at WHERE custom_slug=%s LIMIT 1", $slug));
            if ($app && !empty($app->canvas_link)) {
                wp_redirect($app->canvas_link, 302);
                exit;
            } else {
                wp_die('Link tidak ditemukan / dihapus.', 'Not Found', ['response' => 404]);
            }
        }
    }
}, 1);

// Force flush rewrite rules once to ensure /ai/ endpoint works
add_action('init', function() {
    if (!get_option('cl_rewrite_rules_flushed_v3')) {
        flush_rewrite_rules();
        update_option('cl_rewrite_rules_flushed_v3', 1);
    }
}, 99);

add_action('admin_init', 'cl_export_members_and_customers_csv');
function cl_export_members_and_customers_csv() {
    global $wpdb;
    
    // Member list export
    if (isset($_GET['cl_export_members']) && $_GET['cl_export_members'] === 'csv') {
        if (!current_user_can('manage_options')) wp_die('No access');
        
        $users = get_users(['role__in' => ['administrator', 'subscriber']]);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=all_members_'.date('Ymd_His').'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Username', 'Name', 'Email', 'WA Contact', 'Quota Limit', 'Created / Registered']);
        foreach($users as $u) {
            $wa = get_user_meta($u->ID, 'cl_wa_number', true);
            $quota = get_user_meta($u->ID, 'cl_quota_limit', true) ?: 100;
            fputcsv($out, [$u->ID, $u->user_login, $u->display_name, $u->user_email, $wa, $quota, $u->user_registered]);
        }
        fclose($out);
        exit;
    }

    // Customer list export
    if (isset($_GET['cl_export_customers']) && $_GET['cl_export_customers'] === 'csv') {
        if (!is_user_logged_in()) wp_die('No access');
        $uid = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
        $ct = $wpdb->prefix . 'cl_customers';
        if ($is_admin) {
            $custs = $wpdb->get_results("SELECT * FROM $ct ORDER BY id DESC");
        } else {
            $custs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $ct WHERE user_id = %d ORDER BY id DESC", $uid));
        }
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=app_users_'.date('Ymd_His').'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Nama Pelanggan', 'Email', 'No Telepon', 'Ditambahkan']);
        foreach($custs as $c) {
            fputcsv($out, [$c->name, $c->email, $c->wa_number, $c->created_at]);
        }
        fclose($out);
        exit;
    }
}

function cl_normalize_wa($phone) {
    if (empty($phone)) {
        return '';
    }
    // Clean non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Convert 08 to 628
    if (strpos($phone, '08') === 0) {
        $phone = '62' . substr($phone, 1);
    }
    return $phone;
}

function cl_is_user_blocked($user_id) {
    if (!$user_id) return false;
    return (bool)get_user_meta($user_id, 'cl_user_blocked', true);
}

function cl_insert_history($user_id, $message, $type = 'info') {
    global $wpdb;
    $ht = $wpdb->prefix . 'cl_history';
    $wpdb->insert($ht, [
        'user_id' => $user_id,
        'message' => $message,
        'type' => $type,
        'created_at' => current_time('mysql')
    ], ['%d', '%s', '%s', '%s']);
}

add_action('admin_init', function() {
    if (!wp_doing_ajax()) {
        global $wpdb;
        $ht = $wpdb->prefix . 'cl_history';
        $wpdb->query("DELETE FROM $ht WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    }
});

add_action('wp_ajax_cl_mark_notif_read', function() {
    if (!current_user_can('read')) wp_die();
    check_ajax_referer('cl_notif_read');
    $uid = get_current_user_id();
    global $wpdb;
    $ht = $wpdb->prefix . 'cl_history';
    $latest = $wpdb->get_var($wpdb->prepare("SELECT id FROM $ht WHERE user_id=%d ORDER BY id DESC LIMIT 1", $uid));
    if ($latest) {
        update_user_meta($uid, 'cl_last_notif_read', $latest);
    }
    wp_send_json_success();
});

add_action('wp_ajax_cl_check_slug', function() {
    if (!current_user_can('read')) wp_send_json(['available' => false]);
    global $wpdb;
    $at = $wpdb->prefix . 'cl_apps';
    $slug = sanitize_title($_POST['slug']);
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $at WHERE custom_slug=%s AND id != %d", $slug, $id));
    wp_send_json(['available' => empty($exists)]);
});

register_activation_hook(__FILE__, 'cl_activate');
add_action('plugins_loaded', 'cl_maybe_upgrade');
