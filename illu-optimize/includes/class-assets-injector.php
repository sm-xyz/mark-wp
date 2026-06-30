<?php
/**
 * Critical CSS, Assets Injector, and No-Conflict CSS Delivery
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Optimize_Assets_Injector {
    
    public function __construct() {
        add_action('wp_head', [$this, 'inject_preconnect'], 1);
        add_filter('style_loader_tag', [$this, 'async_css_delivery'], 10, 4);
    }

    public function inject_preconnect() {
        echo "<link rel='preconnect' href='https://fonts.googleapis.com' crossorigin />\n";
        echo "<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin />\n";
    }

    public function async_css_delivery($html, $handle, $href, $media) {
        // Asynchronously load specific heavy CSS files (like Tailwind theme.min.css if configured)
        // We can add handles to this array that should be loaded asynchronously
        $async_handles = ['illusi-theme-css']; // Default handle for our theme
        
        if (in_array($handle, $async_handles)) {
            $html = sprintf(
                "<link rel='preload' href='%s' as='style' onload=\"this.onload=null;this.rel='stylesheet'\" media='%s' />\n",
                esc_url($href),
                esc_attr($media)
            );
            $html .= sprintf(
                "<noscript><link rel='stylesheet' href='%s' media='%s' /></noscript>\n",
                esc_url($href),
                esc_attr($media)
            );
        }

        return $html;
    }
}
