<?php
if (!defined('ABSPATH')) exit;

class Illu_Shield_DB {
    public static function install() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'illu_shield_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            ip varchar(100) NOT NULL,
            event_type varchar(50) NOT NULL,
            description text NOT NULL,
            user_id bigint(20) DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_ip (ip),
            KEY idx_event (event_type),
            KEY idx_time (time)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Schedule log rotation if not scheduled
        if (!wp_next_scheduled('illu_shield_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'illu_shield_daily_cleanup');
        }

        // Default options
        if (get_option('illu_shield_settings') === false) {
            $default_settings = [
                'enable_2fa' => 'yes',
                'require_2fa_admin' => 'yes', // KEL-07: Enforce 2FA for Admin
                'enable_firewall' => 'yes',
                'enable_login_protection' => 'yes',
                'disable_xmlrpc' => 'yes',
                'enable_cache' => 'yes'
            ];
            update_option('illu_shield_settings', $default_settings);
        }
        update_option('illu_shield_db_version', '1.0');
    }

    public static function ensure_table_exists() {
        if (!get_option('illu_shield_db_version')) {
            self::install();
        }
    }

    // KEL-02: Deduplicate get_client_ip
    public static function get_client_ip() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $cf_ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($cf_ip, FILTER_VALIDATE_IP)) {
                return $cf_ip;
            }
        }
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            foreach (array_reverse($ips) as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        if (filter_var($remote, FILTER_VALIDATE_IP)) {
            return $remote;
        }
        
        return '127.0.0.1';
    }

    public static function log($event_type, $description, $user_id = 0) {
        global $wpdb;
        self::ensure_table_exists();
        
        $table_name = $wpdb->prefix . 'illu_shield_logs';
        
        // Use deduplicated get_client_ip
        $ip = self::get_client_ip();

        $wpdb->insert(
            $table_name,
            [
                'time' => current_time('mysql'),
                'ip' => sanitize_text_field(trim($ip)),
                'event_type' => sanitize_text_field($event_type),
                'description' => sanitize_textarea_field($description),
                'user_id' => intval($user_id)
            ]
        );
    }

    public static function cleanup_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'illu_shield_logs';
        
        $settings = get_option('illu_shield_settings', []);
        $days_to_keep = isset($settings['auto_clean_logs_days']) ? intval($settings['auto_clean_logs_days']) : 30;
        
        if ($days_to_keep > 0) {
            $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE time < DATE_SUB(NOW(), INTERVAL %d DAY)", $days_to_keep));
        }
    }

    public static function send_weekly_report() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'illu_shield_logs';
        $has_logs = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        $total_events = 0;
        $brute_force = 0;
        $scanner = 0;
        $firewall = 0;
        $spam = 0;
        
        if ($has_logs) {
            $total_events = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE time > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $brute_force = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE event_type LIKE '%Brute Force%' AND time > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $scanner = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE (event_type LIKE '%Scanner%' OR event_type LIKE '%Bot%') AND time > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $firewall = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE event_type LIKE '%Firewall%' AND time > DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $spam = (int) $wpdb->get_var("SELECT COUNT(id) FROM $table_name WHERE event_type LIKE '%Spam%' AND time > DATE_SUB(NOW(), INTERVAL 7 DAY)");
        }
        
        $blocked_ips = count(get_option('illu_shield_blacklist_ips', []));
        
        $p_bf = $total_events > 0 ? round(($brute_force / $total_events) * 100) : 0;
        $p_sc = $total_events > 0 ? round(($scanner / $total_events) * 100) : 0;
        $p_fw = $total_events > 0 ? round(($firewall / $total_events) * 100) : 0;
        $p_sp = $total_events > 0 ? round(($spam / $total_events) * 100) : 0;

        $message = "📊 LAPORAN MINGGUAN ILLU SHIELD\n";
        $message .= "================================\n";
        $message .= "Periode: " . date('d M', strtotime('-7 days')) . " - " . date('d M Y') . "\n\n";
        
        $message .= "🚨 SERANGAN TERBLOKIR: " . number_format($total_events) . "\n";
        $message .= "   Brute Force:      " . number_format($brute_force) . " ($p_bf%)\n";
        $message .= "   Scanner/Bot:      " . number_format($scanner) . " ($p_sc%)\n";
        $message .= "   Firewall Block:   " . number_format($firewall) . " ($p_fw%)\n";
        $message .= "   Spam Comment:     " . number_format($spam) . " ($p_sp%)\n\n";
        
        $message .= "🔴 TOTAL IP DI BLACKLIST PERMANEN: " . number_format($blocked_ips) . "\n\n";
        $message .= "✅ STATUS SISTEM: Semua proteksi aktif\n\n";
        
        // F4-04: Basic vulnerability / out-of-date check
        $core_updates = get_site_transient('update_core');
        if (isset($core_updates->updates) && !empty($core_updates->updates) && $core_updates->updates[0]->response === 'upgrade') {
            $message .= "⚠️ PERINGATAN: WordPress Core versi baru tersedia. Segera perbarui untuk menghindari celah keamanan!\n";
        }
        
        $plugin_updates = get_site_transient('update_plugins');
        if (isset($plugin_updates->response) && count($plugin_updates->response) > 0) {
            $message .= "⚠️ PERINGATAN: Terdapat " . count($plugin_updates->response) . " plugin yang memiliki pembaruan. Plugin usang sering menjadi vektor serangan.\n";
        }
        
        wp_mail(get_option('admin_email'), '[Illu Shield] Laporan Keamanan Mingguan', $message);
    }
}

add_action('illu_shield_daily_cleanup', ['Illu_Shield_DB', 'cleanup_old_logs']);