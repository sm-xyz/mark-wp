<?php
/**
 * Plugin Name: Illu Shield (Security & Optimasi)
 * Plugin URI:  https://solusimarketing.xyz
 * Description: Plugin keamanan dan optimasi khusus ekosistem Sharelink AI. Includes 2FA, Rate Limiting, Micro-Firewall, Login Obfuscation, FIM, and Cloudflare WAF Sync.
 * Version:     2.1.0
 * Author:      Solusi Marketing
 * Text Domain: illu-shield
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('ILLU_SHIELD_VERSION', '2.1.0');
define('ILLU_SHIELD_DIR', plugin_dir_path(__FILE__));
define('ILLU_SHIELD_URL', plugin_dir_url(__FILE__));

// Load modules
require_once ILLU_SHIELD_DIR . 'includes/class-totp.php';
require_once ILLU_SHIELD_DIR . 'includes/class-db.php';
require_once ILLU_SHIELD_DIR . 'includes/class-2fa.php';
require_once ILLU_SHIELD_DIR . 'includes/class-security.php';
require_once ILLU_SHIELD_DIR . 'includes/class-cache.php';
require_once ILLU_SHIELD_DIR . 'includes/class-admin.php';
require_once ILLU_SHIELD_DIR . 'includes/class-cloudflare.php';

// Utility for encryption
function illu_encrypt_secret($value) {
    if (empty($value)) return $value;
    $key = wp_hash('illu_secret_key');
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
    $tag = '';
    $encrypted = openssl_encrypt($value, 'aes-256-gcm', $key, 0, $iv, $tag);
    return base64_encode($encrypted . '::' . $iv . '::' . $tag);
}

function illu_decrypt_secret($value) {
    if (empty($value)) return $value;
    $decoded = base64_decode($value);
    if (strpos($decoded, '::') === false) return $value; // Might be old unencrypted plaintext
    $parts = explode('::', $decoded, 3);
    
    $key = wp_hash('illu_secret_key');
    if (count($parts) === 3) {
        list($encrypted, $iv, $tag) = $parts;
        $decrypted = openssl_decrypt($encrypted, 'aes-256-gcm', $key, 0, $iv, $tag);
    } elseif (count($parts) === 2) {
        // Fallback for older CBC encryption
        list($encrypted, $iv) = $parts;
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    } else {
        return $value;
    }
    
    return $decrypted !== false ? $decrypted : $value;
}

function illu_shield_activate() {
    Illu_Shield_DB::install();
    Illu_Shield_Security::snapshot_files();
    if (!wp_next_scheduled('illu_shield_daily_fim')) {
        wp_schedule_event(time(), 'daily', 'illu_shield_daily_fim');
    }
    if (!wp_next_scheduled('illu_shield_weekly_report')) {
        wp_schedule_event(time(), 'weekly', 'illu_shield_weekly_report'); // FITUR-05
    }
}
register_activation_hook(__FILE__, 'illu_shield_activate');

register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('illu_shield_daily_fim');
    wp_clear_scheduled_hook('illu_shield_weekly_report');
});

// Initialize plugins
function illu_shield_init() {
    Illu_Shield_DB::ensure_table_exists();
    
    // Add Cron hooks
    add_action('illu_shield_daily_fim', ['Illu_Shield_Security', 'verify_files']);
    add_action('illu_shield_weekly_report', ['Illu_Shield_DB', 'send_weekly_report']); // FITUR-05
    
    new Illu_Shield_2FA();
    new Illu_Shield_Security();
    new Illu_Shield_Cache();
    new Illu_Shield_Cloudflare();
    if (is_admin()) {
        new Illu_Shield_Admin();
    }
}
add_action('plugins_loaded', 'illu_shield_init');
