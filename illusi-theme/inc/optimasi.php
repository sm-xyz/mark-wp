<?php
/**
 * Remove WordPress Bloatware
 */

if (!defined('ABSPATH')) {
    exit;
}

// Remove Emoji Scripts
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('admin_print_scripts', 'print_emoji_detection_script');
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('admin_print_styles', 'print_emoji_styles');
remove_filter('the_content_feed', 'wp_staticize_emoji');
remove_filter('comment_text_rss', 'wp_staticize_emoji');
remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

// Remove Dashicons on frontend (if not logged in)
add_action('wp_enqueue_scripts', 'illusi_remove_dashicons');
function illusi_remove_dashicons() {
    if (!is_user_logged_in()) {
        wp_deregister_style('dashicons');
    }
}

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

// Remove Global Styles (theme.json default output from WP core if we handle our own)
add_action('wp_enqueue_scripts', 'illusi_remove_global_styles');
function illusi_remove_global_styles() {
    wp_dequeue_style('global-styles');
}

/**
 * Completely Disable Comments (Bloatware Removal)
 */

// Disable support for comments and trackbacks in post types
add_action('admin_init', 'illusi_disable_comments_post_types_support');
function illusi_disable_comments_post_types_support() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}

// Close comments on the front-end
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

// Hide existing comments
add_filter('comments_array', '__return_empty_array', 10, 2);

// Remove comments page in menu
add_action('admin_menu', 'illusi_disable_comments_admin_menu');
function illusi_disable_comments_admin_menu() {
    remove_menu_page('edit-comments.php');
}

// Redirect any user trying to access comments page
add_action('admin_init', 'illusi_disable_comments_admin_menu_redirect');
function illusi_disable_comments_admin_menu_redirect() {
    global $pagenow;
    if ($pagenow === 'edit-comments.php') {
        wp_redirect(admin_url());
        exit;
    }
}

// Remove comments metabox from dashboard
add_action('admin_init', 'illusi_disable_comments_dashboard');
function illusi_disable_comments_dashboard() {
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
}

// Remove comments links from admin bar
add_action('wp_before_admin_bar_render', 'illusi_disable_comments_admin_bar');
function illusi_disable_comments_admin_bar() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_menu('comments');
}
