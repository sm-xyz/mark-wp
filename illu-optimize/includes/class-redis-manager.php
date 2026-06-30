<?php
/**
 * Redis Object Cache Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Optimize_Redis_Manager {
    
    public function __construct() {
        add_action('admin_notices', [$this, 'check_redis_status']);
        add_action('illu_optimize_purge_redis', [$this, 'purge_cache']);
        
        // Add redis cache to the illu caches menu if admin
        add_action('admin_bar_menu', [$this, 'admin_bar_cache_clear'], 101);
        add_action('admin_post_illu_clear_redis_cache', [$this, 'handle_clear_cache']);
    }

    public function purge_cache() {
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    
    public function admin_bar_cache_clear($wp_admin_bar) {
        if (current_user_can('manage_options') && file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
            $wp_admin_bar->add_node([
                'id'    => 'illu-clear-redis',
                'parent'=> 'illu-cache-menu', // Placed under the Illu Caches dropdown created by Illu Shield
                'title' => 'Purge Redis Cache',
                'href'  => wp_nonce_url(admin_url('admin-post.php?action=illu_clear_redis_cache'), 'illu_clear_redis_nonce')
            ]);
        }
    }

    public function handle_clear_cache() {
        if (current_user_can('manage_options') && isset($_REQUEST['action']) && $_REQUEST['action'] === 'illu_clear_redis_cache') {
            check_admin_referer('illu_clear_redis_nonce');
            $this->purge_cache();
            wp_safe_redirect(wp_get_referer() ?: admin_url());
            exit;
        }
    }

    public static function install_object_cache() {
        $source = ILLU_OPTIMIZE_DIR . 'dropins/object-cache.php';
        $destination = WP_CONTENT_DIR . '/object-cache.php';
        
        if (file_exists($source)) {
            copy($source, $destination);
        }
    }

    public static function uninstall_object_cache() {
        $destination = WP_CONTENT_DIR . '/object-cache.php';
        if (file_exists($destination)) {
            // Check if it's our drop-in before deleting
            $content = file_get_contents($destination);
            if (strpos($content, 'Illu-Optimize Redis Object Cache') !== false) {
                unlink($destination);
            }
        }
    }

    public function check_redis_status() {
        if (!extension_loaded('redis')) {
            echo '<div class="notice notice-error"><p><strong>Illu-Optimize:</strong> Ekstensi PHP Redis tidak ditemukan! Mohon install ekstensi <code>redis</code> di aaPanel untuk mengaktifkan Object Cache.</p></div>';
        } elseif (!file_exists(WP_CONTENT_DIR . '/object-cache.php')) {
            echo '<div class="notice notice-warning"><p><strong>Illu-Optimize:</strong> object-cache.php belum terpasang. Nonaktifkan dan aktifkan kembali plugin ini untuk memasangnya.</p></div>';
        }
    }
}
