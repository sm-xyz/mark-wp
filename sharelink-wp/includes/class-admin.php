<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'cl_admin_menu');
function cl_admin_menu() {
    add_menu_page(
        'Sharelink AI', 
        'Sharelink AI', 
        'read', 
        'canvaslock', 
        'cl_render_layout', 
        'dashicons-shield', 
        3 
    );
}

add_action('admin_init', 'cl_admin_restrictions');
function cl_admin_restrictions() {
    if (wp_doing_ajax()) return;
    
    if (!current_user_can('manage_options')) {
        $page = isset($_GET['page']) ? $_GET['page'] : '';
        if ($page !== 'canvaslock') {
            wp_redirect(admin_url('admin.php?page=canvaslock'));
            exit;
        }
        show_admin_bar(false);
    }
}

add_action('admin_init', 'cl_export_licenses_csv');
function cl_export_licenses_csv() {
    if (isset($_GET['cl_export']) && $_GET['cl_export'] === 'csv') {
        if (!is_user_logged_in()) wp_die('No access');
        global $wpdb;
        $uid = get_current_user_id();
        $lt = $wpdb->prefix . CL_LICS;
        
        $where = $wpdb->prepare("user_id = %d", $uid);
        if (!empty($_GET['fapp'])) $where .= $wpdb->prepare(" AND app_id = %d", intval($_GET['fapp']));
        if (!empty($_GET['fs'])) $where .= $wpdb->prepare(" AND status = %s", sanitize_text_field($_GET['fs']));
        
        $lics = $wpdb->get_results("SELECT * FROM $lt WHERE $where ORDER BY id DESC");
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=lisensi_'.date('Ymd_His').'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['License Key', 'App ID', 'Status', 'Usage Count', 'Label', 'Created At', 'Last Used', 'Expires At', 'Fingerprint']);
        foreach($lics as $l) {
            fputcsv($out, [$l->license_key, $l->app_id, $l->status, $l->usage_count, $l->label, $l->created_at, $l->last_used, $l->expires_at, $l->device_fingerprint]);
        }
        fclose($out);
        exit;
    }
}

add_filter('login_redirect', 'cl_login_redirect', 10, 3);
function cl_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (!in_array('administrator', $user->roles)) {
            return admin_url('admin.php?page=canvaslock');
        }
    }
    return $redirect_to;
}

function cl_render_layout() {
    require_once CL_PLUGIN_DIR . 'views/layout.php';
}
