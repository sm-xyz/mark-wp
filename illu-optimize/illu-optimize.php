<?php
/**
 * Plugin Name: Illu-Optimize
 * Description: Server-Level Cache & Performance Manager. Integrates with Redis, minifies HTML/CSS, and optimizes asset delivery.
 * Version: 1.1.0
 * Author: In-House
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ILLU_OPTIMIZE_DIR', plugin_dir_path(__FILE__));
define('ILLU_OPTIMIZE_URL', plugin_dir_url(__FILE__));

// Includes
require_once ILLU_OPTIMIZE_DIR . 'includes/class-admin.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-redis-manager.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-minifier.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-assets-injector.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-clean-header.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-image-converter.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-responsive-images.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-robots.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-sitemap.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-meta.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-ads-manager.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-async-worker.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-micro-cache.php';
require_once ILLU_OPTIMIZE_DIR . 'includes/class-db-optimizer.php';

// Initialize
function illu_optimize_init() {
    new Illu_Optimize_Admin();
    new Illu_Optimize_Ads_Manager();
    new Illu_Optimize_Redis_Manager();
    new Illu_Optimize_Minifier();
    new Illu_Optimize_Assets_Injector();
    new Illu_Optimize_Clean_Header();
    new Illu_Media_Image_Converter();
    new Illu_Media_Responsive_Images();
    new Illu_SEO_Robots();
    new Illu_SEO_Sitemap();
    new Illu_SEO_Meta();
    new Illu_Optimize_Async_Worker();
    new Illu_Optimize_Micro_Cache();
    new Illu_Optimize_DB_Optimizer();

    
    // Auto purge cache hook
    add_action('illu_optimize_auto_purge', 'illu_optimize_run_auto_purge');
}
add_action('plugins_loaded', 'illu_optimize_init');

function illu_optimize_run_auto_purge() {
    do_action('illu_shield_clear_smart_cache');
    do_action('illu_optimize_purge_redis');
    do_action('illu_shield_purge_cloudflare');
}

// Activation hook for object-cache.php and rewrite rules
register_activation_hook(__FILE__, function() {
    Illu_Optimize_Redis_Manager::install_object_cache();
    Illu_SEO_Sitemap::add_rewrite_rules();
    
    if (!wp_next_scheduled('illu_optimize_auto_purge')) {
        $schedule = get_option('illu_auto_purge_schedule', 'twicedaily');
        if ($schedule !== 'never') {
            wp_schedule_event(time(), $schedule, 'illu_optimize_auto_purge');
        }
    }
    
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function() {
    Illu_Optimize_Redis_Manager::uninstall_object_cache();
    wp_clear_scheduled_hook('illu_optimize_auto_purge');
    flush_rewrite_rules();
});
