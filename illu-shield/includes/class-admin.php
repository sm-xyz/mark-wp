<?php
if (!defined('ABSPATH')) exit;

class Illu_Shield_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_init', [$this, 'handle_form_submission']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        add_action('admin_post_illu_quick_blacklist_get', [$this, 'quick_blacklist_get']);
        add_action('admin_post_illu_quick_unblacklist', [$this, 'quick_unblacklist_get']);
    }

    public function quick_unblacklist_get() {
        if (!current_user_can('manage_options')) return;
        check_admin_referer('illu_quick_unblacklist_nonce');
        
        $ip = isset($_GET['ip']) ? sanitize_text_field($_GET['ip']) : '';
        if (!empty($ip)) {
            $blacklist = get_option('illu_shield_blacklist_ips', []);
            if (($key = array_search($ip, $blacklist)) !== false) {
                unset($blacklist[$key]);
                update_option('illu_shield_blacklist_ips', array_values($blacklist));
            }
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=illu-shield-logs'));
        exit;
    }

    public function quick_blacklist_get() {
        if (!current_user_can('manage_options')) return;
        check_admin_referer('illu_quick_blacklist_nonce');
        
        $ip = isset($_GET['ip']) ? sanitize_text_field($_GET['ip']) : '';
        if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
            $blacklist = get_option('illu_shield_blacklist_ips', []);
            if (!in_array($ip, $blacklist)) {
                $blacklist[] = $ip;
                update_option('illu_shield_blacklist_ips', array_values($blacklist));
            }
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=illu-shield-logs'));
        exit;
    }

    public function add_dashboard_widget() {
        if (current_user_can('manage_options')) {
            wp_add_dashboard_widget(
                'illu_shield_dashboard_widget',
                'Illu Shield - Security Events',
                [$this, 'render_dashboard_widget']
            );
        }
    }

    public function render_dashboard_widget() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'illu_shield_logs';
        $has_logs = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($has_logs) {
            $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC LIMIT 5");
        } else {
            $logs = [];
        }

        if (empty($logs)) {
            echo '<p>No security events logged recently.</p>';
        } else {
            echo '<table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 13px;">';
            echo '<thead style="border-bottom: 1px solid #ddd;"><tr><th style="padding: 8px 4px;">Time</th><th style="padding: 8px 4px;">IP</th><th style="padding: 8px 4px;">Event</th></tr></thead>';
            echo '<tbody>';
            foreach ($logs as $log) {
                echo '<tr style="border-bottom: 1px solid #eee;">';
                echo '<td style="padding: 8px 4px; color: #666;">' . esc_html(substr($log->time, 5, 11)) . '</td>';
                echo '<td style="padding: 8px 4px; font-family: monospace;">' . esc_html($log->ip) . '</td>';
                echo '<td style="padding: 8px 4px;"><span style="background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 11px;">' . esc_html($log->event_type) . '</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        
        echo '<p style="margin-top:10px;"><a href="' . admin_url('admin.php?page=illu-shield-logs') . '" class="button">View All Logs</a></p>';
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'illu-shield') !== false) {
            // Include Tailwind manually using CDN for the settings page specifically
            wp_enqueue_script('tailwindcss', 'https://cdn.tailwindcss.com', [], null, false);
            // Include Lucide
            wp_enqueue_script('lucide', 'https://unpkg.com/lucide@latest', [], null, true);
        }
    }

    public function add_menu_pages() {
        add_menu_page(
            'Illu Shield',
            'Illu Shield',
            'manage_options',
            'illu-shield-logs',
            [$this, 'render_logs_page'],
            'dashicons-shield',
            31 // place near sharelink ai if possible
        );

        add_submenu_page(
            'illu-shield-logs',
            'Analytics & Logs',
            'Analytics & Logs',
            'manage_options',
            'illu-shield-logs',
            [$this, 'render_logs_page']
        );

        add_submenu_page(
            'illu-shield-logs',
            'Settings',
            'Settings',
            'manage_options',
            'illu-shield',
            [$this, 'render_settings_page']
        );
    }

    private function export_logs_csv() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'illu_shield_logs';
        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC", ARRAY_A);
        
        if (empty($logs)) return;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=illu-shield-logs-' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('ID', 'Time', 'IP', 'Event Type', 'Description', 'User ID'));
        
        foreach ($logs as $log) {
            fputcsv($output, $log);
        }
        fclose($output);
        exit;
    }

    private function clear_ip_records($ip) {
        $ip_hash = md5($ip);
        delete_transient('illu_lockout_' . $ip_hash);
        delete_transient('illu_lockout_level_' . $ip_hash);
        delete_transient('illu_violations_' . $ip_hash);
        
        // Remove from blacklist if it's there
        $blacklist = get_option('illu_shield_blacklist_ips', []);
        if (($key = array_search($ip, $blacklist)) !== false) {
            unset($blacklist[$key]);
            update_option('illu_shield_blacklist_ips', array_values($blacklist));
        }
    }

    public function handle_form_submission() {
        if (isset($_POST['illu_shield_manual_blacklist_ip']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_manual_blacklist_action', 'illu_shield_manual_blacklist_nonce');
            $ip_to_add = sanitize_text_field($_POST['blacklist_ip']);
            if (!empty($ip_to_add) && filter_var($ip_to_add, FILTER_VALIDATE_IP)) {
                $blacklist = get_option('illu_shield_blacklist_ips', []);
                if (!in_array($ip_to_add, $blacklist)) {
                    $blacklist[] = $ip_to_add;
                    update_option('illu_shield_blacklist_ips', array_values($blacklist));
                    add_settings_error('illu_shield', 'ip_blacklisted', "IP $ip_to_add berhasil ditambahkan ke blacklist permanent.", 'success');
                }
            }
        }

        if (isset($_POST['illu_shield_manual_wildcard_ip']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_manual_wildcard_action', 'illu_shield_manual_wildcard_nonce');
            $ip_to_add = sanitize_text_field($_POST['wildcard_ip']);
            if (!empty($ip_to_add)) {
                $wildcards = get_option('illu_shield_wildcard_ips', []);
                if (!in_array($ip_to_add, $wildcards)) {
                    $wildcards[] = $ip_to_add;
                    update_option('illu_shield_wildcard_ips', array_values($wildcards));
                    add_settings_error('illu_shield', 'wildcard_blacklisted', "Wildcard Range $ip_to_add berhasil ditambahkan ke blacklist.", 'success');
                }
            }
        }

        if (isset($_POST['illu_shield_unblacklist_ip']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_unblacklist_action', 'illu_shield_unblacklist_nonce');
            $ip_to_remove = sanitize_text_field($_POST['unblacklist_ip']);
            $blacklist = get_option('illu_shield_blacklist_ips', []);
            if (($key = array_search($ip_to_remove, $blacklist)) !== false) {
                unset($blacklist[$key]);
                update_option('illu_shield_blacklist_ips', array_values($blacklist));
                add_settings_error('illu_shield', 'ip_unblacklisted', "IP $ip_to_remove berhasil dihapus dari blacklist.", 'success');
            }
        }

        if (isset($_POST['illu_shield_bulk_unblacklist']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_bulk_unblacklist_action', 'illu_shield_bulk_unblacklist_nonce');
            $ips_to_remove = isset($_POST['bulk_unblacklist_ips']) ? array_map('sanitize_text_field', $_POST['bulk_unblacklist_ips']) : [];
            if (!empty($ips_to_remove)) {
                $blacklist = get_option('illu_shield_blacklist_ips', []);
                $new_blacklist = array_values(array_diff($blacklist, $ips_to_remove));
                update_option('illu_shield_blacklist_ips', $new_blacklist);
                add_settings_error('illu_shield', 'ips_bulk_unblacklisted', count($ips_to_remove) . " IP berhasil dihapus dari blacklist.", 'success');
            }
        }

        if (isset($_POST['illu_shield_unblacklist_wildcard']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_unblacklist_wildcard_action', 'illu_shield_unblacklist_wildcard_nonce');
            $ip_to_remove = sanitize_text_field($_POST['unblacklist_wildcard']);
            $wildcards = get_option('illu_shield_wildcard_ips', []);
            if (($key = array_search($ip_to_remove, $wildcards)) !== false) {
                unset($wildcards[$key]);
                update_option('illu_shield_wildcard_ips', array_values($wildcards));
                add_settings_error('illu_shield', 'wildcard_unblacklisted', "Wildcard Range $ip_to_remove berhasil dihapus dari blacklist.", 'success');
            }
        }

        if (isset($_POST['illu_shield_manual_whitelist_ip']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_manual_whitelist_action', 'illu_shield_manual_whitelist_nonce');
            $ip_to_add = sanitize_text_field($_POST['whitelist_ip']);
            if (!empty($ip_to_add) && filter_var($ip_to_add, FILTER_VALIDATE_IP)) {
                $whitelist = get_option('illu_shield_whitelist_ips', []);
                if (!in_array($ip_to_add, $whitelist)) {
                    $whitelist[] = $ip_to_add;
                    update_option('illu_shield_whitelist_ips', array_values($whitelist));
                    $this->clear_ip_records($ip_to_add);
                    add_settings_error('illu_shield', 'ip_whitelisted', "IP $ip_to_add berhasil ditambahkan ke whitelist.", 'success');
                } else {
                    $this->clear_ip_records($ip_to_add);
                    add_settings_error('illu_shield', 'ip_whitelisted', "IP $ip_to_add sudah ada di whitelist. Data lockout & pelanggaran telah dibersihkan ulang.", 'success');
                }
            }
        }

        if (isset($_POST['illu_shield_unwhitelist_ip']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_unwhitelist_action', 'illu_shield_unwhitelist_nonce');
            $ip_to_remove = sanitize_text_field($_POST['unwhitelist_ip']);
            $whitelist = get_option('illu_shield_whitelist_ips', []);
            if (($key = array_search($ip_to_remove, $whitelist)) !== false) {
                unset($whitelist[$key]);
                update_option('illu_shield_whitelist_ips', array_values($whitelist));
                add_settings_error('illu_shield', 'ip_unwhitelisted', "IP $ip_to_remove berhasil dihapus dari whitelist.", 'success');
            }
        }

        if (isset($_POST['illu_shield_manual_wildcard_whitelist_ip']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_manual_wildcard_whitelist_action', 'illu_shield_manual_wildcard_whitelist_nonce');
            $ip_to_add = sanitize_text_field($_POST['wildcard_whitelist_ip']);
            if (!empty($ip_to_add) && strpos($ip_to_add, '*') !== false) {
                $wildcards = get_option('illu_shield_wildcard_whitelist_ips', []);
                if (!in_array($ip_to_add, $wildcards)) {
                    $wildcards[] = $ip_to_add;
                    update_option('illu_shield_wildcard_whitelist_ips', array_values($wildcards));
                    add_settings_error('illu_shield', 'wildcard_whitelisted', "Wildcard Range $ip_to_add berhasil ditambahkan ke whitelist.", 'success');
                }
            }
        }

        if (isset($_POST['illu_shield_unwhitelist_wildcard']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_unwhitelist_wildcard_action', 'illu_shield_unwhitelist_wildcard_nonce');
            $ip_to_remove = sanitize_text_field($_POST['unwhitelist_wildcard']);
            $wildcards = get_option('illu_shield_wildcard_whitelist_ips', []);
            if (($key = array_search($ip_to_remove, $wildcards)) !== false) {
                unset($wildcards[$key]);
                update_option('illu_shield_wildcard_whitelist_ips', array_values($wildcards));
                add_settings_error('illu_shield', 'wildcard_unwhitelisted', "Wildcard Range $ip_to_remove berhasil dihapus dari whitelist.", 'success');
            }
        }

        if (isset($_POST['illu_shield_bulk_action_logs']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_bulk_logs_action', 'illu_shield_bulk_logs_nonce');
            $action = sanitize_text_field($_POST['bulk_action']);
            $log_ids = isset($_POST['log_ids']) ? array_map('intval', $_POST['log_ids']) : [];
            
            if (!empty($log_ids)) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'illu_shield_logs';
                $ids_placeholder = implode(',', array_fill(0, count($log_ids), '%d'));
                
                if ($action === 'delete') {
                    $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id IN ($ids_placeholder)", ...$log_ids));
                    add_settings_error('illu_shield', 'logs_deleted', count($log_ids) . " log berhasil dihapus.", 'success');
                } elseif ($action === 'blacklist') {
                    $ips = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT ip FROM $table_name WHERE id IN ($ids_placeholder) AND ip != ''", ...$log_ids));
                    if (!empty($ips)) {
                        $blacklist = get_option('illu_shield_blacklist_ips', []);
                        $added = 0;
                        foreach ($ips as $ip) {
                            if (filter_var($ip, FILTER_VALIDATE_IP) && !in_array($ip, $blacklist)) {
                                $blacklist[] = $ip;
                                $added++;
                            }
                        }
                        if ($added > 0) {
                            update_option('illu_shield_blacklist_ips', array_values($blacklist));
                            add_settings_error('illu_shield', 'ips_blacklisted', "$added IP berhasil ditambahkan ke blacklist.", 'success');
                        }
                    }
                }
            }
        }

        if (isset($_POST['illu_shield_export_csv']) && current_user_can('manage_options')) {
            $this->export_logs_csv();
        }

        if (isset($_POST['illu_shield_save_settings']) && current_user_can('manage_options')) {
            check_admin_referer('illu_shield_settings_action', 'illu_shield_nonce');
            
            $settings = [
                'enable_2fa' => isset($_POST['enable_2fa']) ? 'yes' : 'no',
                'require_2fa_admin' => isset($_POST['require_2fa_admin']) ? 'yes' : 'no',
                'enable_firewall' => isset($_POST['enable_firewall']) ? 'yes' : 'no',
                'enable_login_protection' => isset($_POST['enable_login_protection']) ? 'yes' : 'no',
                'disable_xmlrpc' => isset($_POST['disable_xmlrpc']) ? 'yes' : 'no',
                'enable_cache' => isset($_POST['enable_cache']) ? 'yes' : 'no',
                'max_failures' => isset($_POST['max_failures']) ? intval($_POST['max_failures']) : 3,
                'lockout_tier1_minutes' => isset($_POST['lockout_tier1_minutes']) ? intval($_POST['lockout_tier1_minutes']) : 15,
                'lockout_tier2_hours' => isset($_POST['lockout_tier2_hours']) ? intval($_POST['lockout_tier2_hours']) : 1,
                'lockout_tier3_days' => isset($_POST['lockout_tier3_days']) ? intval($_POST['lockout_tier3_days']) : 1,
                'auto_blacklist_days' => isset($_POST['auto_blacklist_days']) ? intval($_POST['auto_blacklist_days']) : 0,
                'auto_clean_logs_days' => isset($_POST['auto_clean_logs_days']) ? intval($_POST['auto_clean_logs_days']) : 30,
                'disable_app_passwords' => isset($_POST['disable_app_passwords']) ? 'yes' : 'no',
                'disable_file_edit' => isset($_POST['disable_file_edit']) ? 'yes' : 'no',
                'disable_file_mods' => isset($_POST['disable_file_mods']) ? 'yes' : 'no',
                'hide_wp_version' => isset($_POST['hide_wp_version']) ? 'yes' : 'no',
                'disable_author_archives' => isset($_POST['disable_author_archives']) ? 'yes' : 'no',
                'protect_rest_api' => isset($_POST['protect_rest_api']) ? 'yes' : 'no',
                'prevent_concurrent_logins' => isset($_POST['prevent_concurrent_logins']) ? 'yes' : 'no',
                'auto_ban_404' => isset($_POST['auto_ban_404']) ? 'yes' : 'no',
                'block_malicious_queries' => isset($_POST['block_malicious_queries']) ? 'yes' : 'no',
                'security_headers' => isset($_POST['security_headers']) ? 'yes' : 'no',
                'email_alert_blacklist' => isset($_POST['email_alert_blacklist']) ? 'yes' : 'no',
                'email_alert_fim' => isset($_POST['email_alert_fim']) ? 'yes' : 'no',
                'email_alert_new_login' => isset($_POST['email_alert_new_login']) ? 'yes' : 'no',
                'fim_webhook_url' => isset($_POST['fim_webhook_url']) ? esc_url_raw($_POST['fim_webhook_url']) : '',
                'custom_login_slug' => isset($_POST['custom_login_slug']) ? sanitize_text_field(trim($_POST['custom_login_slug'])) : '',
                'cloudflare_email' => isset($_POST['cloudflare_email']) ? sanitize_email($_POST['cloudflare_email']) : '',
                'cloudflare_api_key' => isset($_POST['cloudflare_api_key']) && !empty($_POST['cloudflare_api_key']) && $_POST['cloudflare_api_key'] !== '******' ? illu_encrypt_secret(sanitize_text_field($_POST['cloudflare_api_key'])) : (get_option('illu_shield_settings')['cloudflare_api_key'] ?? ''),
                'cloudflare_zone_id' => isset($_POST['cloudflare_zone_id']) ? sanitize_text_field($_POST['cloudflare_zone_id']) : '',
                'blocked_countries' => isset($_POST['blocked_countries']) ? sanitize_text_field($_POST['blocked_countries']) : '',
            ];
            
            update_option('illu_shield_settings', $settings);
            
            add_settings_error('illu_shield', 'settings_updated', 'Pengaturan keamanan berhasil disimpan.', 'success');
        }
    }

    public function render_settings_page() {
        $settings = get_option('illu_shield_settings', []);
        
        // Defaults
        $enable_2fa = $settings['enable_2fa'] ?? 'yes';
        $require_2fa_admin = $settings['require_2fa_admin'] ?? 'no';
        $enable_firewall = $settings['enable_firewall'] ?? 'yes';
        $enable_login_protection = $settings['enable_login_protection'] ?? 'yes';
        $max_failures = $settings['max_failures'] ?? 3;
        $disable_xmlrpc = $settings['disable_xmlrpc'] ?? 'yes';
        $enable_cache = $settings['enable_cache'] ?? 'yes';
        $disable_app_passwords = $settings['disable_app_passwords'] ?? 'yes';
        $disable_file_edit = $settings['disable_file_edit'] ?? 'yes';
        $hide_wp_version = $settings['hide_wp_version'] ?? 'yes';
        $disable_author_archives = $settings['disable_author_archives'] ?? 'yes';
        $protect_rest_api = $settings['protect_rest_api'] ?? 'yes';
        $prevent_concurrent_logins = $settings['prevent_concurrent_logins'] ?? 'yes';
        $auto_ban_404 = $settings['auto_ban_404'] ?? 'yes';
        $block_malicious_queries = $settings['block_malicious_queries'] ?? 'yes';
        $security_headers = $settings['security_headers'] ?? 'yes';
        
        ?>
        <div class="wrap" style="margin: 20px 20px 0 0;">
            <?php settings_errors('illu_shield'); ?>
            
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden" style="max-width: 800px;">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800 flex items-center m-0 p-0 line-height-1">
                            <i data-lucide="shield-check" class="w-6 h-6 mr-2 text-brand text-blue-600"></i> Illu Shield v2.1.0
                        </h2>
                        <p class="text-sm text-slate-500 mt-1 mb-0">Keamanan dan Optimasi Zero-Conflict untuk Sharelink AI</p>
                    </div>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field('illu_shield_settings_action', 'illu_shield_nonce'); ?>
                    
                    <div class="p-6 space-y-8">

                        <!-- Section: Self Audit (FITUR-08) -->
                        <div>
                            <h3 class="text-base font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 flex items-center">
                                <i data-lucide="check-circle" class="w-4 h-4 mr-2 text-green-500"></i> System Audit (Self-Check)
                            </h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <?php
                                $wp_version_ok = version_compare(get_bloginfo('version'), '6.0', '>=');
                                $php_version_ok = version_compare(PHP_VERSION, '7.4', '>=');
                                $debug_ok = !(defined('WP_DEBUG') && WP_DEBUG);
                                $debug_log_exposed = file_exists(WP_CONTENT_DIR . '/debug.log');
                                ?>
                                <div class="bg-white p-3 rounded border <?php echo $wp_version_ok ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'; ?>">
                                    <div class="text-xs text-slate-500">WordPress Core</div>
                                    <div class="font-semibold <?php echo $wp_version_ok ? 'text-green-700' : 'text-red-700'; ?>"><?php echo $wp_version_ok ? 'Up to date' : 'Outdated'; ?></div>
                                </div>
                                <div class="bg-white p-3 rounded border <?php echo $php_version_ok ? 'border-green-200 bg-green-50' : 'border-orange-200 bg-orange-50'; ?>">
                                    <div class="text-xs text-slate-500">PHP Version</div>
                                    <div class="font-semibold <?php echo $php_version_ok ? 'text-green-700' : 'text-orange-700'; ?>"><?php echo PHP_VERSION; ?></div>
                                </div>
                                <div class="bg-white p-3 rounded border <?php echo $debug_ok ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'; ?>">
                                    <div class="text-xs text-slate-500">WP_DEBUG Mode</div>
                                    <div class="font-semibold <?php echo $debug_ok ? 'text-green-700' : 'text-red-700'; ?>"><?php echo $debug_ok ? 'Disabled (Safe)' : 'Enabled (Risk)'; ?></div>
                                </div>
                                <div class="bg-white p-3 rounded border <?php echo !$debug_log_exposed ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'; ?>">
                                    <div class="text-xs text-slate-500">Debug Log Access</div>
                                    <div class="font-semibold <?php echo !$debug_log_exposed ? 'text-green-700' : 'text-red-700'; ?>"><?php echo !$debug_log_exposed ? 'Secure' : 'Exposed!'; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section: Security -->
                        <div>
                            <h3 class="text-base font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 flex items-center">
                                <i data-lucide="lock" class="w-4 h-4 mr-2 text-slate-500"></i> Security & Authentication
                            </h3>
                            <div class="space-y-4 text-sm">
                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="enable_2fa" value="yes" <?php checked($enable_2fa, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Enable Two-Factor Authentication (2FA)</span>
                                        <span class="text-slate-500 text-[13px]">Mengizinkan pengguna untuk mengaktifkan lapisan sekuriti 2FA di profil mereka.</span>
                                    </div>
                                </label>
                                
                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="require_2fa_admin" value="yes" <?php checked($require_2fa_admin, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Force 2FA for Administrators (WIP)</span>
                                        <span class="text-slate-500 text-[13px]">Mewajibkan role Administrator untuk memasang 2FA. (Akan aktif segera)</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="prevent_concurrent_logins" value="yes" <?php checked($prevent_concurrent_logins, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Pencegahan Login Bersamaan</span>
                                        <span class="text-slate-500 text-[13px]">Mencegah 1 akun login di perangkat yang berbeda di waktu bersamaan (session hijacking/sharing protection).</span>
                                    </div>
                                </label>

                                <div class="flex items-start p-2 -ml-2">
                                    <div class="mt-1 mr-3 w-4"></div>
                                    <div class="flex-1">
                                        <span class="font-semibold text-slate-700 block">Custom Login URL (Obfuscation)</span>
                                        <span class="text-slate-500 text-[13px] block mb-2">Ganti wp-login.php dengan URL custom untuk mengecoh bot scanner. Biarkan kosong untuk disable.</span>
                                        <div class="flex items-center">
                                            <span class="text-xs text-slate-500 mr-2"><?php echo site_url(); ?>/?</span>
                                            <input type="text" name="custom_login_slug" value="<?php echo esc_attr($settings['custom_login_slug'] ?? ''); ?>" placeholder="masuk" class="border-slate-300 rounded text-sm text-slate-700 py-1 px-2 focus:ring-blue-500 w-48">
                                        </div>
                                    </div>
                                </div>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="enable_login_protection" value="yes" <?php checked($enable_login_protection, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div class="flex-1">
                                        <span class="font-semibold text-slate-700 block">Login & Brute-Force Protection</span>
                                        <span class="text-slate-500 text-[13px] block mb-2">Melindungi web dari serangan brute-force. Memblokir IP setelah beberapa kali gagal login atau melanggar firewall.</span>
                                        <div class="flex items-center mb-2">
                                            <span class="text-xs text-slate-500 mr-2 w-48">Batas Gagal/Violasi:</span>
                                            <select name="max_failures" class="border-slate-300 rounded text-sm text-slate-700 py-1 pl-2 pr-8 focus:ring-blue-500">
                                                <option value="2" <?php selected($max_failures, 2); ?>>2x Gagal -> Lockout</option>
                                                <option value="3" <?php selected($max_failures, 3); ?>>3x Gagal -> Lockout</option>
                                                <option value="4" <?php selected($max_failures, 4); ?>>4x Gagal -> Lockout</option>
                                                <option value="5" <?php selected($max_failures, 5); ?>>5x Gagal -> Lockout</option>
                                            </select>
                                        </div>
                                        <div class="flex items-center mb-2">
                                            <span class="text-xs text-slate-500 mr-2 w-48">Tier 1 Lockout (Menit):</span>
                                            <input type="number" name="lockout_tier1_minutes" value="<?php echo esc_attr($settings['lockout_tier1_minutes'] ?? 15); ?>" class="border-slate-300 rounded text-sm text-slate-700 py-1 px-2 focus:ring-blue-500 w-24" min="1">
                                        </div>
                                        <div class="flex items-center mb-2">
                                            <span class="text-xs text-slate-500 mr-2 w-48">Tier 2 Lockout (Jam):</span>
                                            <input type="number" name="lockout_tier2_hours" value="<?php echo esc_attr($settings['lockout_tier2_hours'] ?? 1); ?>" class="border-slate-300 rounded text-sm text-slate-700 py-1 px-2 focus:ring-blue-500 w-24" min="1">
                                        </div>
                                        <div class="flex items-center mb-2">
                                            <span class="text-xs text-slate-500 mr-2 w-48">Tier 3 Lockout (Hari):</span>
                                            <input type="number" name="lockout_tier3_days" value="<?php echo esc_attr($settings['lockout_tier3_days'] ?? 1); ?>" class="border-slate-300 rounded text-sm text-slate-700 py-1 px-2 focus:ring-blue-500 w-24" min="1">
                                        </div>
                                        <div class="flex items-center mb-2">
                                            <span class="text-xs text-slate-500 mr-2 w-48">Hapus Blacklist Otomatis (Hari):</span>
                                            <input type="number" name="auto_blacklist_days" value="<?php echo esc_attr($settings['auto_blacklist_days'] ?? 0); ?>" class="border-slate-300 rounded text-sm text-slate-700 py-1 px-2 focus:ring-blue-500 w-24" min="0">
                                            <span class="text-xs text-slate-400 ml-2">(0 = Permanen, IP akan dihapus dari blacklist setelah sekian hari)</span>
                                        </div>
                                        <div class="flex items-center">
                                            <span class="text-xs text-slate-500 mr-2 w-48">Hapus Log Security (Hari):</span>
                                            <input type="number" name="auto_clean_logs_days" value="<?php echo esc_attr($settings['auto_clean_logs_days'] ?? 30); ?>" class="border-slate-300 rounded text-sm text-slate-700 py-1 px-2 focus:ring-blue-500 w-24" min="1">
                                            <span class="text-xs text-slate-400 ml-2">(Umur maksimal data log keamanan yang disimpan di database)</span>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Section: Advanced Hardening -->
                        <div>
                            <h3 class="text-base font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 flex items-center">
                                <i data-lucide="shield-alert" class="w-4 h-4 mr-2 text-slate-500"></i> Advanced Hardening
                            </h3>
                            <div class="space-y-4 text-sm">
                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="disable_app_passwords" value="yes" <?php checked($disable_app_passwords, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Nonaktifkan Application Passwords</span>
                                        <span class="text-slate-500 text-[13px]">Mencegah pembuatan app passwords baru yang sering dimanfaatkan hacker jika admin berhasil diambil alih. (Opsional, uncheck jika Anda menggunakannya)</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="disable_file_edit" value="yes" <?php checked($disable_file_edit, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Nonaktifkan Theme/Plugin Editor</span>
                                        <span class="text-slate-500 text-[13px]">Menyembunyikan menu edit file dari wp-admin untuk mencegah modifikasi kode langsung meskipun admin diretas.</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="disable_file_mods" value="yes" <?php checked(get_option('illu_shield_settings', [])['disable_file_mods'] ?? 'no', 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Nonaktifkan Instalasi/Update Theme/Plugin (Hardening)</span>
                                        <span class="text-slate-500 text-[13px]">Memblokir penambahan atau update plugin dari dashboard (DISALLOW_FILE_MODS). Uncheck saat Anda ingin melakukan update plugin.</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="hide_wp_version" value="yes" <?php checked($hide_wp_version, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Sembunyikan Versi WordPress</span>
                                        <span class="text-slate-500 text-[13px]">Menghapus tag versi WP dari header untuk mencegah targeted exploits.</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="disable_author_archives" value="yes" <?php checked($disable_author_archives, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Cegah Enumerasi User (Author Archives)</span>
                                        <span class="text-slate-500 text-[13px]">Memblokir akses ke /author/ yang biasa dipakai bot untuk menebak username admin.</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="protect_rest_api" value="yes" <?php checked($protect_rest_api, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Proteksi REST API Dasar</span>
                                        <span class="text-slate-500 text-[13px]">Memblokir endpoint enumerasi data (/wp/v2/users) untuk tamu (non-login).</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="security_headers" value="yes" <?php checked($security_headers, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Tambahkan HTTP Security Headers</span>
                                        <span class="text-slate-500 text-[13px]">Menambahkan perlindungan Clickjacking dan XSS (X-Frame-Options, X-Content-Type-Options).</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="auto_ban_404" value="yes" <?php checked($auto_ban_404, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Auto-Ban 404 Scanner (Bot Protection)</span>
                                        <span class="text-slate-500 text-[13px]">Memblokir IP bot yang melakukan brute-force URL pencarian celah atau error 404 berulang kali.</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="block_malicious_queries" value="yes" <?php checked($block_malicious_queries, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Blokir Malicious Query (WAF Ringan)</span>
                                        <span class="text-slate-500 text-[13px]">Memblokir request URL dengan pola SQL Injection dan XSS sederhana seperti <code>UNION SELECT</code> atau <code>&lt;script&gt;</code>.</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Section: Notifications -->
                        <div>
                            <h3 class="text-base font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 flex items-center">
                                <i data-lucide="mail" class="w-4 h-4 mr-2 text-slate-500"></i> Notifikasi Email
                            </h3>
                            <div class="space-y-4 text-sm">
                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="email_alert_blacklist" value="yes" <?php checked(get_option('illu_shield_settings', [])['email_alert_blacklist'] ?? 'yes', 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Permanent Blacklist Alert</span>
                                        <span class="text-slate-500 text-[13px]">Kirim email saat sebuah IP diblokir secara permanen (Tier 4) karena serangan atau brute-force.</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="email_alert_fim" value="yes" <?php checked(get_option('illu_shield_settings', [])['email_alert_fim'] ?? 'yes', 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">File Integrity Monitoring (FIM) Alert</span>
                                        <span class="text-slate-500 text-[13px]">Kirim email peringatan jika file inti WordPress atau wp-config.php terdeteksi dimodifikasi (Indikasi Hack).</span>
                                    </div>
                                </label>

                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="email_alert_new_login" value="yes" <?php checked(get_option('illu_shield_settings', [])['email_alert_new_login'] ?? 'no', 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Login dari IP/Lokasi Baru</span>
                                        <span class="text-slate-500 text-[13px]">Kirim email peringatan saat ada Administrator login dari IP Address yang belum pernah digunakan sebelumnya.</span>
                                    </div>
                                </label>

                                <div class="mt-4 p-4 bg-slate-50 rounded border border-slate-200">
                                    <label class="font-semibold text-slate-700 block mb-1">External FIM Webhook URL (F4-06)</label>
                                    <span class="text-slate-500 text-[13px] block mb-2">URL endpoint untuk menerima snapshot hash file secara external (Format JSON). Kosongkan untuk nonaktif.</span>
                                    <input type="url" name="fim_webhook_url" value="<?php echo esc_url($settings['fim_webhook_url'] ?? ''); ?>" placeholder="https://endpoint.example.com/webhook" class="w-full border-slate-300 rounded focus:ring-blue-500 focus:border-blue-500 text-sm">
                                </div>
                            </div>
                        </div>

                        <!-- Section: Cloudflare Integration -->
                        <div>
                            <h3 class="text-base font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 flex items-center">
                                <i data-lucide="cloud" class="w-4 h-4 mr-2 text-slate-500"></i> Cloudflare Integration (WAF Sync)
                            </h3>
                            <div class="space-y-4 text-sm bg-blue-50 p-4 rounded-xl border border-blue-100">
                                <p class="text-blue-800 mb-2 font-semibold">Sinkronisasi IP Blacklist ke Cloudflare secara otomatis!</p>
                                <p class="text-blue-700 text-xs mb-4">Fitur ini mendukung akun Cloudflare <strong>Free Tier</strong>. IP yang terdeteksi berbahaya akan langsung diblokir di level DNS (WAF Custom Rules) sebelum menyentuh server Anda.</p>
                                
                                <div class="grid grid-cols-1 gap-4">
                                    <div>
                                        <label class="block text-slate-700 font-semibold mb-1">Cloudflare Email</label>
                                        <input type="email" name="cloudflare_email" value="<?php echo esc_attr($settings['cloudflare_email'] ?? ''); ?>" placeholder="admin@domain.com" class="w-full max-w-md border-slate-300 rounded text-sm text-slate-700 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-slate-700 font-semibold mb-1">Global API Key / Token</label>
                                        <input type="password" name="cloudflare_api_key" value="<?php echo !empty($settings['cloudflare_api_key']) ? '******' : ''; ?>" placeholder="Ketik token API di sini..." class="w-full max-w-md border-slate-300 rounded text-sm text-slate-700 focus:ring-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-slate-700 font-semibold mb-1">Zone ID</label>
                                        <input type="text" name="cloudflare_zone_id" value="<?php echo esc_attr($settings['cloudflare_zone_id'] ?? ''); ?>" placeholder="Zone ID dari domain Anda" class="w-full max-w-md border-slate-300 rounded text-sm text-slate-700 focus:ring-blue-500">
                                    </div>
                                    <div class="mt-4">
                                        <label class="block text-slate-700 font-semibold mb-1">Country Blocking (CF-IPCountry)</label>
                                        <input type="text" name="blocked_countries" value="<?php echo esc_attr($settings['blocked_countries'] ?? ''); ?>" placeholder="Cth: RU, CN, IN" class="w-full max-w-md border-slate-300 rounded text-sm text-slate-700 focus:ring-blue-500 mb-1">
                                        <span class="text-xs text-slate-500">Pisahkan dengan koma. Menggunakan Cloudflare Free IP Geolocation untuk memblokir negara tertentu.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Micro-Firewall -->
                        <div>
                            <h3 class="text-base font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 flex items-center">
                                <i data-lucide="siren" class="w-4 h-4 mr-2 text-slate-500"></i> Micro-Firewall
                            </h3>
                            <div class="space-y-4 text-sm">
                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="enable_firewall" value="yes" <?php checked($enable_firewall, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Aktifkan XSS & SQLi Filter</span>
                                        <span class="text-slate-500 text-[13px]">Secara otomatis memblokir url payload yang berisi injeksi. Dirancang *zero-conflict* dengan Sharelink AI.</span>
                                    </div>
                                </label>
                                
                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="disable_xmlrpc" value="yes" <?php checked($disable_xmlrpc, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Disable XML-RPC</span>
                                        <span class="text-slate-500 text-[13px]">Memblokir endpoint <code>xmlrpc.php</code> usang yang dimanfaatkan botnet DDoS.</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Section: Optimization -->
                        <div>
                            <h3 class="text-base font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2 flex items-center">
                                <i data-lucide="zap" class="w-4 h-4 mr-2 text-slate-500"></i> Smart Caching & Optimization
                            </h3>
                            <div class="space-y-4 text-sm">
                                <label class="flex items-start cursor-pointer hover:bg-slate-50 p-2 rounded transition-colors -ml-2">
                                    <input type="checkbox" name="enable_cache" value="yes" <?php checked($enable_cache, 'yes'); ?> class="mt-1 mr-3 border-slate-300 rounded text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <span class="font-semibold text-slate-700 block">Static HTML Caching</span>
                                        <span class="text-slate-500 text-[13px]">Menghidupkan Smart Cache. Akan *auto-bypass* halaman yang diakses member maupun endpoint webhook.</span>
                                    </div>
                                </label>
                                <p class="text-[13px] text-slate-500 mt-2 bg-blue-50 text-blue-800 px-3 py-2 rounded"><strong>Note:</strong> Fitur Heartbeat Control otomastis berjalan untuk menghemat CPU Server.</p>
                            </div>
                        </div>

                    </div>
                    
                    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100">
                        <button type="submit" name="illu_shield_save_settings" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded shadow-sm text-sm transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 inline-flex items-center">
                            <i data-lucide="save" class="w-4 h-4 mr-2"></i> Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            if (typeof lucide !== 'undefined') lucide.createIcons();
        </script>
        <?php
    }

    public function render_logs_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'illu_shield_logs';
        
        $page = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // Ensure table exists (fail gracefully if plugin activated without hook running)
        $has_logs = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if ($has_logs) {
            $total = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
            $logs = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY time DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            ));
        } else {
            $total = 0;
            $logs = [];
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'logs';

        ?>
        <div class="wrap" style="margin: 20px 20px 0 0;">
            <?php settings_errors('illu_shield'); ?>
            
            <h2 class="nav-tab-wrapper border-b-0 mb-4" style="margin-bottom: 20px;">
                <a href="?page=illu-shield-logs&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">Security Analytics & Logs</a>
                <a href="?page=illu-shield-logs&tab=blacklist" class="nav-tab <?php echo $active_tab === 'blacklist' ? 'nav-tab-active' : ''; ?>">IP Blacklist</a>
                <a href="?page=illu-shield-logs&tab=whitelist" class="nav-tab <?php echo $active_tab === 'whitelist' ? 'nav-tab-active' : ''; ?>">IP Whitelist</a>
            </h2>

            <?php if ($active_tab === 'whitelist'): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Exact Whitelist Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-slate-800 m-0">Daftar Exact IP Whitelist</h2>
                </div>
                <div class="p-6">
                    <form method="post" class="mb-6 flex gap-2">
                        <?php wp_nonce_field('illu_shield_manual_whitelist_action', 'illu_shield_manual_whitelist_nonce'); ?>
                        <input type="text" name="whitelist_ip" placeholder="Masukkan IP address VPS/Kantor..." class="flex-1 border-slate-300 rounded focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <button type="submit" name="illu_shield_manual_whitelist_ip" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded text-sm font-semibold transition-colors">Tambah IP</button>
                    </form>

                    <?php
                    $whitelist = get_option('illu_shield_whitelist_ips', []);
                    if (empty($whitelist)) {
                        echo '<p class="text-slate-500 text-sm">Tidak ada IP yang di-whitelist saat ini.</p>';
                    } else {
                        echo '<ul class="space-y-3">';
                        foreach ($whitelist as $w_ip) {
                            echo '<li class="flex items-center justify-between bg-green-50 p-3 rounded border border-green-100">';
                            echo '<span class="font-mono text-green-800 font-semibold">' . esc_html($w_ip) . '</span>';
                            echo '<form method="post" style="margin:0;">';
                            wp_nonce_field('illu_shield_unwhitelist_action', 'illu_shield_unwhitelist_nonce');
                            echo '<input type="hidden" name="unwhitelist_ip" value="' . esc_attr($w_ip) . '">';
                            echo '<button type="submit" name="illu_shield_unwhitelist_ip" class="text-sm bg-white border border-green-200 text-green-600 hover:bg-green-600 hover:text-white px-3 py-1 rounded transition-colors">Hapus</button>';
                            echo '</form>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            </div>

            <!-- Wildcard Whitelist Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-slate-800 m-0">Daftar Wildcard/Range Whitelist</h2>
                </div>
                <div class="p-6">
                    <form method="post" class="mb-6 flex gap-2">
                        <?php wp_nonce_field('illu_shield_manual_wildcard_whitelist_action', 'illu_shield_manual_wildcard_whitelist_nonce'); ?>
                        <input type="text" name="wildcard_whitelist_ip" placeholder="Cth: 173.239.240.*" class="flex-1 border-slate-300 rounded focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <button type="submit" name="illu_shield_manual_wildcard_whitelist_ip" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded text-sm font-semibold transition-colors">Tambah Range</button>
                    </form>

                    <?php
                    $wildcards_wl = get_option('illu_shield_wildcard_whitelist_ips', []);
                    if (empty($wildcards_wl)) {
                        echo '<p class="text-slate-500 text-sm">Tidak ada Range IP yang di-whitelist saat ini.</p>';
                    } else {
                        echo '<ul class="space-y-3">';
                        foreach ($wildcards_wl as $w_ip) {
                            echo '<li class="flex items-center justify-between bg-teal-50 p-3 rounded border border-teal-100">';
                            echo '<span class="font-mono text-teal-800 font-semibold">' . esc_html($w_ip) . '</span>';
                            echo '<form method="post" style="margin:0;">';
                            wp_nonce_field('illu_shield_unwhitelist_wildcard_action', 'illu_shield_unwhitelist_wildcard_nonce');
                            echo '<input type="hidden" name="unwhitelist_wildcard" value="' . esc_attr($w_ip) . '">';
                            echo '<button type="submit" name="illu_shield_unwhitelist_wildcard" class="text-sm bg-white border border-teal-200 text-teal-600 hover:bg-teal-600 hover:text-white px-3 py-1 rounded transition-colors">Hapus</button>';
                            echo '</form>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            </div>
            </div>

            <?php elseif ($active_tab === 'blacklist'): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Permanent Exact Blacklist Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-slate-800 m-0">Daftar Exact IP Blacklist</h2>
                </div>
                <div class="p-0">
                    <div class="p-6 border-b border-slate-100">
                        <form method="post" class="flex gap-2">
                            <?php wp_nonce_field('illu_shield_manual_blacklist_action', 'illu_shield_manual_blacklist_nonce'); ?>
                            <input type="text" name="blacklist_ip" placeholder="Masukkan IP address..." class="flex-1 border-slate-300 rounded focus:ring-blue-500 focus:border-blue-500 text-sm">
                            <button type="submit" name="illu_shield_manual_blacklist_ip" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded text-sm font-semibold transition-colors">Blokir IP</button>
                        </form>
                    </div>

                    <?php
                    $blacklist = get_option('illu_shield_blacklist_ips', []);
                    if (empty($blacklist)) {
                        echo '<div class="p-6"><p class="text-slate-500 text-sm">Tidak ada IP yang di-blacklist secara manual saat ini.</p></div>';
                    } else {
                        ?>
                        <form method="post" id="illu_shield_bulk_blacklist_form">
                            <?php wp_nonce_field('illu_shield_bulk_unblacklist_action', 'illu_shield_bulk_unblacklist_nonce'); ?>
                            <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                <div class="flex gap-2 items-center">
                                    <input type="checkbox" id="cb-select-all-bl" class="rounded border-slate-300">
                                    <label for="cb-select-all-bl" class="text-sm text-slate-600 font-medium cursor-pointer">Pilih Semua</label>
                                </div>
                                <button type="submit" name="illu_shield_bulk_unblacklist" class="text-sm bg-white border border-red-200 text-red-600 hover:bg-red-600 hover:text-white px-3 py-1.5 rounded transition-colors" onclick="return confirm('Hapus IP terpilih dari blacklist?');">Hapus Terpilih</button>
                            </div>
                            <ul class="max-h-96 overflow-y-auto p-4 space-y-2">
                            <?php
                            foreach ($blacklist as $b_ip) {
                                echo '<li class="flex items-center justify-between bg-red-50 px-4 py-2 rounded border border-red-100">';
                                echo '<div class="flex items-center gap-3">';
                                echo '<input type="checkbox" name="bulk_unblacklist_ips[]" value="' . esc_attr($b_ip) . '" class="rounded border-slate-300 cb-select-item-bl">';
                                echo '<span class="font-mono text-red-800 font-semibold text-sm">' . esc_html($b_ip) . '</span>';
                                echo '</div>';
                                echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=illu_quick_unblacklist&ip=' . urlencode($b_ip)), 'illu_quick_unblacklist_nonce')) . '" class="text-xs text-red-600 hover:text-red-800 hover:underline">Hapus</a>';
                                echo '</li>';
                            }
                            ?>
                            </ul>
                        </form>
                        <script>
                            document.getElementById('cb-select-all-bl').addEventListener('change', function(e) {
                                var checkboxes = document.querySelectorAll('.cb-select-item-bl');
                                for (var i = 0; i < checkboxes.length; i++) {
                                    checkboxes[i].checked = e.target.checked;
                                }
                            });
                        </script>
                        <?php
                    }
                    ?>
                </div>
            </div>

            <!-- Wildcard Blacklist Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-slate-800 m-0">Daftar Wildcard/Range IP</h2>
                </div>
                <div class="p-6">
                    <form method="post" class="mb-6 flex gap-2">
                        <?php wp_nonce_field('illu_shield_manual_wildcard_action', 'illu_shield_manual_wildcard_nonce'); ?>
                        <input type="text" name="wildcard_ip" placeholder="Cth: 173.239.240.*" class="flex-1 border-slate-300 rounded focus:ring-blue-500 focus:border-blue-500 text-sm">
                        <button type="submit" name="illu_shield_manual_wildcard_ip" class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-2 rounded text-sm font-semibold transition-colors">Blokir Range</button>
                    </form>

                    <?php
                    $wildcards = get_option('illu_shield_wildcard_ips', []);
                    if (empty($wildcards)) {
                        echo '<p class="text-slate-500 text-sm">Tidak ada Range IP yang di-blacklist saat ini.</p>';
                    } else {
                        echo '<ul class="space-y-3">';
                        foreach ($wildcards as $w_ip) {
                            echo '<li class="flex items-center justify-between bg-orange-50 p-3 rounded border border-orange-100">';
                            echo '<span class="font-mono text-orange-800 font-semibold">' . esc_html($w_ip) . '</span>';
                            echo '<form method="post" style="margin:0;">';
                            wp_nonce_field('illu_shield_unblacklist_wildcard_action', 'illu_shield_unblacklist_wildcard_nonce');
                            echo '<input type="hidden" name="unblacklist_wildcard" value="' . esc_attr($w_ip) . '">';
                            echo '<button type="submit" name="illu_shield_unblacklist_wildcard" class="text-sm bg-white border border-orange-200 text-orange-600 hover:bg-orange-600 hover:text-white px-3 py-1 rounded transition-colors">Hapus</button>';
                            echo '</form>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                    ?>
                </div>
            </div>
            </div>

            <?php else: ?>

                <!-- Analytics Summary -->
                <?php if ($has_logs): 
                    $total_events = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");
                    $unique_ips = $wpdb->get_var("SELECT COUNT(DISTINCT ip) FROM $table_name");
                    $event_types = $wpdb->get_results("SELECT event_type, COUNT(*) as count FROM $table_name GROUP BY event_type ORDER BY count DESC LIMIT 3");
                ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="text-slate-500 text-sm font-semibold mb-1">Total Serangan/Event</div>
                        <div class="text-3xl font-bold text-slate-800"><?php echo number_format($total_events); ?></div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="text-slate-500 text-sm font-semibold mb-1">Unik IP Penyerang</div>
                        <div class="text-3xl font-bold text-slate-800"><?php echo number_format($unique_ips); ?></div>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                        <div class="text-slate-500 text-sm font-semibold mb-1">Top Event</div>
                        <div class="text-sm text-slate-700 space-y-1 mt-2">
                            <?php foreach ($event_types as $evt): 
                                $pct = $total_events > 0 ? round(($evt->count / $total_events) * 100) : 0;
                            ?>
                                <div class="flex justify-between items-center">
                                    <span class="truncate pr-2"><?php echo esc_html($evt->event_type); ?></span>
                                    <span class="font-mono bg-slate-100 px-1 rounded text-xs text-slate-600"><?php echo $pct; ?>% (<?php echo $evt->count; ?>)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- F4-02: Real-time Threat Dashboard -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-6">
                    <h3 class="font-bold text-slate-800 mb-4 flex items-center">
                        <i data-lucide="bar-chart-2" class="w-5 h-5 mr-2 text-blue-600"></i> Real-time Threat Dashboard
                    </h3>
                    <div style="position: relative; height:300px; width:100%">
                        <canvas id="illuThreatChart"></canvas>
                    </div>
                    <?php
                    // Ambil data chart: event 7 hari terakhir dikelompokkan per hari
                    $chart_data_query = $wpdb->get_results("
                        SELECT DATE(time) as date, COUNT(*) as count 
                        FROM $table_name 
                        WHERE time >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                        GROUP BY DATE(time) 
                        ORDER BY DATE(time) ASC
                    ");
                    $dates = [];
                    $counts = [];
                    foreach ($chart_data_query as $row) {
                        $dates[] = date('M d', strtotime($row->date));
                        $counts[] = $row->count;
                    }
                    ?>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            var ctx = document.getElementById('illuThreatChart').getContext('2d');
                            var chart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: <?php echo json_encode($dates); ?>,
                                    datasets: [{
                                        label: 'Blocked Threats (Last 7 Days)',
                                        data: <?php echo json_encode($counts); ?>,
                                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                        borderColor: 'rgba(59, 130, 246, 1)',
                                        borderWidth: 2,
                                        pointBackgroundColor: '#fff',
                                        pointBorderColor: 'rgba(59, 130, 246, 1)',
                                        pointRadius: 4,
                                        fill: true,
                                        tension: 0.3
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: { precision: 0 }
                                        }
                                    }
                                }
                            });
                        });
                    </script>
                </div>
                <?php endif; ?>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800 flex items-center m-0 p-0 line-height-1">
                            <i data-lucide="activity" class="w-6 h-6 mr-2 text-blue-600"></i> Security Analytics & Logs
                        </h2>
                    </div>
                    <div class="flex gap-2">
                        <form method="post" style="margin:0;">
                            <?php wp_nonce_field('illu_shield_bulk_logs_action', 'illu_shield_bulk_logs_nonce'); ?>
                            <input type="hidden" name="illu_shield_export_csv" value="1">
                            <button type="submit" class="font-semibold text-sm bg-green-600 hover:bg-green-700 text-white py-1.5 px-4 rounded inline-flex items-center transition">
                                <i data-lucide="download" class="w-4 h-4 mr-2"></i> Export CSV
                            </button>
                        </form>
                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=illu_clear_cache'), 'illu_clear_cache_nonce'); ?>" class="font-semibold text-sm bg-slate-200 hover:bg-slate-300 text-slate-700 py-1.5 px-4 rounded inline-flex items-center transition">
                            <i data-lucide="trash-2" class="w-4 h-4 mr-2"></i> Clear Smart Cache
                        </a>
                    </div>
                </div>

                <div class="p-0">
                    <form method="post" id="illu_shield_logs_form">
                        <?php wp_nonce_field('illu_shield_bulk_logs_action', 'illu_shield_bulk_logs_nonce'); ?>
                        <div class="p-4 border-b border-slate-100 bg-slate-50 flex gap-2 items-center">
                            <select name="bulk_action" class="border-slate-300 rounded text-sm py-1 pl-2 pr-8">
                                <option value="">Bulk Actions</option>
                                <option value="blacklist">Blacklist Selected IPs</option>
                                <option value="delete">Delete Selected Logs</option>
                            </select>
                            <button type="submit" name="illu_shield_bulk_action_logs" class="bg-white border border-slate-300 text-slate-700 px-3 py-1 rounded text-sm hover:bg-slate-50 transition-colors">Apply</button>
                        </div>
                    <table class="w-full text-left text-sm text-slate-600">
                        <thead class="bg-slate-50 border-b border-slate-200 text-slate-700">
                            <tr>
                                <th class="px-6 py-3 font-semibold w-8"><input type="checkbox" id="cb-select-all" class="rounded border-slate-300"></th>
                                <th class="px-6 py-3 font-semibold w-40">Waktu</th>
                                <th class="px-6 py-3 font-semibold w-40">IP Address</th>
                                <th class="px-6 py-3 font-semibold w-48">Tipe Event</th>
                                <th class="px-6 py-3 font-semibold">Deskripsi</th>
                                <th class="px-6 py-3 font-semibold w-40">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!empty($logs)): ?>
                                <?php $blacklist_cache = get_option('illu_shield_blacklist_ips', []); ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-6 py-3"><input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log->id); ?>" class="rounded border-slate-300 cb-select-item"></td>
                                        <td class="px-6 py-3 whitespace-nowrap text-slate-500 text-xs"><?php echo esc_html($log->time); ?></td>
                                        <td class="px-6 py-3 font-mono text-xs"><?php echo esc_html($log->ip); ?></td>
                                        <td class="px-6 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                                <?php echo esc_html($log->event_type); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-3 text-slate-600"><?php echo esc_html($log->description); ?></td>
                                        <td class="px-6 py-3">
                                            <?php if (!empty($log->ip) && filter_var($log->ip, FILTER_VALIDATE_IP)): ?>
                                                <?php if (in_array($log->ip, $blacklist_cache)): ?>
                                                    <span class="text-xs text-red-600 font-bold"><i data-lucide="ban" class="w-3 h-3 inline"></i> Blacklisted</span>
                                                <?php else: ?>
                                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=illu_quick_blacklist_get&ip=' . urlencode($log->ip)), 'illu_quick_blacklist_nonce')); ?>" class="text-xs bg-red-50 text-red-700 border border-red-200 hover:bg-red-600 hover:text-white px-2 py-1 rounded transition-colors whitespace-nowrap" title="Add to Permanent Blacklist">
                                                        Block IP
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-8 text-center text-slate-500">Belum ada rekaman log keamanan.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </form>
                    <script>
                        document.getElementById('cb-select-all').addEventListener('change', function(e) {
                            var checkboxes = document.querySelectorAll('.cb-select-item');
                            for (var i = 0; i < checkboxes.length; i++) {
                                checkboxes[i].checked = e.target.checked;
                            }
                        });
                    </script>
                </div>
                
                <?php if ($total > $per_page): ?>
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 text-sm flex justify-between items-center">
                    <span class="text-slate-500">Total: <?php echo $total; ?> events</span>
                    <div class="flex gap-2">
                        <?php
                        $num_pages = ceil($total / $per_page);
                        $page_links = paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $num_pages,
                            'current' => $page,
                            'type' => 'plain'
                        ]);
                        if ($page_links) {
                            echo '<div class="tablenav-pages">'.$page_links.'</div>';
                        }
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php endif; // End Tab Content ?>
            
        </div>
        <script>
            if (typeof lucide !== 'undefined') lucide.createIcons();
        </script>
        <?php
    }
}
