<?php
/**
 * Dynamic robots.txt Router
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_SEO_Robots {
    
    public function __construct() {
        // Intercept native robots.txt output
        add_filter('robots_txt', [$this, 'dynamic_robots_txt'], 99, 2);
    }

    public function dynamic_robots_txt($output, $public) {
        if ('0' == $public) {
            return "User-agent: *\nDisallow: /\n";
        }
        
        $sitemap_url = home_url('/sitemap.xml');
        
        $robots = "User-agent: *\n";
        $robots .= "Disallow: /wp-admin/\n";
        $robots .= "Allow: /wp-admin/admin-ajax.php\n\n";
        
        $robots .= "Sitemap: $sitemap_url\n";
        
        return $robots;
    }
}
