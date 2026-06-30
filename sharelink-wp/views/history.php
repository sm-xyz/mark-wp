<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) wp_die('No access');

global $wpdb;
$uid = get_current_user_id();
$ht = $wpdb->prefix . 'cl_history';

// Handle Mass Delete
if (isset($_POST['cl_delete_history']) && isset($_POST['hist_ids']) && is_array($_POST['hist_ids'])) {
    $ids = array_map('intval', $_POST['hist_ids']);
    $ids_str = implode(',', $ids);
    $wpdb->query("DELETE FROM $ht WHERE id IN ($ids_str) AND user_id = $uid");
    echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Histori yang dipilih berhasil dihapus.', 'success'));</script>";
}

// Handle Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $where = $wpdb->prepare("user_id = %d", $uid);
    if (!empty($_GET['fdate'])) {
        $date = sanitize_text_field($_GET['fdate']);
        $where .= $wpdb->prepare(" AND DATE(created_at) = %s", $date);
    }
    
    $rows = $wpdb->get_results("SELECT * FROM $ht WHERE $where ORDER BY id DESC");
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=riwayat_'.date('Ymd_His').'.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Tipe', 'Pesan', 'Tanggal']);
    foreach($rows as $r) {
        fputcsv($out, [$r->id, $r->type, $r->message, $r->created_at]);
    }
    fclose($out);
    exit;
}

// Data fetching setup
$where = $wpdb->prepare("user_id = %d", $uid);
$filter_date = '';
if (!empty($_GET['fdate'])) {
    $filter_date = sanitize_text_field($_GET['fdate']);
    $where .= $wpdb->prepare(" AND DATE(created_at) = %s", $filter_date);
}

// Paging
$limit = 20;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $limit;

$total_rows = $wpdb->get_var("SELECT COUNT(id) FROM $ht WHERE $where");
$all_hist = $wpdb->get_results("SELECT * FROM $ht WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset");
$total_pages = ceil($total_rows / $limit);
?>

<div class="max-w-6xl mx-auto pb-10">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <h2 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2">
                <div class="p-2 bg-indigo-100 text-indigo-600 rounded-lg">
                    <i data-lucide="history" class="w-6 h-6"></i>
                </div>
                Riwayat & Log
            </h2>
            <p class="text-sm text-slate-500 mt-1">Daftar notifikasi & aktivitas. Data lama akan otomatis dihapus dalam 7 hari.</p>
        </div>
        
        <div class="flex items-center gap-3 w-full md:w-auto">
            <!-- Filter form -->
            <form method="GET" class="flex gap-2 w-full md:w-auto">
                <input type="hidden" name="page" value="canvaslock">
                <input type="hidden" name="view" value="history">
                <input type="date" name="fdate" value="<?= esc_attr($filter_date) ?>" class="px-4 py-2 bg-white border border-slate-200 rounded-xl text-sm outline-none focus:border-brand w-full md:w-40 min-w-[130px]" title="Filter Tanggal">
                <button type="submit" class="bg-white border border-slate-200 text-slate-600 hover:text-brand px-4 py-2 rounded-xl text-sm font-semibold transition-all">Filter</button>
                <?php if ($filter_date): ?>
                <a href="<?= admin_url('admin.php?page=canvaslock&view=history') ?>" class="bg-red-50 text-red-500 hover:bg-red-100 px-4 py-2 rounded-xl text-sm font-semibold transition-all">Clear</a>
                <?php endif; ?>
            </form>
            
            <!-- Export button -->
            <a href="<?= admin_url('admin.php?page=canvaslock&view=history&export=csv&fdate='.esc_attr($filter_date)) ?>" class="bg-emerald-50 text-emerald-600 hover:bg-emerald-100 px-4 py-2 rounded-xl text-sm font-semibold transition-all flex items-center gap-2 whitespace-nowrap">
                <i data-lucide="download" class="w-4 h-4"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <form method="POST" id="mass-delete-form" onsubmit="return confirm('Yakin ingin menghapus riwayat yang dipilih?');">
            <?php if (count($all_hist) > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap lg:whitespace-normal text-sm">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-200 uppercase text-[11px] font-bold tracking-wider text-slate-400">
                            <th class="py-4 px-6 w-12 text-center">
                                <input type="checkbox" id="check-all" class="rounded border-slate-300 text-brand focus:ring-brand w-4 h-4" onclick="document.querySelectorAll('.check-item').forEach(c => c.checked = this.checked); toggleMassDeleteBtn();">
                            </th>
                            <th class="py-4 px-6 w-32 border-l border-slate-200">Tanggal</th>
                            <th class="py-4 px-6 min-w-[200px] border-l border-slate-200">Pesan</th>
                            <th class="py-4 px-6 w-24 text-center border-l border-slate-200">Tipe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        <?php foreach($all_hist as $h): 
                            $isSuccess = $h->type === 'success';
                        ?>
                        <tr class="hover:bg-slate-50/50 transition-colors group">
                            <td class="py-3 px-6 text-center">
                                <input type="checkbox" name="hist_ids[]" value="<?= $h->id ?>" class="check-item rounded border-slate-300 text-brand focus:ring-brand w-4 h-4" onchange="toggleMassDeleteBtn()">
                            </td>
                            <td class="py-3 px-6 border-l border-slate-50 text-slate-500 whitespace-nowrap shrink-0 text-xs font-mono">
                                <?= esc_html(wp_date('d M Y, H:i', strtotime($h->created_at))) ?>
                            </td>
                            <td class="py-3 px-6 border-l border-slate-50">
                                <?= esc_html($h->message) ?>
                            </td>
                            <td class="py-3 px-6 text-center border-l border-slate-50">
                                <?php if ($isSuccess): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-600 border border-emerald-100">
                                        <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Sukses
                                    </span>
                                <?php elseif ($h->type === 'error'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-600 border border-red-100">
                                        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> Error
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-600 border border-blue-100">
                                        <i data-lucide="info" class="w-3.5 h-3.5"></i> Info
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Footer -->
            <div class="px-6 py-4 bg-slate-50/80 border-t border-slate-200 flex flex-col md:flex-row items-center justify-between gap-4">
                <button type="submit" name="cl_delete_history" id="mass-delete-btn" disabled class="bg-white hover:bg-red-50 border border-slate-200 hover:border-red-200 text-slate-400 hover:text-red-500 font-semibold py-2 px-4 rounded-xl text-sm transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    <i data-lucide="trash-2" class="w-4 h-4"></i> Hapus Terpilih
                </button>
                
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center gap-1 bg-white border border-slate-200 rounded-xl p-1 shadow-sm">
                    <?php 
                    $base_url = admin_url('admin.php?page=canvaslock&view=history&fdate='.esc_attr($filter_date));
                    for ($i = 1; $i <= $total_pages; $i++): 
                        $active = $i == $page ? 'bg-brand text-white font-bold' : 'text-slate-600 hover:bg-slate-100 font-medium';
                    ?>
                    <a href="<?= $base_url . '&paged=' . $i ?>" class="px-3 py-1.5 rounded-lg text-sm <?= $active ?> transition-colors"><?= $i ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
                <div class="py-16 text-center">
                    <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100">
                        <i data-lucide="history" class="w-10 h-10 text-slate-300"></i>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 mb-1">Riwayat Kosong</h3>
                    <p class="text-slate-500 max-w-sm mx-auto text-sm">Tidak ada riwayat aktivitas yang tersimpan. Segala notifikasi terbaru akan muncul di sini.</p>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
function toggleMassDeleteBtn() {
    const anyChecked = document.querySelectorAll('.check-item:checked').length > 0;
    const btn = document.getElementById('mass-delete-btn');
    if (btn) {
        btn.disabled = !anyChecked;
        if(anyChecked) {
            btn.classList.add('text-red-500', 'border-red-200', 'bg-red-50');
            btn.classList.remove('text-slate-400');
        } else {
            btn.classList.remove('text-red-500', 'border-red-200', 'bg-red-50');
            btn.classList.add('text-slate-400');
        }
    }
}
</script>
