<?php
/**
 * Illu-Optimize Redis Object Cache Drop-In
 * 
 * Drop-in Name: Redis Object Cache
 * Description: Lightweight Redis object cache for Illu-Optimize.
 * Version: 1.0.0
 * Author: In-House
 */

if (!defined('ABSPATH')) {
    exit;
}

function wp_cache_add($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->add($key, $data, $group, (int) $expire);
}

function wp_cache_close() {
    return true;
}

function wp_cache_decr($key, $offset = 1, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->decr($key, $offset, $group);
}

function wp_cache_delete($key, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->delete($key, $group);
}

function wp_cache_flush() {
    global $wp_object_cache;
    return $wp_object_cache->flush();
}

function wp_cache_get($key, $group = '', $force = false, &$found = null) {
    global $wp_object_cache;
    return $wp_object_cache->get($key, $group, $force, $found);
}

function wp_cache_incr($key, $offset = 1, $group = '') {
    global $wp_object_cache;
    return $wp_object_cache->incr($key, $offset, $group);
}

function wp_cache_init() {
    global $wp_object_cache;
    $wp_object_cache = new WP_Object_Cache();
}

function wp_cache_replace($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->replace($key, $data, $group, (int) $expire);
}

function wp_cache_set($key, $data, $group = '', $expire = 0) {
    global $wp_object_cache;
    return $wp_object_cache->set($key, $data, $group, (int) $expire);
}

function wp_cache_switch_to_blog($blog_id) {
    global $wp_object_cache;
    return $wp_object_cache->switch_to_blog($blog_id);
}

function wp_cache_add_global_groups($groups) {
    global $wp_object_cache;
    $wp_object_cache->add_global_groups($groups);
}

function wp_cache_add_non_persistent_groups($groups) {
    global $wp_object_cache;
    $wp_object_cache->add_non_persistent_groups($groups);
}

class WP_Object_Cache {
    private $redis;
    private $cache = [];
    private $global_groups = [];
    private $non_persistent_groups = [];
    private $prefix = '';
    private $blog_prefix = '';
    private $is_connected = false;

    public function __construct() {
        global $table_prefix;
        $this->prefix = defined('WP_CACHE_KEY_SALT') ? WP_CACHE_KEY_SALT : $table_prefix;
        $this->blog_prefix = (function_exists('is_multisite') && is_multisite()) ? get_current_blog_id() . ':' : '';

        if (extension_loaded('redis')) {
            try {
                $this->redis = new Redis();
                $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
                $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;
                
                if ($this->redis->connect($host, $port)) {
                    if (defined('WP_REDIS_PASSWORD') && WP_REDIS_PASSWORD) {
                        $this->redis->auth(WP_REDIS_PASSWORD);
                    }
                    
                    // Ping to ensure authentication was successful
                    $this->redis->ping();

                    if (defined('WP_REDIS_DATABASE') && WP_REDIS_DATABASE) {
                        $this->redis->select(WP_REDIS_DATABASE);
                    }
                    if (extension_loaded('igbinary')) {
                        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
                    } else {
                        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                    }
                    $this->is_connected = true;
                }
            } catch (Exception $e) {
                $this->is_connected = false;
            }
        }
    }

    private function get_key($key, $group) {
        $group = empty($group) ? 'default' : $group;
        $prefix = in_array($group, $this->global_groups) ? $this->prefix : $this->prefix . $this->blog_prefix;
        return $prefix . ':' . $group . ':' . $key;
    }

    public function add($key, $data, $group = 'default', $expire = 0) {
        if (wp_suspend_cache_addition()) return false;
        
        $id = $this->get_key($key, $group);
        if (in_array($group, $this->non_persistent_groups) || !$this->is_connected) {
            if (isset($this->cache[$id])) return false;
            $this->cache[$id] = $data;
            return true;
        }
        
        try {
            if ($this->redis->exists($id)) return false;
            
            $this->cache[$id] = $data;
            return $expire ? $this->redis->setex($id, $expire, $data) : $this->redis->set($id, $data);
        } catch (Exception $e) {
            $this->is_connected = false;
            return false;
        }
    }

    public function set($key, $data, $group = 'default', $expire = 0) {
        $id = $this->get_key($key, $group);
        $this->cache[$id] = $data;
        
        if (in_array($group, $this->non_persistent_groups) || !$this->is_connected) {
            return true;
        }
        
        try {
            return $expire ? $this->redis->setex($id, $expire, $data) : $this->redis->set($id, $data);
        } catch (Exception $e) {
            $this->is_connected = false;
            return false;
        }
    }

    public function get($key, $group = 'default', $force = false, &$found = null) {
        $id = $this->get_key($key, $group);
        
        if (!$force && isset($this->cache[$id])) {
            $found = true;
            return $this->cache[$id];
        }
        
        if (in_array($group, $this->non_persistent_groups) || !$this->is_connected) {
            $found = false;
            return false;
        }
        
        try {
            $value = $this->redis->get($id);
            if ($value === false) {
                $found = false;
                return false;
            }
            
            $this->cache[$id] = $value;
            $found = true;
            return $value;
        } catch (Exception $e) {
            $this->is_connected = false;
            $found = false;
            return false;
        }
    }

    public function delete($key, $group = 'default') {
        $id = $this->get_key($key, $group);
        unset($this->cache[$id]);
        
        if (in_array($group, $this->non_persistent_groups) || !$this->is_connected) {
            return true;
        }
        
        try {
            return (bool) $this->redis->del($id);
        } catch (Exception $e) {
            $this->is_connected = false;
            return false;
        }
    }

    public function replace($key, $data, $group = 'default', $expire = 0) {
        $id = $this->get_key($key, $group);
        
        if (in_array($group, $this->non_persistent_groups) || !$this->is_connected) {
            if (!isset($this->cache[$id])) return false;
            $this->cache[$id] = $data;
            return true;
        }
        
        try {
            if (!$this->redis->exists($id)) return false;
            
            $this->cache[$id] = $data;
            return $expire ? $this->redis->setex($id, $expire, $data) : $this->redis->set($id, $data);
        } catch (Exception $e) {
            $this->is_connected = false;
            return false;
        }
    }

    public function flush() {
        $this->cache = [];
        if ($this->is_connected) {
            try {
                return $this->redis->flushDb();
            } catch (Exception $e) {
                $this->is_connected = false;
                return false;
            }
        }
        return true;
    }

    public function incr($key, $offset = 1, $group = 'default') {
        $id = $this->get_key($key, $group);
        if (!$this->is_connected || in_array($group, $this->non_persistent_groups)) return false;
        try {
            $value = $this->redis->incrBy($id, $offset);
            $this->cache[$id] = $value;
            return $value;
        } catch (Exception $e) {
            $this->is_connected = false;
            return false;
        }
    }

    public function decr($key, $offset = 1, $group = 'default') {
        $id = $this->get_key($key, $group);
        if (!$this->is_connected || in_array($group, $this->non_persistent_groups)) return false;
        try {
            $value = $this->redis->decrBy($id, $offset);
            $this->cache[$id] = $value;
            return $value;
        } catch (Exception $e) {
            $this->is_connected = false;
            return false;
        }
    }

    public function add_global_groups($groups) {
        $groups = (array) $groups;
        $this->global_groups = array_unique(array_merge($this->global_groups, $groups));
    }

    public function add_non_persistent_groups($groups) {
        $groups = (array) $groups;
        $this->non_persistent_groups = array_unique(array_merge($this->non_persistent_groups, $groups));
    }

    public function switch_to_blog($blog_id) {
        $blog_id = (int) $blog_id;
        $this->blog_prefix = (function_exists('is_multisite') && is_multisite()) ? $blog_id . ':' : '';
        return true;
    }
}
