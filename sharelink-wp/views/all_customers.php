<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    echo '<p>Anda tidak memiliki akses ke halaman ini.</p>';
    exit;
}

global $wpdb;
$at = $wpdb->prefix . CL_APPS;
$lt = $wpdb->prefix . CL_LICS;

$ct = $wpdb->prefix . 'cl_customers';

$s = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Query all customers across all workspace subscribers
$paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($paged - 1) * $per_page;

$where_clause = "1=1";
if ($s) {
    $where_clause .= $wpdb->prepare(" AND (c.name LIKE %s OR c.email LIKE %s OR c.wa_number LIKE %s OR u.display_name LIKE %s)", 
        '%' . $wpdb->esc_like($s) . '%', '%' . $wpdb->esc_like($s) . '%', '%' . $wpdb->esc_like($s) . '%', '%' . $wpdb->esc_like($s) . '%');
}

$total_customers = $wpdb->get_var("SELECT COUNT(c.id) FROM $ct c LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID WHERE $where_clause");
$total_pages = ceil($total_customers / $per_page);

$query = "SELECT c.*, u.display_name as subscriber_name, u.user_email as subscriber_email,
          (SELECT COUNT(*) FROM $lt l WHERE l.user_id = c.user_id AND ((l.assignee_email = c.email AND c.email != '') OR (l.assignee_wa = c.wa_number AND c.wa_number != '') OR (l.assignee_name = c.name AND c.name != ''))) as active_lics
          FROM $ct c
          LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID
          WHERE $where_clause
          ORDER BY c.id DESC LIMIT $per_page OFFSET $offset";

$customers = $wpdb->get_results($query);
?>

<div class="max-w-7xl mx-auto pb-12">
    <div class="bg-blue-50 border border-brand/20 rounded-2xl p-5 flex items-start justify-between mb-6 flex-col sm:flex-row gap-4">
        <div class="flex items-start">
            <i data-lucide="user-check" class="w-6 h-6 text-brand mr-4 shrink-0 mt-0.5"></i>
            <div>
                <h3 class="font-bold text-brand mb-1">Daftar Seluruh App User (System-Wide)</h3>
                <p class="text-sm text-slate-700 leading-relaxed max-w-2xl">
                    Halaman ini menampilkan seluruh kontak pengguna aplikasi (app user) dari seluruh member subscriber di dalam sistem Sharelink AI.
                </p>
            </div>
        </div>
        
        <a href="<?= add_query_arg('cl_export_customers', 'csv', admin_url('admin.php?page=canvaslock')) ?>" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-bold shadow-sm transition-all flex items-center justify-center gap-1.5 shrink-0">
            <i data-lucide="download" class="w-4 h-4"></i> Export CSV
        </a>
    </div>

    <!-- Main Table Container -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden anim_fade">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i data-lucide="users" class="w-5 h-5 mr-3 text-brand"></i> Data All App User
            </h2>
            <form class="flex gap-2 text-sm max-w-xs w-full" method="get">
                <input type="hidden" name="page" value="canvaslock">
                <input type="hidden" name="view" value="all_customers">
                <input type="text" name="s" value="<?= esc_attr($s) ?>" placeholder="Cari nama / email / WA..." class="flex-1 w-full border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                <button type="submit" class="bg-brand text-white px-3 py-2 rounded-lg font-semibold hover:bg-[#002b6b] transition text-xs"><i data-lucide="search" class="w-4 h-4"></i></button>
            </form>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-slate-500 text-xs uppercase tracking-widest font-semibold">
                        <th class="px-6 py-4">Nama Pelanggan</th>
                        <th class="px-6 py-4">Email</th>
                        <th class="px-6 py-4">Kontak WA</th>
                        <th class="px-6 py-4">Workspace Subscriber</th>
                        <th class="px-6 py-4 text-center">Ditambahkan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if ($customers): foreach($customers as $c): ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-850"><?= esc_html($c->name) ?></div>
                            <?php if ($c->active_lics > 0): ?>
                                <span class="inline-block mt-1 px-2 py-0.5 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded text-[10px] font-bold"><i data-lucide="key" class="inline w-3 h-3 mr-1"></i> <?= $c->active_lics ?> Lisensi Aktif</span>
                            <?php else: ?>
                                <span class="inline-block mt-1 px-2 py-0.5 bg-slate-100 text-slate-500 border border-slate-200 rounded text-[10px] font-bold">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-slate-600 font-medium">
                            <?= esc_html($c->email ?: '-') ?>
                        </td>
                        <td class="px-6 py-4 text-slate-600 font-medium font-mono">
                            <?= esc_html($c->wa_number ?: '-') ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-semibold text-brand"><?= esc_html($c->subscriber_name ?: 'Admin') ?></div>
                            <div class="text-[10px] text-slate-500"><?= esc_html($c->subscriber_email ?: 'admin') ?></div>
                        </td>
                        <td class="px-6 py-4 text-center text-xs text-slate-500 font-medium">
                            <?= date('d M Y', strtotime($c->created_at)) ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada data customer terdaftar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="px-5 py-3 border-t border-slate-100 bg-white flex justify-between items-center shrink-0">
            <span class="text-xs text-slate-500">Hal <?= $paged ?> dari <?= $total_pages ?></span>
            <div class="flex gap-1">
                <?php if ($paged > 1): ?>
                    <a href="?page=canvaslock&view=all_customers&paged=<?= $paged-1 ?>&s=<?= esc_attr($s) ?>" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-xs font-semibold transition-colors">Prev</a>
                <?php endif; ?>
                <?php if ($paged < $total_pages): ?>
                    <a href="?page=canvaslock&view=all_customers&paged=<?= $paged+1 ?>&s=<?= esc_attr($s) ?>" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-xs font-semibold transition-colors">Next</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
