<?php
/**
 * Clean Header Manager - Removes WordPress bloat
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Optimize_Clean_Header {
    
    public function __construct() {
        add_action('init', [$this, 'clean_wp_head']);
    }

    public function clean_wp_head() {
        // Remove Emoji Scripts
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        
        // Remove WordPress Generator Meta
        remove_action('wp_head', 'wp_generator');
        
        // Remove WLW Manifest
        remove_action('wp_head', 'wlwmanifest_link');
        
        // Remove RSD Link
        remove_action('wp_head', 'rsd_link');
        
        // Remove Shortlink
        remove_action('wp_head', 'wp_shortlink_wp_head');
        
        // Remove REST API link tag
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        
        // Remove oEmbed links
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
        remove_action('wp_head', 'wp_oembed_add_host_js');

        // Disable global styles
        add_action('wp_enqueue_scripts', function() {
            wp_dequeue_style('global-styles');
        });
    }
}
