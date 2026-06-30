<?php
if (!defined('ABSPATH')) exit;

class Illu_Shield_Cache {

    private $cache_dir;

    public function __construct() {
        $settings = get_option('illu_shield_settings', []);
        
        // Heartbeat Control
        add_action('init', [$this, 'control_heartbeat'], 1);

        if (($settings['enable_cache'] ?? 'yes') === 'yes') {
            $upload_dir = wp_upload_dir();
            $this->cache_dir = trailingslashit($upload_dir['basedir']) . 'illu-cache/';

            // Serve Cache Early (run immediately upon instantiation in plugins_loaded phase)
            $this->serve_cache();

            // Start Output Buffering to create cache
            add_action('template_redirect', [$this, 'start_cache_buffer'], 1);
            
            // Clear cache on specific actions
            add_action('save_post', [$this, 'clear_cache']);
            add_action('deleted_post', [$this, 'clear_cache']);
            add_action('switch_theme', [$this, 'clear_cache']);
            
            // Add cache clearing button in admin bar
            add_action('admin_bar_menu', [$this, 'admin_bar_cache_clear'], 100);
            add_action('admin_post_illu_clear_all_caches', [$this, 'handle_clear_cache']);
            add_action('admin_post_illu_clear_smart_cache', [$this, 'handle_clear_cache']);
            add_action('admin_post_illu_clear_cf_cache', [$this, 'handle_clear_cache']);
            add_action('illu_shield_clear_smart_cache', [$this, 'clear_cache']);
        }
    }

    public function control_heartbeat() {
        // Only keep heartbeat on post edit screens, disable elsewhere or slow it down
        add_filter('heartbeat_settings', function($settings) {
            $settings['interval'] = 60; // 60 seconds (slowest)
            return $settings;
        });
        
        // Disable heartbeat on frontend completely
        if (!is_admin()) {
            wp_deregister_script('heartbeat');
        }
    }

    private function should_bypass_cache() {
        // Zero-Conflict: Force bypass for Sharelink specific paths
        $uri = $_SERVER['REQUEST_URI'];
        
        if (strpos($uri, '/ai/') !== false) return true; // Sharelink Canvas proxy/redirects
        if (strpos($uri, '/webhook') !== false) return true; // Webhooks
        if (strpos($uri, '/wp-json/sharelink/') !== false) return true; // Sharelink REST API
        if (strpos($uri, '/wp-json/canvas-app/') !== false) return true; // Sharelink WP canvas app API
        if (strpos($uri, 'wp-admin') !== false || strpos($uri, 'wp-login.php') !== false) return true; // WP Core Backend
        if (!empty($_GET) || !empty($_POST)) return true; // dynamic requests

        // Bypass if user is logged in
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'wordpress_logged_in_') !== false) return true;
            if (strpos($name, 'wp-settings-') !== false) return true;
        }

        return false;
    }

    private function get_cache_file_path() {
        $path = strtok($_SERVER['REQUEST_URI'], '?');
        $hash = wp_hash($path); // Fix CEL-02 & FITUR-06
        return $this->cache_dir . $hash . '.html';
    }

    public function serve_cache() {
        if ($this->should_bypass_cache()) return;

        $cache_file = $this->get_cache_file_path();
        
        if (file_exists($cache_file)) {
            $mtime = filemtime($cache_file);
            // Cache valid for 1 hour
            if (time() - $mtime < 3600) {
                header('X-Illu-Cache: HIT');
                readfile($cache_file);
                exit;
            } else {
                @unlink($cache_file);
            }
        }
        header('X-Illu-Cache: MISS');
    }

    public function start_cache_buffer() {
        if ($this->should_bypass_cache() || is_404() || is_search()) return;
        
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
            file_put_contents($this->cache_dir . 'index.php', '<?php // silence');
            
            // Protect direct access to html files via htaccess
            $htaccess_rules = "<Files *.html>\n    Header set Cache-Control \"public, max-age=3600\"\n</Files>\n# Prevent script execution just in case\n<Files *.php>\n    Deny from all\n</Files>\n";
            file_put_contents($this->cache_dir . '.htaccess', $htaccess_rules);
        }

        ob_start([$this, 'cache_output_callback']);
    }

    public function cache_output_callback($buffer) {
        // Don't cache error pages or empty buffers
        if (empty($buffer) || http_response_code() !== 200) return $buffer;

        // Minify output nicely
        $buffer = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $buffer); // Remove HTML comments
        
        // Add stamp
        $buffer .= "\n<!-- Illu Shield Static Cache Created @ " . date('Y-m-d H:i:s') . " -->";
        
        $cache_file = $this->get_cache_file_path();
        @file_put_contents($cache_file, $buffer);

        return $buffer;
    }

    public function clear_cache() {
        if (file_exists($this->cache_dir)) {
            $files = glob($this->cache_dir . '*.html');
            if ($files) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        }
    }

    public function admin_bar_cache_clear($wp_admin_bar) {
        if (current_user_can('manage_options')) {
            $wp_admin_bar->add_node([
                'id'    => 'illu-cache-menu',
                'title' => '⚡ Illu Caches',
                'href'  => '#',
                'meta'  => ['class' => 'illu-cache-menu']
            ]);
            
            $wp_admin_bar->add_node([
                'id'    => 'illu-clear-all',
                'parent'=> 'illu-cache-menu',
                'title' => 'Purge All Caches',
                'href'  => wp_nonce_url(admin_url('admin-post.php?action=illu_clear_all_caches'), 'illu_clear_cache_nonce')
            ]);
            
            $wp_admin_bar->add_node([
                'id'    => 'illu-clear-smart',
                'parent'=> 'illu-cache-menu',
                'title' => 'Clear Smart Cache',
                'href'  => wp_nonce_url(admin_url('admin-post.php?action=illu_clear_smart_cache'), 'illu_clear_cache_nonce')
            ]);

            $wp_admin_bar->add_node([
                'id'    => 'illu-clear-cf',
                'parent'=> 'illu-cache-menu',
                'title' => 'Purge Cloudflare',
                'href'  => wp_nonce_url(admin_url('admin-post.php?action=illu_clear_cf_cache'), 'illu_clear_cache_nonce')
            ]);
        }
    }

    public function handle_clear_cache() {
        if (current_user_can('manage_options') && isset($_REQUEST['action'])) {
            check_admin_referer('illu_clear_cache_nonce');
            $action = sanitize_text_field($_REQUEST['action']);
            
            if ($action === 'illu_clear_smart_cache' || $action === 'illu_clear_all_caches') {
                $this->clear_cache();
            }
            
            if ($action === 'illu_clear_cf_cache' || $action === 'illu_clear_all_caches') {
                do_action('illu_shield_purge_cloudflare');
            }
            
            if ($action === 'illu_clear_all_caches') {
                do_action('illu_optimize_purge_redis');
            }

            wp_safe_redirect(wp_get_referer() ?: admin_url());
            exit;
        }
    }
}
