<?php
/**
 * Ad Manager for Illu-Optimize
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Optimize_Ads_Manager {
    
    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        
        // Inject ads into content
        add_filter('the_content', [$this, 'inject_content_ads'], 99);
        
        // Hook for top ad (can be placed in single.php via do_action('illusi_before_content'))
        add_action('illusi_before_content', [$this, 'inject_top_ad']);
        
        // Hook for sticky right ad
        add_action('wp_footer', [$this, 'inject_sticky_right_ad']);
    }

    public function register_settings() {
        register_setting('illu_optimize_settings', 'illu_ad_top');
        register_setting('illu_optimize_settings', 'illu_ad_middle');
        register_setting('illu_optimize_settings', 'illu_ad_bottom');
        register_setting('illu_optimize_settings', 'illu_ad_sticky_link');
        register_setting('illu_optimize_settings', 'illu_ad_sticky_image');
    }

    public function inject_top_ad() {
        if (!is_single()) return;
        
        $ad_top = get_option('illu_ad_top');
        if (!empty($ad_top)) {
            echo '<div class="illu-ad-container illu-ad-top" style="display:flex; justify-content:center; width:100%; max-width:100%; overflow:hidden; margin: 24px auto;">' . $ad_top . '</div>';
        }
    }

    public function inject_content_ads($content) {
        if (!is_single() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $ad_middle = get_option('illu_ad_middle');
        $ad_bottom = get_option('illu_ad_bottom');
        
        if (!empty($ad_middle)) {
            $delimiter = '</p>';
            if (strpos($content, $delimiter) === false) {
                $delimiter = '<br />';
            }
            if (strpos($content, $delimiter) === false) {
                $delimiter = '</div>';
            }
            
            $paragraphs = explode($delimiter, $content);
            $count = count($paragraphs);
            
            if ($count > 3) {
                $middle_index = floor($count / 2);
                $paragraphs[$middle_index] .= '<div class="illu-ad-container illu-ad-middle" style="display:flex; justify-content:center; width:100%; max-width:100%; overflow:hidden; margin: 24px auto;">' . $ad_middle . '</div>';
                $content = implode($delimiter, $paragraphs);
            } else {
                $content .= '<div class="illu-ad-container illu-ad-middle" style="display:flex; justify-content:center; width:100%; max-width:100%; overflow:hidden; margin: 24px auto;">' . $ad_middle . '</div>';
            }
        }
        
        if (!empty($ad_bottom)) {
            $content .= '<div class="illu-ad-container illu-ad-bottom" style="display:flex; justify-content:center; width:100%; max-width:100%; overflow:hidden; margin: 24px auto;">' . $ad_bottom . '</div>';
        }
        
        return $content;
    }
    
    public function inject_sticky_right_ad() {
        if (!is_single()) return;
        
        $link = get_option('illu_ad_sticky_link');
        $image = get_option('illu_ad_sticky_image');
        
        if (!empty($link) && !empty($image)) {
            echo '<a href="' . esc_url($link) . '" target="_blank" rel="noopener nofollow" class="illu-ad-sticky-right" style="position:fixed; top:50%; right:0; transform:translateY(-50%); z-index:9999; display:block; max-width:120px; transition: transform 0.3s ease;">';
            echo '<img src="' . esc_url($image) . '" alt="Advertisement" style="width:100%; height:auto; display:block; border-radius: 8px 0 0 8px; box-shadow: -4px 4px 15px rgba(0,0,0,0.1);">';
            echo '</a>';
            echo '<style>.illu-ad-sticky-right:hover { transform: translateY(-50%) scale(1.05); }</style>';
        }
    }
}
