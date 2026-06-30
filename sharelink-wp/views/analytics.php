<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

$uid = get_current_user_id();
$log_table = $wpdb->prefix . 'cl_api_logs';
$app_table = $wpdb->prefix . CL_APPS;
$lic_table = $wpdb->prefix . CL_LICS;

// Total Requests (All time for this user)
$total_requests = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $log_table WHERE user_id=%d", $uid));

// Top Apps
$top_apps = $wpdb->get_results($wpdb->prepare("
    SELECT a.app_name, COUNT(l.id) as req_count 
    FROM $log_table l 
    JOIN $app_table a ON l.app_id = a.id 
    WHERE l.user_id=%d 
    GROUP BY l.app_id 
    ORDER BY req_count DESC 
    LIMIT 5
", $uid));

// Recent Logs
$recent_logs = $wpdb->get_results($wpdb->prepare("
    SELECT l.*, a.app_name, lic.license_key 
    FROM $log_table l
    LEFT JOIN $app_table a ON l.app_id = a.id
    LEFT JOIN $lic_table lic ON l.license_id = lic.id
    WHERE l.user_id=%d 
    ORDER BY l.created_at DESC 
    LIMIT 20
", $uid));

// Today's requests
$today_start = date('Y-m-d 00:00:00', current_time('timestamp'));
$today_reqs = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $log_table WHERE user_id=%d AND created_at >= %s", $uid, $today_start));

$lics_used = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $lic_table WHERE user_id=%d", $uid));
$now = current_time('mysql');
$active_lics = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $lic_table WHERE status='active' AND (expires_at IS NULL OR expires_at > %s) AND user_id=%d", $now, $uid));
$inactive_lics = $lics_used - $active_lics;

$quota_limit = (int)get_user_meta($uid, 'cl_quota_limit', true);
if ($quota_limit < 1) $quota_limit = 100;

$quota_history = get_user_meta($uid, 'cl_quota_history', true) ?: [];
$quota_history = array_reverse($quota_history); // Newest first
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
        <div class="text-sm font-medium text-slate-500 mb-2">Total API Verifikasi</div>
        <div class="text-3xl font-bold text-slate-800"><?= number_format($total_requests) ?></div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
        <div class="text-sm font-medium text-slate-500 mb-2">API Verifikasi Hari Ini</div>
        <div class="text-3xl font-bold text-slate-800"><?= number_format($today_reqs) ?></div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
        <div class="text-sm font-medium text-slate-500 mb-2">Lisensi Terpakai</div>
        <div class="text-3xl font-bold text-accent"><?= number_format($lics_used) ?> <span class="text-lg text-slate-400">/ <?= number_format($quota_limit) ?></span></div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
        <div class="text-sm font-medium text-slate-500 mb-2">Lisensi Nonaktif</div>
        <div class="text-3xl font-bold text-slate-800"><?= number_format($inactive_lics) ?></div>
    </div>
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-200">
        <div class="text-sm font-medium text-slate-500 mb-2">Sisa Kuota Lisensi</div>
        <div class="text-3xl font-bold text-emerald-600"><?= number_format(max(0, $quota_limit - $lics_used)) ?></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
    <div class="lg:col-span-2 space-y-8">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-bold text-slate-800 flex items-center">
                    <i data-lucide="activity" class="w-5 h-5 mr-2 text-brand"></i> Histori Aktivitas API
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[700px]">
                    <thead>
                        <tr class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                            <th class="p-4 font-semibold border-b border-slate-200">Waktu</th>
                            <th class="p-4 font-semibold border-b border-slate-200">Aplikasi</th>
                            <th class="p-4 font-semibold border-b border-slate-200">Lisensi</th>
                            <th class="p-4 font-semibold border-b border-slate-200">IP & Origin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if ($recent_logs): foreach($recent_logs as $log): ?>
                        <tr class="hover:bg-slate-50/50">
                            <td class="p-4 text-slate-500 whitespace-nowrap">
                                <?= date('d M Y, H:i', strtotime($log->created_at)) ?>
                            </td>
                            <td class="p-4 font-medium text-slate-800">
                                <?= esc_html($log->app_name ?: 'Unknown') ?>
                            </td>
                            <td class="p-4">
                                <span class="font-mono text-xs bg-slate-100 px-2 py-1 rounded text-slate-600">
                                    <?= esc_html($log->license_key ?: 'Unknown') ?>
                                </span>
                            </td>
                            <td class="p-4 text-xs text-slate-500">
                                <div class="truncate w-40" title="<?= esc_attr($log->origin) ?>"><?= esc_html($log->origin ?: 'No Origin') ?></div>
                                <div class="mt-1 text-slate-400 font-mono"><?= esc_html($log->ip_address) ?></div>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="4" class="p-8 text-center text-slate-500">Belum ada aktivitas verifikasi API lisensi.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <h2 class="font-bold text-slate-800 flex items-center">
                    <i data-lucide="history" class="w-5 h-5 mr-2 text-brand"></i> Histori Top-Up Kuota Lisensi
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wider">
                            <th class="p-4 font-semibold border-b border-slate-200">Waktu Penambahan</th>
                            <th class="p-4 font-semibold border-b border-slate-200">Jumlah Kuota Ditambah</th>
                            <th class="p-4 font-semibold border-b border-slate-200">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <?php if ($quota_history): foreach($quota_history as $qh): ?>
                        <tr class="hover:bg-slate-50/50">
                            <td class="p-4 text-slate-500 whitespace-nowrap">
                                <?= date('d M Y, H:i', strtotime($qh['date'])) ?>
                            </td>
                            <td class="p-4 font-bold text-emerald-600">
                                +<?= isset($qh['amount']) ? intval($qh['amount']) : 'Unknown' ?> Lisensi
                            </td>
                            <td class="p-4">
                                <span class="bg-emerald-50 text-emerald-600 border border-emerald-200 px-2 py-1 rounded text-xs font-semibold">Disetujui</span>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="3" class="p-8 text-center text-slate-500">Belum ada histori penambahan kuota. Pembelian kuota pertama akan muncul di sini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="lg:col-span-1 bg-white rounded-2xl border border-slate-200 shadow-sm p-5 lg:sticky lg:top-8">
        <h2 class="font-bold text-slate-800 flex items-center mb-4">
            <i data-lucide="bar-chart" class="w-5 h-5 mr-2 text-brand"></i> Top Aplikasi Tersibuk
        </h2>
        
        <?php if ($top_apps): ?>
            <div class="space-y-4">
            <?php foreach($top_apps as $ta): ?>
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-medium text-slate-700"><?= esc_html($ta->app_name) ?></span>
                        <span class="text-slate-500 font-bold"><?= number_format($ta->req_count) ?></span>
                    </div>
                    <?php $pct = $total_requests > 0 ? min(100, round(($ta->req_count / $total_requests) * 100)) : 0; ?>
                    <div class="w-full bg-slate-100 rounded-full h-2">
                        <div class="bg-brand h-2 rounded-full" style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-6 text-slate-400 text-sm">Tidak ada data penggunaan API yang tercatat.</div>
        <?php endif; ?>
    </div>
</div>
