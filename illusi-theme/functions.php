<?php
/**
 * Illusi Theme functions and definitions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('ILLUSI_THEME_VERSION', '1.0.2');
define('ILLUSI_THEME_DIR', trailingslashit(get_template_directory()));
define('ILLUSI_THEME_URI', trailingslashit(get_template_directory_uri()));

// Include core files
require_once ILLUSI_THEME_DIR . 'inc/optimasi.php';
require_once ILLUSI_THEME_DIR . 'inc/seo.php';
require_once ILLUSI_THEME_DIR . 'inc/images.php';
require_once ILLUSI_THEME_DIR . 'inc/admin-options.php';

// Setup Theme
add_action('after_setup_theme', 'illusi_theme_setup');
function illusi_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('custom-logo', array(
        'height'               => 64,
        'width'                => 200,
        'flex-height'          => true,
        'flex-width'           => true,
        'header-text'          => array('site-title', 'site-description'),
    ));
    add_theme_support('editor-styles');
    add_editor_style('assets/css/editor.min.css');
    
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'illusi-theme'),
        'footer'  => __('Footer Menu', 'illusi-theme'),
    ));
}

// Add tailwind classes to nav menu links
add_filter('nav_menu_link_attributes', 'illusi_nav_menu_link_class', 10, 3);
function illusi_nav_menu_link_class($atts, $item, $args) {
    if ($args->theme_location == 'primary' || $args->theme_location == 'footer') {
        $atts['class'] = 'text-header-text hover:text-header-hover dark:text-slate-300 dark:hover:text-blue-400 transition-colors';
    }
    return $atts;
}

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'illusi_enqueue_assets');
function illusi_enqueue_assets() {
    wp_enqueue_style('illusi-style', ILLUSI_THEME_URI . 'assets/css/theme.min.css', array(), ILLUSI_THEME_VERSION);
    wp_enqueue_script('illusi-script', ILLUSI_THEME_URI . 'assets/js/theme.min.js', array(), ILLUSI_THEME_VERSION, true);
}

// Typography Fixes for Content
add_action('wp_head', function() {
    echo '<style>
        .content { font-size: 1rem; line-height: 1.6; color: #334155; }
        .dark .content { color: #cbd5e1; }
        .content p { margin-bottom: 1.25rem; }
        .content h1, .content h2, .content h3, .content h4, .content h5, .content h6 {
            color: #0f172a; font-weight: 700; margin-top: 1.75em; margin-bottom: 0.75em; line-height: 1.35;
        }
        .dark .content h1, .dark .content h2, .dark .content h3, .dark .content h4, .dark .content h5, .dark .content h6 {
            color: #f8fafc;
        }
        .content h1 { font-size: 1.875em; }
        .content h2 { font-size: 1.5em; }
        .content h3 { font-size: 1.25em; }
        .content h4 { font-size: 1.125em; }
        .content ul { list-style-type: disc; padding-left: 1.5em; margin-bottom: 1.25rem; }
        .content ol { list-style-type: decimal; padding-left: 1.5em; margin-bottom: 1.25rem; }
        .content li { margin-bottom: 0.375rem; }
        .content a { color: #2563eb; text-decoration: underline; text-underline-offset: 4px; }
        .content a:hover { color: #1d4ed8; }
        .dark .content a { color: #60a5fa; }
        .dark .content a:hover { color: #93c5fd; }
        .content blockquote { border-left: 4px solid #e2e8f0; padding-left: 1rem; font-style: italic; color: #475569; margin: 1.25rem 0; }
        .dark .content blockquote { border-color: #334155; color: #94a3b8; }
        .content img { max-width: 100%; height: auto; border-radius: 0.5rem; margin: 1.5rem auto; display: block; }
        .content pre { background: #1e293b; color: #f8fafc; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin-bottom: 1.25rem; }
        .content code { background: #f1f5f9; padding: 0.2em 0.4em; border-radius: 0.25rem; font-size: 0.875em; color: #db2777; }
        .dark .content code { background: #334155; color: #f472b6; }
        .content pre code { background: transparent; color: inherit; padding: 0; }
    </style>';
});
