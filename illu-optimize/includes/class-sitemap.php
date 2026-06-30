<?php
/**
 * Ultra-fast sitemap.xml
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_SEO_Sitemap {
    
    public function __construct() {
        add_action('init', [$this, 'init_rewrite']);
        add_action('template_redirect', [$this, 'render_sitemap'], 1);
    }

    public static function add_rewrite_rules() {
        add_rewrite_rule('^sitemap\.xml$', 'index.php?illu_sitemap=1', 'top');
    }
    
    public function init_rewrite() {
        self::add_rewrite_rules();
        // Add query var
        add_filter('query_vars', function($vars) {
            $vars[] = 'illu_sitemap';
            return $vars;
        });
    }

    public function render_sitemap() {
        if (get_query_var('illu_sitemap')) {
            // Check cache
            $cache_key = 'illu_sitemap_xml';
            $sitemap = wp_cache_get($cache_key, 'illu_seo');

            if (false === $sitemap) {
                $sitemap = $this->generate_sitemap();
                // Cache for 12 hours
                wp_cache_set($cache_key, $sitemap, 'illu_seo', 43200);
            }

            header('Content-Type: text/xml; charset=utf-8');
            echo $sitemap;
            exit;
        }
    }

    private function generate_sitemap() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Homepage
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . esc_url(home_url('/')) . '</loc>' . "\n";
        $xml .= '    <changefreq>daily</changefreq>' . "\n";
        $xml .= '    <priority>1.0</priority>' . "\n";
        $xml .= '  </url>' . "\n";

        // Posts and Pages
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 1000,
            'orderby' => 'modified',
            'order' => 'DESC',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                // Skip if meta set to noindex
                $noindex = get_post_meta($post->ID, '_illu_seo_noindex', true);
                if ($noindex === 'yes') continue;

                $url = get_permalink($post->ID);
                $modified = get_the_modified_time('c', $post->ID);
                
                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . esc_url($url) . '</loc>' . "\n";
                $xml .= '    <lastmod>' . $modified . '</lastmod>' . "\n";
                $xml .= '    <changefreq>weekly</changefreq>' . "\n";
                $xml .= '    <priority>0.8</priority>' . "\n";
                $xml .= '  </url>' . "\n";
            }
        }
        
        $xml .= '</urlset>';
        return $xml;
    }
}
