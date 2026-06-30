<?php
/**
 * Micro-Cache API
 * Caches REST API responses to reduce DB hits.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Optimize_Micro_Cache {
    public function __construct() {
        add_filter('rest_pre_dispatch', [$this, 'serve_micro_cache'], 10, 3);
        add_filter('rest_post_dispatch', [$this, 'store_micro_cache'], 10, 3);
    }

    public function serve_micro_cache($result, $server, $request) {
        if ($request->get_method() !== 'GET') {
            return $result;
        }

        $is_enabled = get_option('illu_micro_cache_enabled', '0');
        if (!$is_enabled) return $result;

        $cache_key = 'illu_mc_' . md5($request->get_route() . wp_json_encode($request->get_params()));
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }

        return $result;
    }

    public function store_micro_cache($result, $server, $request) {
        if ($request->get_method() !== 'GET' || is_wp_error($result)) {
            return $result;
        }
        
        $is_enabled = get_option('illu_micro_cache_enabled', '0');
        if (!$is_enabled) return $result;

        $cache_key = 'illu_mc_' . md5($request->get_route() . wp_json_encode($request->get_params()));
        set_transient($cache_key, $result, 3600); // Cache for 1 hour

        return $result;
    }
}
