<?php
/**
 * Database Query Optimizer
 * Cleans expired transients and optimizes tables.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Optimize_DB_Optimizer {
    public function __construct() {
        add_action('illu_optimize_auto_purge', [$this, 'cleanup_transients']);
        add_action('illu_optimize_auto_purge', [$this, 'optimize_database_tables']);
    }

    public function cleanup_transients() {
        global $wpdb;
        // Clean expired transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%' AND option_value < " . time());
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_timeout\_%' AND option_value < " . time());
        
        // Clean orphaned transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%' AND option_name NOT LIKE '\_transient\_timeout\_%' AND option_name NOT IN (SELECT REPLACE(option_name, '\_transient\_timeout\_', '\_transient\_') FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_timeout\_%')");
    }

    public function optimize_database_tables() {
        global $wpdb;
        $is_enabled = get_option('illu_db_optimizer_enabled', '0');
        if (!$is_enabled) return;

        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}%'");
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE $table");
        }
    }
}
