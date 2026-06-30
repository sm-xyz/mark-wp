<?php
if (!defined('ABSPATH')) exit;

class Illu_Shield_Security {

    private $max_login_attempts;
    private $lockout_duration = 900; // 15 minutes
    private $settings;

    public function __construct() {
        $this->settings = get_option('illu_shield_settings', []);
        $this->max_login_attempts = intval($this->settings['max_failures'] ?? 3);
        if ($this->max_login_attempts < 1) $this->max_login_attempts = 3;

        // Micro-Firewall (Anti-Injection & XSS Protection)
        if (($this->settings['enable_firewall'] ?? 'yes') === 'yes') {
            add_action('init', [$this, 'micro_firewall'], 1);
            add_action('init', [$this, 'block_bad_bots'], 1);
            add_filter('preprocess_comment', [$this, 'anti_spam_comment'], 1);
        }

        // Global IP Block
        add_action('init', [$this, 'block_blacklisted_ips'], 0);

        // Login Protection & Rate Limiting
        if (($this->settings['enable_login_protection'] ?? 'yes') === 'yes') {
            add_filter('authenticate', [$this, 'check_login_attempts'], 10, 3);
            add_action('wp_login_failed', [$this, 'log_failed_attempt']);
            add_action('wp_login', [$this, 'clear_failed_attempts'], 10, 2);
            
            // Honeypot
            add_action('login_form', [$this, 'add_login_honeypot']);
            add_filter('authenticate', [$this, 'check_login_honeypot'], 5, 3);
        }

        // Upload Folder Lockdown
        add_action('admin_init', [$this, 'secure_upload_folder']);
        add_action('admin_init', [$this, 'protect_wp_config']);

        // Admin Audit Logs
        add_action('updated_option', [$this, 'audit_option_updates'], 10, 3);
        add_action('profile_update', [$this, 'audit_profile_update'], 10, 2);
        add_action('delete_user', [$this, 'audit_delete_user']);
        
        // FIX-05: Plugin & User Events
        add_action('activated_plugin', [$this, 'audit_plugin_activated'], 10, 2);
        add_action('deactivated_plugin', [$this, 'audit_plugin_deactivated'], 10, 2);
        add_action('user_register', [$this, 'audit_new_user']);
        add_action('set_user_role', [$this, 'audit_role_change'], 10, 3);

        // Disable XML-RPC
        if (($this->settings['disable_xmlrpc'] ?? 'yes') === 'yes') {
            add_filter('xmlrpc_enabled', '__return_false');
            add_filter('wp_headers', [$this, 'remove_x_pingback']);
            add_action('init', [$this, 'block_xmlrpc_requests'], 1);
        }

        // Advanced Hardening
        if (($this->settings['disable_app_passwords'] ?? 'yes') === 'yes') {
            add_filter('wp_is_application_passwords_available', '__return_false');
        }
        if (($this->settings['disable_file_edit'] ?? 'yes') === 'yes') {
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }
        }
        if (($this->settings['disable_file_mods'] ?? 'no') === 'yes') {
            if (!defined('DISALLOW_FILE_MODS')) {
                define('DISALLOW_FILE_MODS', true);
            }
        }
        if (($this->settings['hide_wp_version'] ?? 'yes') === 'yes') {
            remove_action('wp_head', 'wp_generator');
            add_filter('the_generator', '__return_empty_string');
        }
        if (($this->settings['disable_author_archives'] ?? 'yes') === 'yes') {
            add_action('template_redirect', [$this, 'disable_author_archives']);
        }
        if (($this->settings['protect_rest_api'] ?? 'yes') === 'yes') {
            add_filter('rest_authentication_errors', [$this, 'restrict_rest_api_global']);
        }
        if (($this->settings['prevent_concurrent_logins'] ?? 'yes') === 'yes') {
            add_filter('authenticate', [$this, 'check_concurrent_logins'], 30, 3);
            add_action('wp_login', [$this, 'set_concurrent_login_token'], 10, 2);
            add_action('clear_auth_cookie', [$this, 'clear_concurrent_login_token']);
        }
        if (($this->settings['auto_ban_404'] ?? 'yes') === 'yes') {
            add_action('template_redirect', [$this, 'track_404_requests']);
        }
        if (($this->settings['block_malicious_queries'] ?? 'yes') === 'yes') {
            add_action('init', [$this, 'block_malicious_queries'], 1);
        }
        if (($this->settings['security_headers'] ?? 'yes') === 'yes') {
            add_action('send_headers', [$this, 'add_security_headers']);
            add_filter('script_loader_tag', [$this, 'inject_csp_nonce'], 10, 3);
            add_filter('wp_inline_script_attributes', [$this, 'inject_inline_csp_nonce']);
        }

        // URL Honeypot & Session Idle Timeout
        add_action('init', [$this, 'check_url_honeypot'], 1);
        add_action('init', [$this, 'check_session_idle_timeout']);
        add_action('wp_login', [$this, 'reset_last_activity'], 10, 2);
        
        // Custom Login URL
        if (!empty($this->settings['custom_login_slug'])) {
            add_action('init', [$this, 'custom_login_obfuscation'], 1);
            add_filter('site_url', [$this, 'filter_login_url'], 10, 3);
            add_filter('network_site_url', [$this, 'filter_login_url'], 10, 3);
            add_filter('wp_redirect', [$this, 'filter_wp_redirect'], 10, 2);
        }

        // F3-05: REST API rate limiting & CSP Report
        add_action('rest_api_init', [$this, 'setup_rest_endpoints']);

        // FITUR-10: Realtime FIM (Webshell Detection)
        add_action('init', [$this, 'start_realtime_fim'], 1);

        // FITUR-02: Country Blocking (Cloudflare Free Tier)
        add_action('init', [$this, 'check_country_block'], 0);

        // FITUR-07: security.txt generator
        add_action('init', [$this, 'serve_security_txt']);
    }

    public function filter_login_url($url, $path, $scheme) {
        if (strpos($url, 'wp-login.php') !== false) {
            $slug = trim($this->settings['custom_login_slug'], '/');
            $url = str_replace('wp-login.php', $slug, $url);
        }
        return $url;
    }

    public function filter_wp_redirect($location, $status) {
        if (strpos($location, 'wp-login.php') !== false) {
            $slug = trim($this->settings['custom_login_slug'], '/');
            $location = str_replace('wp-login.php', $slug, $location);
        }
        return $location;
    }

    public function custom_login_obfuscation() {
        $slug = trim($this->settings['custom_login_slug'] ?? '', '/');
        if (empty($slug)) return;

        $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
        
        // Accessing custom slug
        if ($path === $slug) {
            $GLOBALS['illu_custom_login_accessed'] = true;
            $_SERVER['REQUEST_URI'] = '/' . $slug; // ensure proper uri for wp-login form
            require_once ABSPATH . 'wp-login.php';
            exit;
        }
        
        // Block direct wp-login.php access
        if ($path === 'wp-login.php' && !isset($GLOBALS['illu_custom_login_accessed'])) {
            // Exceptions
            $action = $_GET['action'] ?? '';
            if (in_array($action, ['logout', 'postpass'])) {
                return; 
            }
            // For sharelink API if any, allow pass? Actually API doesn't use wp-login.php.
            // Also allow if it's an AJAX request just in case
            if (wp_doing_ajax()) return;

            // Trigger violation and 404
            $this->register_violation('Scanner Detected', 'Blocked direct access to wp-login.php (Obfuscated)');
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            require get_query_template('404');
            exit;
        }
    }

    public function serve_security_txt() {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($path === '/.well-known/security.txt' || $path === '/security.txt') {
            header('Content-Type: text/plain');
            $admin_email = get_option('admin_email');
            $url = home_url();
            echo "Contact: mailto:{$admin_email}\n";
            echo "Expires: " . gmdate('Y-m-d\TH:i:s\Z', strtotime('+1 year')) . "\n";
            echo "Preferred-Languages: id, en\n";
            echo "Canonical: {$url}/.well-known/security.txt\n";
            exit;
        }
    }

    public function setup_rest_endpoints() {
        $this->rate_limit_rest_api();
        
        // F4-07: CSP Violation Endpoint
        register_rest_route('illu-shield/v1', '/csp-report', [
            'methods' => 'POST',
            'callback' => [$this, 'log_csp_violation'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function set_concurrent_login_token($user_login, $user) {
        if (!$user instanceof WP_User) return;
        $token = wp_generate_password(20, false);
        update_user_meta($user->ID, 'illu_concurrent_token', $token);
        setcookie('illu_concurrent_token', $token, time() + 14 * DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }

    public function clear_concurrent_login_token() {
        if (isset($_COOKIE['illu_concurrent_token'])) {
            setcookie('illu_concurrent_token', ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        }
    }

    public function check_concurrent_logins($user, $username, $password) {
        if (is_a($user, 'WP_User')) {
            $saved_token = get_user_meta($user->ID, 'illu_concurrent_token', true);
            $cookie_token = $_COOKIE['illu_concurrent_token'] ?? '';
            // If there's already an active session somewhere else and we don't have the token
            if (!empty($saved_token) && $saved_token !== $cookie_token) {
                // Determine if we should block new login or allow and invalidate old.
                // Typical concurrent block: reject new login. 
                // But what if old session is lost? Let's just invalidate the old session by setting a new token.
                // Wait, the prompt says "prevent concurrent logins (session hijacking protection)".
                // Actually WordPress doesn't have an easy "reject new login" without confusing the user.
                // The standard way is to destroy other sessions!
                $manager = WP_Session_Tokens::get_instance($user->ID);
                $manager->destroy_all();
                Illu_Shield_DB::log('Concurrent Login Prevented', "User {$user->user_login} logged in from a new location. Previous sessions were terminated.");
            }
        }
        return $user;
    }

    public function track_404_requests() {
        if (is_404()) {
            $ip = $this->get_client_ip();
            $key = 'illu_404_' . md5($ip);
            $count = (int)get_transient($key) + 1;
            set_transient($key, $count, 60); // 1 minute window
            
            if ($count >= 20) {
                $this->register_violation('Scanner Detected', 'Terlalu banyak request 404 (20+ dalam 1 menit). Indikasi bot scanner.');
            }
        }
    }

    public function block_malicious_queries() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $bad_patterns = [
            '/<script.*?>/i',
            '/UNION.*?SELECT/i',
            '/CONCAT\(/i',
            '/base64_decode\(/i',
            '/eval\(/i',
            '/etc\/passwd/i',
            '/wp-config\.php/i'
        ];
        foreach ($bad_patterns as $pattern) {
            if (preg_match($pattern, urldecode($uri))) {
                $this->register_violation('Malicious Query', "Mendeteksi payload injeksi pada URL: " . esc_html($uri));
                wp_die('Request diblokir oleh Illu Shield WAF.', 'Akses Ditolak', ['response' => 403]);
            }
        }
    }

    public function log_csp_violation(WP_REST_Request $request) {
        $body = $request->get_body();
        // FIX-06: Validasi format payload sebelum log
        if (strlen($body) > 2000) {
            return new WP_REST_Response(['status' => 'payload_too_large'], 400);
        }
        
        $data = json_decode($body, true);
        if ($data && isset($data['csp-report']) && is_array($data['csp-report'])) {
            $report = $data['csp-report'];
            $blocked_uri = sanitize_text_field($report['blocked-uri'] ?? 'unknown');
            $violated_directive = sanitize_text_field($report['violated-directive'] ?? 'unknown');
            
            // FIX: Gunakan register_violation agar dihitung dalam akumulasi lockout 3x
            // dan tidak logging jika IP sudah masuk blacklist
            $is_locked = $this->register_violation('CSP Violation', "Blocked URI: {$blocked_uri}, Directive: {$violated_directive}");
            
            if ($is_locked) {
                return new WP_REST_Response(['status' => 'locked'], 403);
            }
            
            return new WP_REST_Response(['status' => 'logged'], 200);
        }
        return new WP_REST_Response(['status' => 'invalid_format'], 400);
    }

    public function rate_limit_rest_api() {
        $raw_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($raw_uri, '/wp-json/sharelink/') !== false || strpos($raw_uri, 'rest_route=/sharelink/') !== false || 
            strpos($raw_uri, '/wp-json/canvas-app/') !== false || strpos($raw_uri, 'rest_route=/canvas-app/') !== false ||
            strpos($raw_uri, '/webhook') !== false || strpos($raw_uri, '/wp-json/webhook/') !== false) {
            return;
        }

        if (current_user_can('edit_posts')) return; // Allow internal users
        
        $ip = Illu_Shield_DB::get_client_ip();
        $option_name = 'illu_rest_rl_' . md5($ip);
        
        $requests = intval(get_transient($option_name)) + 1;
        
        if ($requests === 1) {
            set_transient($option_name, $requests, MINUTE_IN_SECONDS);
        } else {
            // FIX-03: REST Rate Limit Fixed Window
            $ttl = get_option('_transient_timeout_' . $option_name) - time();
            if ($ttl > 0) {
                set_transient($option_name, $requests, $ttl);
            }
            if ($requests > 60) {
                header('HTTP/1.1 429 Too Many Requests');
                header('Retry-After: 60');
                $this->register_violation('REST API Rate Limit', "Blocked REST API request. Over 60 requests/min.");
                die(json_encode(['code' => 'rest_rate_limited', 'message' => 'Too many requests.', 'data' => ['status' => 429]]));
            }
        }
    }

    public function check_country_block() {
        // Skip filtering for webhooks
        $raw_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($raw_uri, '/wp-json/sharelink/') !== false || strpos($raw_uri, 'rest_route=/sharelink/') !== false || 
            strpos($raw_uri, '/wp-json/canvas-app/') !== false || strpos($raw_uri, 'rest_route=/canvas-app/') !== false ||
            strpos($raw_uri, '/webhook') !== false || strpos($raw_uri, '/wp-json/webhook/') !== false) {
            return;
        }

        $blocked_countries_raw = $this->settings['blocked_countries'] ?? '';
        if (empty($blocked_countries_raw)) return;

        $blocked_countries = array_map('trim', explode(',', strtoupper($blocked_countries_raw)));
        $visitor_country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';

        if (!empty($visitor_country) && in_array($visitor_country, $blocked_countries)) {
            $this->register_violation('Country Blocked', "Blocked request from country: $visitor_country");
            header('HTTP/1.1 403 Forbidden');
            die('Access Denied: Your country is not allowed to access this site.');
        }
    }

    public function disable_author_archives() {
        if (is_author()) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            nocache_headers();
            $this->register_violation('Author Archive Access', 'Blocked user enumeration attempt via author archive.');
        }
    }

    public function restrict_rest_api_global($result) {
        if (!empty($result)) return $result;
        if (!is_user_logged_in()) {
            $path = $_SERVER['REQUEST_URI'] ?? '';
            // Allow essential unauthenticated endpoints
            if (strpos($path, '/wp-json/sharelink/') !== false || 
                strpos($path, 'rest_route=/sharelink/') !== false ||
                strpos($path, '/wp-json/webhook/') !== false || 
                strpos($path, '/wp-json/canvas-app/') !== false || 
                strpos($path, 'rest_route=/canvas-app/') !== false ||
                strpos($path, '/wp-json/wp/v2/posts') !== false || 
                strpos($path, '/wp-json/wp/v2/pages') !== false) {
                return $result;
            }
            $this->register_violation('REST API Blocked', 'Unauthenticated access to protected REST API endpoint.');
            return new WP_Error('rest_not_logged_in', 'Authentication required. Endpoint protected by Illu Shield.', ['status' => 401]);
        }
        return $result;
    }

    public function add_login_honeypot() {
        echo '<p style="display:none!important" aria-hidden="true">
            <input type="text" name="illu_honeypot_field" value="" tabindex="-1" autocomplete="off">
        </p>';
    }

    public function check_login_honeypot($user, $username, $password) {
        if (!empty($_POST['illu_honeypot_field'])) {
            $this->register_violation('Bot Detected (Honeypot)', "Login honeypot triggered by user: $username");
            return new WP_Error('honeypot_triggered', 'Bot detected. Access denied.');
        }
        return $user;
    }

    public function check_url_honeypot() {
        // KEL-05: Expanded honeypot paths
        $honeypot_paths = [
            '/.env', '/.git/HEAD', '/phpinfo.php', '/wp-config.php.bak',
            '/xmlrpc.php', '/.htaccess', '/wp-content/debug.log', 
            '/backup.zip', '/dump.sql', '/wp-admin/install.php', 
            '/server-status', '/server-info'
        ];
        $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (in_array($current_path, $honeypot_paths)) {
            // Trigger a violation for 3 times the limit so they get banned instantly
            for ($i = 0; $i < $this->max_login_attempts; $i++) {
                $this->register_violation('Scanner Detected', "Accessed honeypot path: $current_path");
            }
            header('HTTP/1.1 403 Forbidden');
            die('Access Denied');
        }
    }

    public function check_session_idle_timeout() {
        if (is_user_logged_in()) {
            // CEL-05: Ignore background AJAX/Heartbeat requests to prevent keeping session alive indefinitely
            if (wp_doing_ajax() && isset($_POST['action']) && in_array($_POST['action'], ['heartbeat'])) {
                return;
            }
            
            $uid = get_current_user_id();
            $last_activity = get_user_meta($uid, 'illu_last_activity', true);
            $timeout = 60 * MINUTE_IN_SECONDS; // 1 hour idle limit
            
            if ($last_activity && (time() - $last_activity) > $timeout) {
                wp_logout();
                wp_redirect(wp_login_url() . '?timeout=1');
                exit;
            }
            update_user_meta($uid, 'illu_last_activity', time());
        }
    }

    public function reset_last_activity($user_login, $user) {
        update_user_meta($user->ID, 'illu_last_activity', time());
    }

    public static function snapshot_files() {
        $critical_files = array_merge(
            glob(ABSPATH . 'wp-includes/*.php') ?: [],
            [ABSPATH . 'wp-login.php', ABSPATH . 'wp-config.php', ABSPATH . 'wp-settings.php', ABSPATH . 'index.php'],
            file_exists(ABSPATH . '.htaccess') ? [ABSPATH . '.htaccess'] : [],
            glob(ABSPATH . 'wp-admin/*.php') ?: [],
            glob(WP_CONTENT_DIR . '/mu-plugins/*.php') ?: [],
            glob(get_template_directory() . '/*.php') ?: [],
            [WP_PLUGIN_DIR . '/illu-shield/illu-shield.php']
        );
        
        // F3-03: FIM untuk semua plugin
        $plugin_main_files = glob(WP_PLUGIN_DIR . '/*/') ?: [];
        foreach ($plugin_main_files as $plugin_dir) {
            $plugin_php = glob($plugin_dir . '*.php') ?: [];
            $critical_files = array_merge($critical_files, array_slice($plugin_php, 0, 3));
        }

        $hashes = [];
        foreach ($critical_files as $file) {
            if (file_exists($file) && is_file($file)) {
                $hashes[$file] = hash_file('sha256', $file);
            }
        }
        update_option('illu_shield_file_hashes', $hashes);
        
        // F4-06: External FIM Hash Storage via Webhook
        $settings = get_option('illu_shield_settings', []);
        if (!empty($settings['fim_webhook_url'])) {
            wp_remote_post(esc_url_raw($settings['fim_webhook_url']), [
                'body' => json_encode(['timestamp' => time(), 'hashes' => $hashes]),
                'headers' => ['Content-Type' => 'application/json'],
                'blocking' => false
            ]);
        }
    }

    public static function verify_files() {
        $settings = get_option('illu_shield_settings', []);
        if (($settings['email_alert_fim'] ?? 'yes') !== 'yes') {
            return; // FIM alert disabled
        }
        
        $original = get_option('illu_shield_file_hashes', []);
        if (empty($original)) {
            self::snapshot_files();
            return;
        }

        foreach ($original as $file => $hash) {
            if (file_exists($file) && hash_file('sha256', $file) !== $hash) {
                // ALERT! File modified
                wp_mail(
                    get_option('admin_email'), 
                    '[Illu Shield] ⚠️ File Dimodifikasi: ' . basename($file),
                    "Peringatan Keamanan (FIM): File inti {$file} telah dimodifikasi.\nJika Anda tidak baru saja melakukan update core/plugin, ini bisa jadi indikasi web Anda telah disusupi (terinfeksi malware/backdoor).\nSegera periksa file tersebut."
                );
                Illu_Shield_DB::log('File Modified (FIM)', "File integrity violation: {$file}");
                
                // Update hash so we don't spam emails every day for the same modified file
                $original[$file] = hash_file('sha256', $file);
                update_option('illu_shield_file_hashes', $original);
            }
        }
    }

    public function start_realtime_fim() {
        if (empty($_POST) && empty($_FILES)) return; // Only monitor dynamic requests that might upload files
        register_shutdown_function([__CLASS__, 'end_realtime_fim']);
    }

    public static function end_realtime_fim() {
        $upload_dir = wp_upload_dir();
        if (empty($upload_dir['basedir'])) return;
        
        // Scan for PHP files in upload directory (max 2 levels deep for performance)
        $php_files = array_merge(
            glob($upload_dir['basedir'] . '/*.php') ?: [],
            glob($upload_dir['basedir'] . '/*/*.php') ?: [],
            glob($upload_dir['basedir'] . '/*/*/*.php') ?: []
        );
        
        if (!empty($php_files)) {
            foreach ($php_files as $file) {
                $mtime = filemtime($file);
                if (time() - $mtime < 60) {
                    // New PHP file created in uploads during this request!
                    Illu_Shield_DB::log('Webshell Detected', 'Realtime FIM Auto-Deleted suspicious PHP file in uploads: ' . $file);
                    @unlink($file); // Auto-delete webshell
                }
            }
        }
    }

    public function inject_csp_nonce($tag, $handle, $src) {
        if (defined('ILLU_CSP_NONCE')) {
            return str_replace('<script ', '<script nonce="' . ILLU_CSP_NONCE . '" ', $tag);
        }
        return $tag;
    }

    public function inject_inline_csp_nonce($attributes) {
        if (defined('ILLU_CSP_NONCE')) {
            $attributes['nonce'] = ILLU_CSP_NONCE;
        }
        return $attributes;
    }

    public function add_security_headers() {
        if (!headers_sent()) {
            if (!defined('ILLU_CSP_NONCE')) {
                try {
                    define('ILLU_CSP_NONCE', base64_encode(random_bytes(16)));
                } catch (Exception $e) {
                    define('ILLU_CSP_NONCE', base64_encode(wp_generate_password(16, true)));
                }
            }

            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            
            // Nonce-based CSP for scripts, remove unsafe-eval and unsafe-inline.
            $report_uri = esc_url(home_url('/wp-json/illu-shield/v1/csp-report'));
            $csp = "default-src 'self' https: data: blob:; " .
                   "script-src 'self' 'nonce-" . ILLU_CSP_NONCE . "' https:; " .
                   "style-src 'self' 'unsafe-inline' https:; " .
                   "img-src 'self' data: https:; " .
                   "font-src 'self' data: https:; " .
                   "report-uri " . $report_uri . ";";
                   
            header("Content-Security-Policy-Report-Only: " . $csp);
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
            header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
        }
    }

    public function remove_x_pingback($headers) {
        unset($headers['X-Pingback']);
        return $headers;
    }

    public function block_xmlrpc_requests() {
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            $this->register_violation('XML-RPC Blocked', 'Blocked XML-RPC request.');
            header('HTTP/1.1 403 Forbidden');
            die('XML-RPC API is disabled by Illu Shield.');
        }
    }

    public function block_bad_bots() {
        // Skip filtering for webhooks
        $raw_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($raw_uri, '/wp-json/sharelink/') !== false || strpos($raw_uri, 'rest_route=/sharelink/') !== false || 
            strpos($raw_uri, '/wp-json/canvas-app/') !== false || strpos($raw_uri, 'rest_route=/canvas-app/') !== false ||
            strpos($raw_uri, '/webhook') !== false || strpos($raw_uri, '/wp-json/webhook/') !== false) {
            return;
        }

        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // KEL-04: Block empty UA
        if (empty(trim($user_agent))) {
            $this->register_violation('Bad Bot Blocked', "Blocked empty user-agent");
            header('HTTP/1.1 403 Forbidden');
            die('Access Denied: Empty User-Agent.');
        }

        // Allow list (Good bots)
        $good_bots = ['Googlebot', 'Bingbot', 'Slurp', 'DuckDuckBot', 'Baiduspider', 'YandexBot', 'GPTBot', 'ClaudeBot', 'Applebot'];
        foreach ($good_bots as $bot) {
            if (stripos($user_agent, $bot) !== false) {
                return; // Let it pass
            }
        }

        // Block list (Bad bots/Scanners)
        $bad_bots = ['curl', 'wget', 'python-requests', 'nikto', 'sqlmap', 'nmap', 'zgrab', 'masscan', 'libwww-perl', 'scrapy', 'postman', 'java', 'acunetix', 'dirbuster', 'go-http-client'];
        foreach ($bad_bots as $bot) {
            if (stripos($user_agent, $bot) !== false) {
                $this->register_violation('Bad Bot Blocked', "Blocked user-agent: $user_agent");
                header('HTTP/1.1 403 Forbidden');
                die('Access Denied: Bad Bot / Scanner detected.');
            }
        }
    }

    public function anti_spam_comment($commentdata) {
        $spam_keywords = ['viagra', 'cialis', 'casino', 'porn', 'xxx', 'seo services', 'buy followers', 'crypto investment'];
        
        $content = strtolower($commentdata['comment_content']);
        $author_url = strtolower($commentdata['comment_author_url']);
        
        // KEL-03: Strip non-alphanumeric to catch obfuscated keywords (e.g. v.i.a.g.r.a)
        $clean_content = preg_replace('/[^a-z0-9]/', '', $content);
        $clean_keywords = array_map(function($kw) { return preg_replace('/[^a-z0-9]/', '', $kw); }, $spam_keywords);
        
        foreach ($clean_keywords as $i => $clean_keyword) {
            if (strpos($clean_content, $clean_keyword) !== false || strpos($author_url, $spam_keywords[$i]) !== false) {
                $this->register_violation('Spam Comment', 'Blocked comment containing spam keyword: ' . $spam_keywords[$i]);
                wp_die('Comment blocked due to spam content.');
            }
        }
        
        return $commentdata;
    }

    private function scan_input_array($data, $patterns, $source = 'INPUT', $whitelist = []) {
        foreach ($data as $key => $value) {
            if (is_admin() && in_array($key, $whitelist)) continue;
            
            if (is_array($value)) {
                $this->scan_input_array($value, $patterns, $source . '[' . $key . ']', $whitelist);
            } elseif (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $this->register_violation('Firewall Blocked', "Request blocked due to suspicious pattern in {$source}[{$key}]");
                        header('HTTP/1.1 403 Forbidden');
                        die('Request blocked by Illu Shield Micro-Firewall.');
                    }
                }
            }
        }
    }

    public function micro_firewall() {
        // Skip filtering for internal WP admin actions or Sharelink valid webhooks
        // to ensure ZERO-CONFLICT. Sharelink API validates payload strictly.
        $raw_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (strpos($raw_uri, '/wp-json/sharelink/') !== false || strpos($raw_uri, 'rest_route=/sharelink/') !== false || 
            strpos($raw_uri, '/wp-json/canvas-app/') !== false || strpos($raw_uri, 'rest_route=/canvas-app/') !== false) {
            return; // Pass-through for Sharelink Dynamic Verification
        }
        if (strpos($raw_uri, '/webhook') !== false || strpos($raw_uri, '/wp-json/webhook/') !== false) {
            return; // Pass-through for Webhooks
        }

        $bad_patterns = [
            '/<script.*?>.*?<\/script>/is',  // XSS
            '/UNION\s+ALL\s+SELECT/is',       // SQLi
            '/base64_decode\(/is',            // Obfuscated payload
            '/eval\(/is',                     // Execution
            '/(?:%3C|<)iframe/is',            // Iframe injection
            '/(?:%3C|<)object/is',            // Object injection
            '/document\.cookie/is'            // Cookie stealing
        ];

        // Scan GET
        if (!empty($_GET)) {
            $this->scan_input_array($_GET, $bad_patterns, 'GET');
        }

        // Scan POST only for non-admin to prevent breaking settings saves
        if (!empty($_POST)) {
            $admin_whitelist = ['content', 'post_content', 'excerpt', 'illu_shield_settings']; // CEL-03
            $this->scan_input_array($_POST, $bad_patterns, 'POST', $admin_whitelist);
        }
        
        // Block Directory Traversal in common params
        $uri = urldecode($_SERVER['REQUEST_URI']);
        if (strpos($uri, '../') !== false || strpos($uri, '..\\') !== false || strpos($uri, '/etc/passwd') !== false) {
            $this->register_violation('Firewall Blocked', "Directory traversal / LFI blocked in URI.");
            header('HTTP/1.1 403 Forbidden');
            die('Directory traversal blocked.');
        }
    }

    public function protect_wp_config() {
        $htaccess_path = ABSPATH . '.htaccess';
        if (file_exists($htaccess_path) && is_writable($htaccess_path)) {
            $content = file_get_contents($htaccess_path);
            $new_rules = '';
            
            if (strpos($content, 'Protect wp-config') === false) {
                $new_rules .= "\n# Illu Shield: Protect wp-config.php\n<Files wp-config.php>\norder allow,deny\ndeny from all\n</Files>\n";
                Illu_Shield_DB::log('wp-config Secured', 'Added protection for wp-config.php in .htaccess.');
            }
            
            if (strpos($content, 'Remove WP Fingerprints') === false) {
                $new_rules .= "\n# Illu Shield: Remove WP Fingerprints\n<FilesMatch \"^(readme\\.html|license\\.txt|readme\\.txt|wp-config\\.php\\.bak)$\">\norder allow,deny\ndeny from all\n</FilesMatch>\n";
                Illu_Shield_DB::log('Fingerprints Secured', 'Added protection for readme.html and license.txt in .htaccess.');
            }

            if (!empty($new_rules)) {
                file_put_contents($htaccess_path, $content . $new_rules);
            }
        }
    }

    public function secure_upload_folder() {
        $upload_dir = wp_upload_dir();
        if (!empty($upload_dir['basedir'])) {
            $htaccess_file = trailingslashit($upload_dir['basedir']) . '.htaccess';
            if (!file_exists($htaccess_file)) {
                $rules = "<Files *.php>\nDeny from all\n</Files>\n";
                @file_put_contents($htaccess_file, $rules);
                Illu_Shield_DB::log('Upload Secured', 'Created .htaccess in upload directory to prevent script execution.');
            }
        }
    }

    public function block_blacklisted_ips() {
        // Skip filtering for webhooks
        $raw_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($raw_uri, '/wp-json/sharelink/') !== false || strpos($raw_uri, 'rest_route=/sharelink/') !== false || 
            strpos($raw_uri, '/wp-json/canvas-app/') !== false || strpos($raw_uri, 'rest_route=/canvas-app/') !== false ||
            strpos($raw_uri, '/webhook') !== false || strpos($raw_uri, '/wp-json/webhook/') !== false) {
            return;
        }

        $ip = $this->get_client_ip();
        
        // 0. Whitelisted IP check (Bypass blocks)
        $whitelist = get_option('illu_shield_whitelist_ips', []);
        if (in_array($ip, $whitelist)) {
            return;
        }
        
        $wildcards_wl = get_option('illu_shield_wildcard_whitelist_ips', []);
        if (!empty($wildcards_wl)) {
            foreach ($wildcards_wl as $pattern) {
                $pattern = trim($pattern);
                if (empty($pattern)) continue;
                $regex = str_replace('\*', '.*', preg_quote($pattern, '/'));
                if (preg_match('/^' . $regex . '$/', $ip)) {
                    return;
                }
            }
        }

        // 1. Exact IP Blacklist
        $blacklist = get_option('illu_shield_blacklist_ips', []);
        if (in_array($ip, $blacklist)) {
            // Check if it's expired
            $auto_blacklist_days = intval($this->settings['auto_blacklist_days'] ?? 0);
            if ($auto_blacklist_days > 0) {
                $timestamps = get_option('illu_shield_blacklist_timestamps', []);
                $banned_time = $timestamps[$ip] ?? 0;
                
                // If it's manually added or old without timestamp, we might not have a timestamp.
                // We'll only expire if timestamp exists and is expired.
                if ($banned_time > 0 && (time() - $banned_time) > ($auto_blacklist_days * 86400)) {
                    // Expired! Remove from blacklist
                    $blacklist = array_diff($blacklist, [$ip]);
                    update_option('illu_shield_blacklist_ips', array_values($blacklist));
                    unset($timestamps[$ip]);
                    update_option('illu_shield_blacklist_timestamps', $timestamps);
                    Illu_Shield_DB::log('IP Unbanned', "IP {$ip} automatically removed from blacklist after {$auto_blacklist_days} days.");
                    return; // Let them pass
                }
            }
            
            header('HTTP/1.1 403 Forbidden');
            die('Access Denied: Your IP has been blacklisted due to malicious activity.');
        }

        // 2. Wildcard / CIDR Range Blacklist
        $wildcards = get_option('illu_shield_wildcard_ips', []);
        if (!empty($wildcards)) {
            foreach ($wildcards as $pattern) {
                $pattern = trim($pattern);
                if (empty($pattern)) continue;
                
                // Convert 173.239.240.* to regex 173\.239\.240\..*
                $regex = str_replace('\*', '.*', preg_quote($pattern, '/'));
                if (preg_match('/^' . $regex . '$/', $ip)) {
                    header('HTTP/1.1 403 Forbidden');
                    die('Access Denied: Your IP range is blacklisted.');
                }
            }
        }

        // 3. Temporary Lockout (Tiered)
        $lockout_end = get_transient('illu_lockout_' . md5($ip));
        if ($lockout_end && time() < $lockout_end) {
            $remaining = ceil(($lockout_end - time()) / 60);
            header('HTTP/1.1 429 Too Many Requests');
            die("Access Denied: Temporarily locked out due to suspicious activity. Try again in $remaining minutes.");
        }
    }

    public function check_login_attempts($user, $username, $password) {
        $ip = $this->get_client_ip();
        
        // Also block early if they try to login while on tiered lockout
        $lockout_end = get_transient('illu_lockout_' . md5($ip));
        if ($lockout_end && time() < $lockout_end) {
            $remaining = ceil(($lockout_end - time()) / 60);
            return new WP_Error(
                'too_many_retries',
                "<strong>ERROR</strong>: Terlalu banyak percobaan login gagal dari IP Anda. Silakan coba lagi dalam $remaining menit."
            );
        }

        $option_name = 'illu_lf_' . md5($ip);
        
        $attempts = intval(get_transient($option_name));
        
        if ($attempts >= $this->max_login_attempts) {
            Illu_Shield_DB::log('Brute Force Blocked', "Blocked login attempt from IP. Total failures: $attempts.");
            return new WP_Error(
                'too_many_retries',
                '<strong>ERROR</strong>: Terlalu banyak percobaan login gagal dari IP Anda. Silakan coba lagi nanti.'
            );
        }
        
        return $user;
    }

    public function log_failed_attempt($username) {
        $this->register_violation('Login Failed', "Failed login attempt for username: $username");
    }

    public function register_violation($event_type, $description = '') {
        $ip = $this->get_client_ip();
        
        // 0. Bypass Whitelisted IPs
        $whitelist = get_option('illu_shield_whitelist_ips', []);
        if (in_array($ip, $whitelist)) {
            return false;
        }

        $wildcards_wl = get_option('illu_shield_wildcard_whitelist_ips', []);
        if (!empty($wildcards_wl)) {
            foreach ($wildcards_wl as $pattern) {
                $pattern = trim($pattern);
                if (empty($pattern)) continue;
                $regex = str_replace('\*', '.*', preg_quote($pattern, '/'));
                if (preg_match('/^' . $regex . '$/', $ip)) {
                    return false;
                }
            }
        }

        $blacklist = get_option('illu_shield_blacklist_ips', []);
        
        if (in_array($ip, $blacklist)) {
            return true; // Already blacklisted
        }

        $option_name = 'illu_lf_' . md5($ip);
        $lockout_level_key = 'illu_lockout_level_' . md5($ip);
        
        // KEL-01: Migrate brute force counter to Transient
        $attempts = intval(get_transient($option_name)) + 1;
        set_transient($option_name, $attempts, 24 * HOUR_IN_SECONDS);
        
        if ($attempts >= $this->max_login_attempts) {
            // FIX-04: lockout_level dengan TTL (90 hari) via transient
            $level = intval(get_transient($lockout_level_key)) + 1;
            set_transient($lockout_level_key, $level, 90 * DAY_IN_SECONDS);
            
            delete_transient($option_name); // Reset attempts to start next tier
            
            if ($level == 1) {
                $tier1_mins = intval($this->settings['lockout_tier1_minutes'] ?? 15);
                $duration = $tier1_mins * 60;
                set_transient('illu_lockout_' . md5($ip), time() + $duration, $duration);
                Illu_Shield_DB::log("IP Lockout ({$tier1_mins}m)", "IP temporarily locked for {$tier1_mins} mins. Last event: $event_type.");
            } elseif ($level == 2) {
                $tier2_hours = intval($this->settings['lockout_tier2_hours'] ?? 1);
                $duration = $tier2_hours * 60 * 60;
                set_transient('illu_lockout_' . md5($ip), time() + $duration, $duration);
                Illu_Shield_DB::log("IP Lockout ({$tier2_hours}h)", "IP temporarily locked for {$tier2_hours} hours. Last event: $event_type.");
            } elseif ($level == 3) {
                $tier3_days = intval($this->settings['lockout_tier3_days'] ?? 1);
                $duration = $tier3_days * 24 * 60 * 60;
                set_transient('illu_lockout_' . md5($ip), time() + $duration, $duration);
                Illu_Shield_DB::log("IP Lockout ({$tier3_days}d)", "IP temporarily locked for {$tier3_days} days. Last event: $event_type.");
            } else {
                // Tier 4: Permanent / Auto Blacklist
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $blacklist[] = $ip;
                    update_option('illu_shield_blacklist_ips', array_unique($blacklist));
                    
                    // Save timestamp for auto removal
                    $timestamps = get_option('illu_shield_blacklist_timestamps', []);
                    $timestamps[$ip] = time();
                    update_option('illu_shield_blacklist_timestamps', $timestamps);
                    
                    do_action('illu_shield_ip_blacklisted', $ip);
                    
                    // Email alert
                    if (($this->settings['email_alert_blacklist'] ?? 'yes') === 'yes') {
                        wp_mail(
                            get_option('admin_email'),
                            '[Illu Shield] IP Baru Diblokir: ' . $ip,
                            "IP $ip telah diblokir karena pelanggaran berulang (Tier 4).\nPelanggaran terakhir: $event_type\nDeskripsi: $description"
                        );
                    }
                }
                delete_transient($lockout_level_key); // clear tier memory
                Illu_Shield_DB::log('IP Blacklisted', "IP blacklisted after repeated lockouts. Last event: $event_type. IP: $ip");
            }
            return true;
        } else {
            if (!empty($description)) {
                Illu_Shield_DB::log($event_type, $description);
            } else {
                Illu_Shield_DB::log($event_type, "Violation detected from IP. Count: $attempts");
            }
            return false;
        }
    }

    public function clear_failed_attempts($username, $user = null) {
        $ip = $this->get_client_ip();
        $option_name = 'illu_lf_' . md5($ip);
        delete_transient($option_name);
        
        // KEL-08: Check for new IP login alert for any user with edit access
        if ($user && user_can($user->ID, 'edit_posts') && ($this->settings['email_alert_new_login'] ?? 'no') === 'yes') {
            $known_ips = get_user_meta($user->ID, 'illu_known_ips', true) ?: [];
            if (!in_array($ip, $known_ips)) {
                $known_ips[] = $ip;
                update_user_meta($user->ID, 'illu_known_ips', $known_ips);
                
                $role = implode(', ', (array)$user->roles);
                
                wp_mail(
                    get_option('admin_email'),
                    '[Illu Shield] Login dari IP Baru',
                    "User {$username} (Role: {$role}) baru saja login dari IP Address yang belum pernah digunakan sebelumnya: {$ip}\n\nJika ini bukan Anda atau staf Anda, segera ganti password dan cek pengaturan keamanan."
                );
            }
        }
    }

    private function get_client_ip() {
        return Illu_Shield_DB::get_client_ip();
    }

    public function audit_option_updates($option, $old_value, $value) {
        $tracked_options = ['illu_shield_settings', 'illu_shield_blacklist_ips', 'default_role', 'users_can_register'];
        if (in_array($option, $tracked_options)) {
            $user_id = get_current_user_id();
            Illu_Shield_DB::log('Audit: Option Updated', "Option '$option' was updated by user ID $user_id.", $user_id);
        }
    }

    public function audit_profile_update($user_id, $old_user_data) {
        $current_user_id = get_current_user_id();
        Illu_Shield_DB::log('Audit: Profile Updated', "User ID $user_id profile was updated by user ID $current_user_id.", $current_user_id);
    }

    public function audit_delete_user($id) {
        $current_user_id = get_current_user_id();
        Illu_Shield_DB::log('Audit: User Deleted', "User ID $id was deleted by user ID $current_user_id.", $current_user_id);
    }

    public function audit_plugin_activated($plugin, $network_wide) {
        $user_id = get_current_user_id();
        Illu_Shield_DB::log('Audit: Plugin Activated', "Plugin '$plugin' was activated by user ID $user_id.", $user_id);
    }

    public function audit_plugin_deactivated($plugin, $network_wide) {
        $user_id = get_current_user_id();
        Illu_Shield_DB::log('Audit: Plugin Deactivated', "Plugin '$plugin' was deactivated by user ID $user_id.", $user_id);
    }

    public function audit_new_user($user_id) {
        $current_user_id = get_current_user_id();
        Illu_Shield_DB::log('Audit: User Registered', "New user ID $user_id registered. Action by user ID $current_user_id.", $current_user_id);
    }

    public function audit_role_change($user_id, $role, $old_roles) {
        $current_user_id = get_current_user_id();
        $old = is_array($old_roles) ? implode(',', $old_roles) : '';
        Illu_Shield_DB::log('Audit: User Role Changed', "User ID $user_id role changed from '$old' to '$role' by user ID $current_user_id.", $current_user_id);
    }
}
