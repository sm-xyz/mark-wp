<?php
if (!defined('ABSPATH')) exit;

global $wpdb;
$ct = $wpdb->prefix . 'cl_customers';
$lt = $wpdb->prefix . CL_LICS;
$at = $wpdb->prefix . CL_APPS;
$uid = get_current_user_id();
$is_admin = current_user_can('manage_options');

// Delete customer handler
if (isset($_POST['cl_delete_customer']) && isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_del_cust')) {
    $cid = intval($_POST['cl_delete_customer']);
    $cdata = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ct WHERE id=%d AND user_id=%d", $cid, $uid));
    if ($cdata) {
        // Unassign licenses that belong to this customer
        $wpdb->update($lt, [
            'assignee_name' => '',
            'assignee_email'=> '',
            'assignee_wa'   => ''
        ], [
            'user_id' => $uid,
            'assignee_email' => $cdata->email,
            'assignee_wa' => $cdata->wa_number
        ]);
        
        $wpdb->delete($ct, ['id' => $cid]);
        cl_insert_history($uid, "Kontak App User {$cdata->name} telah dihapus, dan semua lisensinya dicabut.");
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Kontak App User berhasil dihapus. Lisensinya telah di-unassign.'));</script>";
    }
}

// Unassign customer handler
if (isset($_POST['cl_unassign_customer']) && isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_del_cust')) {
    $cid = intval($_POST['cl_unassign_customer']);
    $cdata = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ct WHERE id=%d AND user_id=%d", $cid, $uid));
    if ($cdata) {
        $wpdb->query($wpdb->prepare("UPDATE $lt SET assignee_name='', assignee_email='', assignee_wa='' WHERE user_id=%d AND (assignee_email=%s OR assignee_wa=%s OR assignee_name=%s)", $uid, $cdata->email, $cdata->wa_number, $cdata->name));
        cl_insert_history($uid, "Seluruh lisensi untuk pengguna {$cdata->name} berhasil dicabut.");
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Seluruh Lisensi App User ini berhasil dicabut (Unassigned).'));</script>";
    }
}

// Edit customer handler
if (isset($_POST['cl_edit_customer']) && isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_edit_cust')) {
    $cid = intval($_POST['edit_cid']);
    $name = sanitize_text_field($_POST['edit_name']);
    $email = sanitize_email($_POST['edit_email']);
    $wa = cl_normalize_wa(sanitize_text_field($_POST['edit_wa']));
    
    $cdata = $wpdb->get_row($wpdb->prepare("SELECT * FROM $ct WHERE id=%d AND user_id=%d", $cid, $uid));
    if ($cdata) {
        $wpdb->update($ct, [
            'name' => $name,
            'email' => $email,
            'wa_number' => $wa
        ], ['id' => $cid]);
        
        // Update associated licenses
        if (!empty($cdata->email) || !empty($cdata->wa_number) || !empty($cdata->name)) {
            $wpdb->query($wpdb->prepare("UPDATE $lt SET assignee_name=%s, assignee_email=%s, assignee_wa=%s WHERE user_id=%d AND (assignee_email=%s OR assignee_wa=%s OR assignee_name=%s)", $name, $email, $wa, $uid, $cdata->email, $cdata->wa_number, $cdata->name));
        }
        
        cl_insert_history($uid, "Data kontak App User {$cdata->name} diperbarui menjadi: $name ($email).");
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Data pelanggan berhasil diupdate.'));</script>";
    }
}

// Bulk Import Handlers
if (isset($_POST['cl_import_customers']) && isset($_FILES['csv_file']) && wp_verify_nonce($_POST['_clnonce'], 'cl_imp_cust')) {
    $quota_limit = (int)get_user_meta($uid, 'cl_quota_limit', true) ?: 100;
    $max_customers = $quota_limit * 2;
    $current_count = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ct WHERE user_id=%d", $uid));
    
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    $imported = 0;
    $failed = 0;
    $header_skipped = false;
    
    if ($handle !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (!$header_skipped) { $header_skipped = true; continue; }
            if (empty($data[0]) && empty($data[1]) && empty($data[2])) continue;
            
            if ($current_count + $imported >= $max_customers) {
                break; // Stop importing if max customer capacity reached
            }

            $name = sanitize_text_field($data[0] ?? 'Pelanggan');
            $ema = sanitize_email($data[1] ?? '');
            $wa = cl_normalize_wa(sanitize_text_field($data[2] ?? ''));
            
            // Check existing
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE user_id=%d AND ((email=%s AND email != '') OR (wa_number=%s AND wa_number != ''))", $uid, $ema, $wa));
            if (!$exists) {
                $wpdb->insert($ct, [
                    'user_id' => $uid,
                    'name' => $name,
                    'email' => $ema,
                    'wa_number' => $wa,
                    'created_at' => current_time('mysql')
                ]);
                $imported++;
            } else {
                $failed++;
            }
        }
        fclose($handle);
        cl_insert_history($uid, "Import CSV App User: $imported data kontak baru berhasil ditambahkan " . ($failed > 0 ? "($failed duplikat dilewati)" : '') . ".");
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('$imported data berhasil di-import. " . ($failed ? "($failed duplikat diabaikan)" : "") . "'));</script>";
    }
}


$s = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Calculate License Quota vs Customer Quota
$quota_limit = (int)get_user_meta($uid, 'cl_quota_limit', true);
if ($quota_limit < 1) $quota_limit = 100;
$device_quota = $quota_limit * 2;
$current_users = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $ct WHERE user_id=%d", $uid));

// Query strictly for the current logged-in user (member)
$query = $wpdb->prepare("SELECT c.*, 
    (SELECT COUNT(*) FROM $lt l WHERE l.user_id = c.user_id AND ((l.assignee_email = c.email AND c.email != '') OR (l.assignee_wa = c.wa_number AND c.wa_number != '') OR (l.assignee_name = c.name AND c.name != ''))) as active_lics
    FROM $ct c WHERE c.user_id = %d", $uid);

if ($s) {
    $query .= $wpdb->prepare(" AND (c.name LIKE %s OR c.email LIKE %s OR c.wa_number LIKE %s)", 
        '%' . $wpdb->esc_like($s) . '%', '%' . $wpdb->esc_like($s) . '%', '%' . $wpdb->esc_like($s) . '%');
}

$query .= " ORDER BY c.id DESC";
$customers = $wpdb->get_results($query);
?>

<div class="max-w-7xl mx-auto pb-12">
    <div class="bg-blue-50 border border-brand/20 rounded-2xl p-5 flex items-start justify-between mb-6 flex-col sm:flex-row gap-4">
        <div class="flex items-start">
            <i data-lucide="users" class="w-6 h-6 text-brand mr-4 shrink-0 mt-0.5"></i>
            <div>
                <h3 class="font-bold text-brand mb-1">Database App User (Pelanggan)</h3>
                <p class="text-sm text-slate-700 leading-relaxed max-w-2xl mb-2">
                    Halaman ini menampilkan semua kontak pengguna aplikasi Anda. Data App User berdiri mandiri dan independen dari lisensi Anda.
                </p>
                <div class="inline-flex items-center text-[11px] font-bold bg-white text-slate-700 border border-slate-200 px-3 py-1.5 rounded-lg shadow-sm">
                    <i data-lucide="database" class="w-3.5 h-3.5 mr-1.5 text-blue-500"></i>
                    Kapasitas Database: <?= $current_users ?> / <?= $device_quota ?> Data 
                    <span class="text-slate-400 font-normal ml-1">(2x Lipat dari Kuota Lisensi / <?= $quota_limit ?>)</span>
                </div>
            </div>
        </div>
        
        <div class="flex gap-2 shrink-0 flex-wrap">
            <button onclick="document.getElementById('import-modal').classList.remove('hidden')" class="px-5 py-2.5 bg-brand hover:bg-[#002b6b] text-white rounded-xl text-sm font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                <i data-lucide="upload" class="w-4 h-4"></i> Import CSV
            </button>
            <a href="<?= add_query_arg('cl_export_customers', 'csv', admin_url('admin.php?page=canvaslock')) ?>" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                <i data-lucide="download" class="w-4 h-4"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="import-modal" class="hidden fixed inset-0 z-[99] bg-slate-900/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl border border-slate-100">
            <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center"><i data-lucide="file-spreadsheet" class="w-5 h-5 mr-2 text-brand"></i> Import Data App User</h3>
            <p class="text-xs text-slate-500 mb-5 leading-relaxed">Pilih file CSV yang berisi data pelanggan. Proses ini HANYA akan menyimpan kontak pelanggan ke daftar tanpa men-generate lisensi (untuk mencegah pengiriman WA spam massal/banned). Format Kolom: <b class="text-slate-700">Nama Lengkap, Email, Nomor WA</b> (Tanpa header row, minimal 1 baris terisi).</p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('cl_imp_cust', '_clnonce'); ?>
                <div class="mb-4">
                    <input type="file" name="csv_file" accept=".csv" required class="w-full border border-slate-200 rounded-lg p-2 text-sm bg-slate-50">
                    <p class="text-[10px] text-slate-400 mt-1.5">Kapasitas Maksimal: <?= $device_quota ?> kontak total (Sisa: <?= max(0, $device_quota - $current_users) ?> slot import).</p>
                </div>
                <div class="flex justify-end gap-2 mt-6">
                    <button type="button" onclick="document.getElementById('import-modal').classList.add('hidden')" class="px-4 py-2 border border-slate-200 text-slate-600 rounded-lg text-sm font-bold hover:bg-slate-50">Batal</button>
                    <button type="submit" name="cl_import_customers" class="px-4 py-2 bg-brand text-white rounded-lg text-sm font-bold hover:bg-[#002b6b] shadow-sm flex items-center"><i data-lucide="upload" class="inline w-4 h-4 mr-2"></i> Mulai Import</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Table Container -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden animate-fade-in">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i data-lucide="users" class="w-5 h-5 mr-3 text-brand"></i> Data App User
            </h2>
            <form class="flex gap-2 text-sm max-w-xs w-full" method="get">
                <input type="hidden" name="page" value="canvaslock">
                <input type="hidden" name="view" value="customer_members">
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
                        <th class="px-6 py-4 text-center">Ditambahkan</th>
                        <th class="px-6 py-4 text-right">Aksi</th>
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
                        <td class="px-6 py-4 text-center text-xs text-slate-500 font-medium">
                            <?= date('d M Y', strtotime($c->created_at)) ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <?php if (!empty($c->email)): ?>
                                    <a href="mailto:<?= esc_attr($c->email) ?>" class="inline-flex items-center gap-1 px-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded text-xs font-bold transition-all border border-blue-100 shadow-sm leading-8 h-8" title="Kirim Email">
                                        <i data-lucide="mail" class="w-4 h-4 mt-2 mb-2"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($c->wa_number)): 
                                    $wa_clean = preg_replace('/[^0-9]/', '', $c->wa_number);
                                ?>
                                    <a href="https://wa.me/<?= esc_attr($wa_clean) ?>" target="_blank" class="inline-flex items-center gap-1 px-2 bg-emerald-50 text-emerald-600 hover:bg-emerald-100 rounded text-xs font-bold transition-all border border-emerald-100 shadow-sm leading-8 h-8" title="Kirim WA">
                                        <i data-lucide="message-square" class="w-4 h-4 mt-2 mb-2"></i>
                                    </a>
                                <?php endif; ?>
                                <button type="button" onclick="openEditCustModal(<?= $c->id ?>, '<?= esc_js($c->name) ?>', '<?= esc_js($c->email) ?>', '<?= esc_js($c->wa_number) ?>')" class="inline-flex items-center gap-1 px-2 bg-slate-50 text-slate-600 hover:bg-slate-200 rounded text-xs font-bold transition-all border border-slate-200 shadow-sm leading-8 h-8" title="Edit App User">
                                    <i data-lucide="edit" class="w-4 h-4 mt-2 mb-2"></i>
                                </button>
                                <?php if ($c->active_lics > 0): ?>
                                <form method="post" onsubmit="return confirm('Cabut semua lisensi dari pengguna ini?');" class="inline">
                                    <?php wp_nonce_field('cl_del_cust', '_clnonce'); ?>
                                    <input type="hidden" name="cl_unassign_customer" value="<?= $c->id ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2 bg-orange-50 text-orange-500 hover:bg-orange-100 hover:text-orange-700 rounded text-xs font-bold transition-all border border-orange-100 shadow-sm leading-8 h-8" title="Unassign / Cabut Lisensi">
                                        <i data-lucide="user-x" class="w-4 h-4 mt-2 mb-2"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="post" onsubmit="return confirm('Yakin ingin menghapus kontak ini? (Lisensi milik kontak ini akan menjadi Unassigned)');" class="inline">
                                    <?php wp_nonce_field('cl_del_cust', '_clnonce'); ?>
                                    <input type="hidden" name="cl_delete_customer" value="<?= $c->id ?>">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2 bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-700 rounded text-xs font-bold transition-all border border-red-100 shadow-sm leading-8 h-8" title="Hapus">
                                        <i data-lucide="trash-2" class="w-4 h-4 mt-2 mb-2"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada data customer terdaftar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="edit-cust-modal" class="hidden fixed inset-0 z-[99] bg-slate-900/50 flex items-center justify-center p-4 opacity-0 transition-opacity duration-200">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl border border-slate-100 transition-transform duration-200 scale-95" id="edit-cust-content">
        <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center"><i data-lucide="edit" class="w-5 h-5 mr-2 text-blue-500"></i> Edit App User</h3>
        <p class="text-[13px] text-slate-500 mb-5 leading-relaxed">Perubahan pada data ini juga akan diterapkan pada lisensi yang sedang di-assign ke pengguna ini.</p>
        <form method="post">
            <?php wp_nonce_field('cl_edit_cust', '_clnonce'); ?>
            <input type="hidden" name="cl_edit_customer" value="1">
            <input type="hidden" name="edit_cid" id="edit_cid">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Lengkap</label>
                <input type="text" name="edit_name" id="edit_name" class="w-full border border-slate-200 rounded-lg p-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 text-slate-800 font-medium">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Email</label>
                <input type="email" name="edit_email" id="edit_email" class="w-full border border-slate-200 rounded-lg p-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 text-slate-800 font-medium">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1">Nomor WhatsApp</label>
                <input type="text" name="edit_wa" id="edit_wa" class="w-full border border-slate-200 rounded-lg p-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 text-slate-800 font-medium">
            </div>
            
            <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                <button type="button" onclick="closeEditCustModal()" class="px-4 py-2 bg-slate-200 text-slate-600 rounded-lg text-sm font-bold hover:bg-slate-300 transition-colors">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 transition-colors text-white rounded-lg text-sm font-bold shadow-sm flex items-center"><i data-lucide="save" class="w-4 h-4 mr-2"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCustModal(id, name, email, wa) {
    document.getElementById('edit_cid').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_wa').value = wa;
    
    const modal = document.getElementById('edit-cust-modal');
    const content = document.getElementById('edit-cust-content');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
    }, 10);
}

function closeEditCustModal() {
    const modal = document.getElementById('edit-cust-modal');
    const content = document.getElementById('edit-cust-content');
    modal.classList.add('opacity-0');
    content.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}
</script>

