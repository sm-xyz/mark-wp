<?php
if (!defined('ABSPATH')) exit;

function cl_activate() {
    global $wpdb;
    $c = $wpdb->get_charset_collate();

    $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cl_apps (
        id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
        app_name     VARCHAR(191) NOT NULL,
        description  VARCHAR(500) DEFAULT '',
        canvas_link  VARCHAR(1000) DEFAULT '',
        login_script LONGTEXT,
        gk_config    LONGTEXT,
        payload      LONGTEXT,
        created_at   DATETIME NOT NULL,
        custom_slug  VARCHAR(191) DEFAULT NULL,
        UNIQUE KEY uq_slug (custom_slug),
        PRIMARY KEY (id),
        KEY k_user (user_id)
    ) $c;";

    $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cl_licenses (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
        license_key VARCHAR(191) NOT NULL,
        app_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status      VARCHAR(20)  NOT NULL DEFAULT 'active',
        label       VARCHAR(191) DEFAULT '',
        usage_count INT          NOT NULL DEFAULT 0,
        max_devices INT          NOT NULL DEFAULT 1,
        last_used   DATETIME     DEFAULT NULL,
        expires_at  DATETIME     DEFAULT NULL,
        device_fingerprint TEXT DEFAULT NULL,
        assignee_name VARCHAR(191) DEFAULT '',
        assignee_email VARCHAR(191) DEFAULT '',
        assignee_wa VARCHAR(50) DEFAULT '',
        created_at  DATETIME     NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_key (license_key),
        KEY k_app (app_id),
        KEY k_user (user_id)
    ) $c;";

    $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cl_api_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        app_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        license_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        ip_address VARCHAR(45) DEFAULT '',
        origin VARCHAR(191) DEFAULT '',
        action VARCHAR(20) DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY k_user (user_id),
        KEY k_app (app_id)
    ) $c;";

    $sql4 = "CREATE TABLE {$wpdb->prefix}cl_history (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'info',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY k_user (user_id)
    ) $c;";

    $sql5 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cl_webhook_logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        event_source VARCHAR(50) DEFAULT 'lynk.id',
        payload LONGTEXT,
        response LONGTEXT,
        status_code INT DEFAULT 200,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY k_user (user_id)
    ) $c;";

    $sql6 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cl_customers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        name VARCHAR(191) DEFAULT '',
        email VARCHAR(191) DEFAULT '',
        wa_number VARCHAR(50) DEFAULT '',
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY k_user (user_id)
    ) $c;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    dbDelta($sql5);
    dbDelta($sql6);
    
    // Attempt to flush rewrite rules
    if (!did_action('wp_loaded')) {
        add_action('wp_loaded', 'flush_rewrite_rules');
    } else {
        flush_rewrite_rules();
    }
}

function cl_maybe_upgrade() {
    global $wpdb;

    if (get_option('cl_db_version') !== CL_VERSION) {
        cl_activate();
        
        $lt = $wpdb->prefix . 'cl_licenses';
        $cols = $wpdb->get_results("SHOW COLUMNS FROM $lt");
        $c_names = array_column($cols, 'Field');
        if (!in_array('max_devices', $c_names)) {
            $wpdb->query("ALTER TABLE $lt ADD max_devices INT NOT NULL DEFAULT 1 AFTER usage_count");
        }
        if (!in_array('assignee_name', $c_names)) {
            $wpdb->query("ALTER TABLE $lt ADD assignee_name VARCHAR(191) DEFAULT '' AFTER device_fingerprint");
            $wpdb->query("ALTER TABLE $lt ADD assignee_email VARCHAR(191) DEFAULT '' AFTER assignee_name");
            $wpdb->query("ALTER TABLE $lt ADD assignee_wa VARCHAR(50) DEFAULT '' AFTER assignee_email");
        }
        // Change device_fingerprint to TEXT if needed
        $wpdb->query("ALTER TABLE $lt MODIFY device_fingerprint TEXT DEFAULT NULL");

        $at = $wpdb->prefix . 'cl_apps';
        $cols2 = $wpdb->get_results("SHOW COLUMNS FROM $at");
        if ($cols2) {
            $wpdb->query("ALTER TABLE $at MODIFY payload LONGTEXT, MODIFY login_script LONGTEXT, MODIFY gk_config LONGTEXT");
            $c_names2 = array_column($cols2, 'Field');
            if (!in_array('custom_slug', $c_names2)) {
                $wpdb->query("ALTER TABLE $at ADD custom_slug VARCHAR(191) DEFAULT NULL");
                $wpdb->query("ALTER TABLE $at ADD UNIQUE KEY uq_slug (custom_slug)");
            }
        }

        // Backfill cl_customers from existing licenses (optimized for thousands of records)
        $ct = $wpdb->prefix . 'cl_customers';
        
        // Pure SQL insertion ignoring duplicates where email/wa match:
        $wpdb->query("
            INSERT INTO $ct (user_id, name, email, wa_number, created_at)
            SELECT l.user_id, MAX(l.assignee_name), l.assignee_email, l.assignee_wa, IFNULL(MIN(l.created_at), NOW())
            FROM $lt l
            WHERE (l.assignee_email != '' OR l.assignee_wa != '')
              AND NOT EXISTS (
                  SELECT 1 FROM $ct c 
                  WHERE c.user_id = l.user_id 
                    AND ((c.email = l.assignee_email AND l.assignee_email != '') OR (c.wa_number = l.assignee_wa AND l.assignee_wa != ''))
              )
            GROUP BY l.user_id, l.assignee_email, l.assignee_wa
        ");
        
        // Insertion for those with name only
        $wpdb->query("
            INSERT INTO $ct (user_id, name, email, wa_number, created_at)
            SELECT l.user_id, l.assignee_name, '', '', IFNULL(MIN(l.created_at), NOW())
            FROM $lt l
            WHERE l.assignee_email = '' AND l.assignee_wa = '' AND l.assignee_name != ''
              AND NOT EXISTS (
                  SELECT 1 FROM $ct c 
                  WHERE c.user_id = l.user_id AND c.name = l.assignee_name AND c.email = '' AND c.wa_number = ''
              )
            GROUP BY l.user_id, l.assignee_name
        ");

        update_option('cl_db_version', CL_VERSION);
    }
}
