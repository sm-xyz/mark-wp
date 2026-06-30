<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$lt = $wpdb->prefix . CL_LICS; $at = $wpdb->prefix . CL_APPS;
$uid = get_current_user_id();

// BATCH Generate
if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_batch_gen'])) {
    $qty = intval($_POST['qty'] ?? 1);
    $aid = intval($_POST['app_id'] ?? 0);
    $lbl = sanitize_text_field($_POST['label'] ?? '');
    $expires = sanitize_text_field($_POST['expires_at'] ?? '');
    $max_dev = intval($_POST['max_devices'] ?? 1);
    
    $expires_at = null;
    if ($expires) {
        $expires_at = date('Y-m-d H:i:s', strtotime($expires));
    }
    
    $app_valid = true;
    if ($aid > 0) {
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $at WHERE id=%d", $aid));
        if ($owner != $uid) $app_valid = false;
    }
    
    if ($app_valid && $qty > 0 && $qty <= 100) {
        $used_lics = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $lt WHERE user_id=%d", $uid));
        $quota_limit = (int)get_user_meta($uid, 'cl_quota_limit', true);
        if ($quota_limit < 1) $quota_limit = 100;
        
        if (($used_lics + $qty) > $quota_limit) {
            $avail = max(0, $quota_limit - $used_lics);
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Gagal! Sisa kuota Anda hanya $avail. Silakan Request Tambah Kuota.'));</script>";
        } else {
            $added = 0;
            for($i=0; $i<$qty; $i++) {
                $key = 'CL' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 12)) . rand(10,99);
                $q = $wpdb->insert($lt, [
                    'user_id' => $uid,
                    'license_key' => $key,
                    'app_id' => $aid,
                    'status' => 'active',
                    'label' => $lbl,
                    'max_devices' => max(1, $max_dev),
                    'expires_at' => $expires_at,
                    'created_at' => current_time('mysql')
                ]);
                if ($q) $added++;
            }
            cl_insert_history($uid, "Berhasil mem-generate $added lisensi baru secara manual.");
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('$added Lisensi berhasil di-generate.'));</script>";
        }
    }
}

if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_assign'])) {
    $id = intval($_POST['lic_id']);
    $a_name = sanitize_text_field($_POST['a_name'] ?? '');
    $a_email = sanitize_email($_POST['a_email'] ?? '');
    $a_wa = cl_normalize_wa(sanitize_text_field($_POST['a_wa'] ?? ''));
    
    $ownercol = $wpdb->get_row($wpdb->prepare("SELECT l.*, a.app_name, a.canvas_link, a.custom_slug FROM $lt l LEFT JOIN $at a ON l.app_id=a.id WHERE l.id=%d", $id));
    
    if ($ownercol && $ownercol->user_id == $uid) {
        $wpdb->update($lt, [
            'assignee_name' => $a_name,
            'assignee_email' => $a_email,
            'assignee_wa' => $a_wa
        ], ['id' => $id]);
        
        // Sync to app user list if not exists
        if (!empty($a_email) || !empty($a_wa) || !empty($a_name)) {
            $ct = $wpdb->prefix . 'cl_customers';
            
            $exists_c = false;
            if (!empty($a_email) || !empty($a_wa)) {
                $exists_c = $wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE user_id=%d AND ((email=%s AND email != '') OR (wa_number=%s AND wa_number != ''))", $uid, $a_email, $a_wa));
            } else {
                $exists_c = $wpdb->get_var($wpdb->prepare("SELECT id FROM $ct WHERE user_id=%d AND name=%s AND email='' AND wa_number=''", $uid, $a_name));
            }
            
            if (!$exists_c) {
                // Organic creation bypass quota to ensure all buyers are listed
                $wpdb->insert($ct, [
                    'user_id' => $uid,
                    'name' => $a_name,
                    'email' => $a_email,
                    'wa_number' => $a_wa,
                    'created_at' => current_time('mysql')
                ]);
            }
        }
        
        // Notify via Subscriber templates
        $tpl_wa = get_user_meta($uid, 'cl_ar_wa', true) ?: "Kunci Lisensi Anda: {license_key}\nAkses: {access_link}";
        $def_email = '<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f9fafb; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 12px; border: 1px solid #e5e7eb;">
        <h2 style="color: #003888; margin-top: 0;">Akses Aplikasi Anda Sudah Siap!</h2>
        <p>Halo <strong>{buyer_name}</strong>,</p>
        <p>Terima kasih atas pesanan Anda. Kami telah menyiapkan dan mengamankan jalur akses Anda ke dalam ekosistem <strong>{app_name}</strong>.</p>
        <div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #003888;">
            <p style="margin:0 0 12px 0;"><strong>Link Akses Aplikasi:</strong><br><a href="{link_gemini_CANVAS}" style="color:#2563eb; text-decoration:none; font-weight:bold;">{link_gemini_CANVAS}</a></p>
            <p style="margin:0 0 12px 0;"><strong>Custom Link Anda:</strong><br><a href="{custom_link}" style="color:#2563eb; text-decoration:none;">{custom_link}</a></p>
            <p style="margin:0;"><strong>Kode Lisensi Eksklusif:</strong><br><span style="font-family: monospace; font-size:18px; color: #111827;">{license_key}</span></p>
        </div>
        <p>Gunakan Kunci Lisensi di atas saat aplikasi memintanya pada halaman awal. Akses Anda bersifat aman dan terbatas sesuai penggunaan Anda pribadi.</p>
    </div>
</body>
</html>';
        $tpl_email = get_user_meta($uid, 'cl_ar_email', true) ?: $def_email;
        
        $app_name = $ownercol->app_name ?: 'Global';
        $acc_link = $ownercol->canvas_link ?: 'Cek URL akses dari admin.';
        $c_link = '';
        if ($ownercol->custom_slug) {
            $c_link = rtrim(home_url(), '/') . '/ai/' . $ownercol->custom_slug;
        } else {
            $c_link = $acc_link;
        }
        
        $member_wa = get_user_meta($uid, 'cl_wa_number', true) ?: '-';
        $tpl_wa = str_replace(['{app_name}', '{license_key}', '{access_link}', '{link_gemini_CANVAS}', '{custom_link}', '{buyer_name}', '{workspace_owner_wa}'], [$app_name, $ownercol->license_key, $acc_link, $acc_link, $c_link, $a_name, $member_wa], $tpl_wa);
        $tpl_email = str_replace(['{app_name}', '{license_key}', '{access_link}', '{link_gemini_CANVAS}', '{custom_link}', '{buyer_name}', '{workspace_owner_wa}'], [$app_name, $ownercol->license_key, $acc_link, $acc_link, $c_link, $a_name, $member_wa], $tpl_email);

        
        $fonnte = get_option('cl_fonnte_token');
        $mailketing = get_option('cl_mailketing_token'); // use new token field
        
        // Ensure email sends HTML content securely
        add_filter( 'wp_mail_content_type', function() { return "text/html"; } );

        if ($fonnte && $a_wa) {
            wp_remote_post('https://api.fonnte.com/send', [
                'headers' => ['Authorization' => $fonnte],
                'body' => ['target' => $a_wa, 'message' => $tpl_wa]
            ]);
        }
        if ($a_email) {
            cl_send_email($a_email, "Akses Aplikasi " . $app_name, $tpl_email);
        }
        
        cl_insert_history($uid, "Lisensi " . $ownercol->license_key . " di-assign & dikirim ke " . ($a_name ?: 'Pengguna') . ($a_email ? " ($a_email)" : "") . ($a_wa ? " ($a_wa)" : ""));
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Lisensi berhasil di-assign & dikirim.'));</script>";
    }
}

// Logic Delete/Status Updates (Isolated)
if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act')) {
    if (isset($_POST['cl_edit_lic'])) {
        $id = intval($_POST['edit_lic_id']);
        $new_app_id = intval($_POST['edit_app_id']);
        $new_max_devices = intval($_POST['edit_max_devices']);
        $new_expires_at = sanitize_text_field($_POST['edit_expires_at']);
        $new_label = sanitize_text_field($_POST['edit_label'] ?? '');
        
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $lt WHERE id=%d", $id));
        if ($owner == $uid || current_user_can('manage_options')) {
            $wpdb->update($lt, [
                'app_id' => $new_app_id,
                'max_devices' => max(1, $new_max_devices),
                'label' => $new_label,
                'expires_at' => !empty($new_expires_at) ? $new_expires_at . ' 23:59:59' : null
            ], ['id' => $id]);
            cl_insert_history($uid, "Lisensi ID $id diperbarui (AppID: $new_app_id, Max devices: $new_max_devices, Label: $new_label)");
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Lisensi berhasil diperbarui.'));</script>";
        }
    }
    if (isset($_POST['cl_unassign'])) {
        $id = intval($_POST['lic_id']);
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $lt WHERE id=%d", $id));
        if ($owner == $uid) {
            $wpdb->update($lt, [
                'assignee_name' => '',
                'assignee_email'=> '',
                'assignee_wa'   => ''
            ], ['id' => $id]);
            cl_insert_history($uid, "Akses pada Lisensi ID $id di-unassign dari pengguna.");
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Akses Lisensi di-unassign. Data app user tidak dihapus.'));</script>";
        }
    }
    if (isset($_POST['cl_del_lic'])) {
        $id = intval($_POST['lic_id']);
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $lt WHERE id=%d", $id));
        if ($owner == $uid) {
            $wpdb->delete($lt, ['id' => $id], ['%d']);
            cl_insert_history($uid, "Lisensi ID $id dihapus dari sistem.");
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Lisensi dihapus.'));</script>";
        }
    }
    if (isset($_POST['cl_set_status'])) {
        $id = intval($_POST['lic_id']);
        $st = sanitize_text_field($_POST['status']);
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $lt WHERE id=%d", $id));
        if ($owner == $uid) {
            $wpdb->update($lt, ['status' => $st], ['id' => $id], ['%s'], ['%d']);
            cl_insert_history($uid, "Status lisensi ID $id diubah menjadi: " . strtoupper($st));
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Status lisensi diperbarui.'));</script>";
        }
    }
    if (isset($_POST['cl_reset_device'])) {
        $id = intval($_POST['lic_id']);
        $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $lt WHERE id=%d", $id));
        if ($owner == $uid) {
            $wpdb->update($lt, ['device_fingerprint' => ''], ['id' => $id]);
            cl_insert_history($uid, "Data device login di-reset untuk lisensi ID $id");
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Device login berhasil di-reset.'));</script>";
        }
    }
}

$my_apps = $wpdb->get_results($wpdb->prepare("SELECT id, app_name FROM $at WHERE user_id=%d ORDER BY app_name ASC", $uid));

$fapp = isset($_GET['fapp']) ? intval($_GET['fapp']) : 0;
$fs   = isset($_GET['fs']) ? sanitize_text_field($_GET['fs']) : '';
$sq   = isset($_GET['sq']) ? sanitize_text_field($_GET['sq']) : '';

$where = $wpdb->prepare("l.user_id = %d", $uid);
if ($fapp) $where .= $wpdb->prepare(" AND l.app_id=%d", $fapp);
if ($fs)   $where .= $wpdb->prepare(" AND l.status=%s", $fs);
if ($sq) {
    $sq_like = '%' . $wpdb->esc_like($sq) . '%';
    $where .= $wpdb->prepare(" AND (l.license_key LIKE %s OR l.label LIKE %s OR l.assignee_name LIKE %s OR l.assignee_email LIKE %s OR l.assignee_wa LIKE %s)", $sq_like, $sq_like, $sq_like, $sq_like, $sq_like);
}

$page_num = isset($_GET['pnum']) ? max(1, intval($_GET['pnum'])) : 1;
$per_page = 20;

$total_items = $wpdb->get_var("SELECT COUNT(*) FROM $lt l WHERE $where");
$total_pages = ceil($total_items / $per_page);
$offset = ($page_num - 1) * $per_page;

$lics = $wpdb->get_results("SELECT l.*, a.app_name FROM $lt l LEFT JOIN $at a ON l.app_id=a.id WHERE $where ORDER BY l.id DESC LIMIT $per_page OFFSET $offset");
?>

<div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
    
    <div class="xl:col-span-3 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden xl:sticky xl:top-8">
        <div class="p-5 border-b border-slate-100 bg-slate-50">
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i data-lucide="zap" class="w-4 h-4 mr-2 text-brand"></i> Generate Lisensi Massal
            </h2>
        </div>
        <form method="post" class="p-5 space-y-4">
            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
            <input type="hidden" name="cl_batch_gen" value="1">
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Target Aplikasi</label>
                <select name="app_id" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                    <option value="0">-- Semua App (Global) --</option>
                    <?php foreach($my_apps as $ma): ?>
                        <option value="<?= $ma->id ?>"><?= esc_html($ma->app_name) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-400 mt-1">Pilih apikasi atau biarkan Global.</p>
            </div>
            
            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Jumlah Key</label>
                <input type="number" name="qty" min="1" max="100" value="10" 
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Label / Batch <span class="font-normal text-slate-400">(opsional)</span></label>
                <input type="text" name="label" placeholder="Promo 2024..." 
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Masa Aktif (Expired) <span class="font-normal text-slate-400">(opsional)</span></label>
                <input type="datetime-local" name="expires_at" 
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 mb-1">Limit Multi-Device</label>
                <input type="number" name="max_devices" min="1" max="999" value="100" 
                    class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                <p class="text-[10px] text-slate-400 mt-1">Berapa perangkat/browser yang diizinkan untuk 1 lisensi.</p>
            </div>

            <div class="pt-0">
                <button type="submit" class="w-full bg-accent hover:bg-accentHover text-white font-medium py-2.5 px-4 rounded-lg text-sm shadow-sm transition-colors flex items-center justify-center">
                    <i data-lucide="cpu" class="w-4 h-4 mr-2"></i> Eksekusi Generate
                </button>
            </div>
        </form>
    </div>

    <!-- Tabel -->
    <div class="xl:col-span-9 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col h-full min-h-[600px]">
        <div class="p-4 border-b border-slate-100 flex flex-wrap gap-4 justify-between items-center bg-white shrink-0">
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i data-lucide="key" class="w-4 h-4 mr-2 text-slate-400"></i> Data Lisensi (<?= number_format($total_items) ?>)
            </h2>
            
            <form method="get" class="flex flex-col sm:flex-row gap-2 text-sm sm:items-center w-full md:w-auto">
                <input type="hidden" name="page" value="canvaslock">
                <input type="hidden" name="view" value="licenses">
                
                <input type="text" name="sq" value="<?= esc_attr($sq) ?>" placeholder="Cari lisensi..." class="border border-slate-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-brand bg-slate-50 min-w-full sm:min-w-[150px]">
                
                <select name="fapp" class="border border-slate-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-brand bg-slate-50 min-w-full sm:min-w-[150px] truncate">
                    <option value="0">Semua App</option>
                    <?php foreach($my_apps as $ma): ?>
                        <option value="<?= $ma->id ?>" <?= $fapp==$ma->id ? 'selected':'' ?>><?= esc_html($ma->app_name) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select name="fs" class="border border-slate-200 rounded-lg px-3 py-1.5 focus:outline-none focus:border-brand bg-slate-50 min-w-full sm:min-w-[120px]">
                    <option value="">Semua Status</option>
                    <option value="active" <?= $fs=='active' ? 'selected':'' ?>>Aktif</option>
                    <option value="inactive" <?= $fs=='inactive' ? 'selected':'' ?>>Nonaktif</option>
                </select>
                
                <button type="submit" class="bg-brand hover:bg-[#002b6b] text-white px-3 py-1.5 rounded-lg transition-colors shadow-sm min-w-full sm:min-w-fit">
                    Filter
                </button>
                <a href="<?= admin_url("admin.php?page=canvaslock&cl_export=csv&fapp=$fapp&fs=$fs") ?>" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 px-3 py-1.5 rounded-lg transition-colors shadow-sm flex items-center min-w-full sm:min-w-fit justify-center whitespace-nowrap text-sm font-semibold">
                    <i data-lucide="download" class="w-4 h-4 sm:mr-2"></i> <span class="hidden sm:inline">Export CSV</span>
                </a>
            </form>
        </div>
        
        <div class="flex-1 overflow-auto bg-slate-50/20 relative">
            <table class="w-full text-left border-collapse min-w-[700px] border-collapse min-w-[700px]">
                <thead class="sticky top-0 bg-white shadow-sm z-10">
                    <tr class="text-slate-500 text-[11px] uppercase tracking-widest bg-slate-50 border-b border-slate-200">
                        <th class="px-5 py-3 font-semibold">Key & Label</th>
                        <th class="px-5 py-3 font-semibold">Aplikasi</th>
                        <th class="px-5 py-3 font-semibold">Assignee / Limit</th>
                        <th class="px-5 py-3 font-semibold text-center">Expired</th>
                        <th class="px-5 py-3 font-semibold text-center">Status</th>
                        <th class="px-5 py-3 font-semibold text-center">Usage</th>
                        <th class="px-5 py-3 font-semibold text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-slate-100">
                    <?php if ($lics): foreach ($lics as $l): 
                        $is_active = $l->status === 'active';
                        $is_expired = $l->expires_at && strtotime($l->expires_at) < current_time('timestamp');
                        $max_dev = isset($l->max_devices) ? $l->max_devices : 1;
                        if ($is_expired && $is_active) $is_active = false; // visual override if expired
                    ?>
                        <tr class="bg-white hover:bg-slate-50/50 transition-colors group">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2 mb-1">
                                    <code onclick="navigator.clipboard.writeText('<?= esc_js($l->license_key) ?>'); showToast('Lisensi disalin!');" title="Klik untuk copy lisensi" class="cursor-pointer font-mono text-xs font-bold text-slate-800 bg-slate-100 px-2 py-1 rounded border border-slate-200 transition-colors hover:bg-blue-50 hover:border-blue-200 hover:text-blue-700"><?= esc_html($l->license_key) ?></code>
                                </div>
                                <?php if ($l->label): ?>
                                    <div class="text-[11px] text-slate-500 font-medium inline-flex items-center px-1.5 py-0.5 rounded bg-slate-50 border border-slate-100">
                                        <i data-lucide="tag" class="w-3 h-3 mr-1"></i> <?= esc_html($l->label) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-[11px] text-slate-400 italic">Tanpa label</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <?php if ($l->app_id): ?>
                                    <span class="text-brand font-semibold truncate block max-w-[150px]" title="<?= esc_attr($l->app_name ?? '') ?>">
                                        <?= esc_html($l->app_name ?? '(Dihapus)') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-emerald-600 border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 rounded-full text-[11px] font-bold">Global API</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <?php if (!empty($l->assignee_name) || !empty($l->assignee_wa)): ?>
                                    <div class="text-xs font-medium text-slate-800"><?= esc_html($l->assignee_name ?: $l->assignee_wa) ?></div>
                                    <div class="text-[10px] text-slate-500"><?= esc_html($l->assignee_wa) ?> / <?= esc_html($l->assignee_email) ?></div>
                                <?php else: ?>
                                    <div class="text-xs text-slate-400 italic">Belum di-assign</div>
                                <?php endif; ?>
                                <div class="text-[10px] text-slate-500 mt-1"><i data-lucide="laptop" class="inline w-3 h-3"></i> max <?= $max_dev ?> device(s)</div>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <?php if ($l->expires_at): ?>
                                    <div class="text-[11px] <?php echo $is_expired ? 'text-red-500 font-bold' : 'text-slate-600'; ?>">
                                        <?= date('d M Y, H:i', strtotime($l->expires_at)) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-xs text-slate-400 italic">Selamanya</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <?php if ($is_active): ?>
                                    <span class="inline-flex items-center text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-1 rounded-md text-[11px] font-bold">
                                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2"></div> Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center text-slate-600 bg-slate-100 border border-slate-200 px-2 py-1 rounded-md text-[11px] font-bold">
                                        <div class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-2"></div> Nonaktif
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <div class="font-bold text-slate-700 <?= $l->usage_count>0 ? 'text-brand' : '' ?>"><?= number_format($l->usage_count) ?>x</div>
                                <?php if ($l->last_used): ?>
                                    <div class="text-[10px] text-slate-400 mt-0.5" title="<?= $l->last_used ?>"><?= date('d M', strtotime($l->last_used)) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <div class="flex justify-end items-center gap-2 transition-opacity">
                                    <button type="button" onclick="openEditModal(<?= $l->id ?>, <?= $l->app_id ?>, <?= $l->max_devices ?: 1 ?>, '<?= $l->expires_at ? date('Y-m-d', strtotime($l->expires_at)) : '' ?>', '<?= esc_js($l->label ?? '') ?>')" class="p-2 rounded-lg border border-blue-200 bg-white text-blue-500 hover:bg-blue-50 hover:text-blue-700 transition-colors shadow-sm" title="Edit Lisensi">
                                        <i data-lucide="edit" class="w-4 h-4"></i>
                                    </button>
                                    <form method="post" class="m-0 p-0" onsubmit="return confirm('Hapus lisensi permanen? (Data pelanggan di menu App User tidak akan terhapus)');">
                                        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
                                        <input type="hidden" name="lic_id" value="<?= $l->id ?>">
                                        <input type="hidden" name="cl_del_lic" value="1">
                                        <button type="submit" class="p-2 rounded-lg border border-red-200 bg-white text-red-500 hover:bg-red-50 hover:text-red-700 transition-colors shadow-sm" title="Hapus">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                    
                                    <?php if (!empty($l->assignee_name) || !empty($l->assignee_wa) || !empty($l->assignee_email)): ?>
                                    <form method="post" class="m-0 p-0" onsubmit="return confirm('Proses ini akan mencabut koneksi lisensi ini dari App User tsb (User tetap ada di list App User, hanya tidak lagi memiliki lisensi ini). Lanjutkan?');">
                                        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
                                        <input type="hidden" name="lic_id" value="<?= $l->id ?>">
                                        <input type="hidden" name="cl_unassign" value="1">
                                        <button type="submit" class="p-2 rounded-lg border border-orange-200 bg-white text-orange-500 hover:bg-orange-50 hover:text-orange-700 transition-colors shadow-sm" title="Unassign / Cabut dari Pengguna">
                                            <i data-lucide="user-x" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    
                                    <form method="post" class="m-0 p-0">
                                        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
                                        <input type="hidden" name="lic_id" value="<?= $l->id ?>">
                                        <input type="hidden" name="cl_set_status" value="1">
                                        <input type="hidden" name="status" value="<?= $is_active ? 'inactive' : 'active' ?>">
                                        <button type="submit" title="<?= $is_active ? 'Nonaktifkan' : 'Aktifkan' ?>" class="p-2 rounded-lg border shadow-sm transition-colors <?= $is_active ? 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' : 'bg-emerald-50 border-emerald-200 text-emerald-700 hover:bg-emerald-100' ?>">
                                            <i data-lucide="<?= $is_active ? 'pause' : 'play' ?>" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                    
                                    <button type="button" onclick="openAssign(<?= $l->id ?>)" class="flex items-center px-4 py-2 rounded-lg border border-brand text-brand hover:bg-brand hover:text-white transition-colors bg-white font-semibold text-sm shadow-sm" title="Assign & Kirim Akses">
                                        <i data-lucide="send" class="w-4 h-4 mr-2"></i> Assign
                                    </button>
                                    
                                    <form method="post" class="m-0 p-0" onsubmit="return confirm('Mereset device akan mengosongkan batasan perangkat/browser sebelumnya. User akan mulai dari 0 device. Lanjutkan?');">
                                        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
                                        <input type="hidden" name="lic_id" value="<?= $l->id ?>">
                                        <input type="hidden" name="cl_reset_device" value="1">
                                        <button type="submit" class="p-2 rounded-lg border border-purple-200 bg-white text-purple-500 hover:bg-purple-50 hover:text-purple-700 transition-colors shadow-sm" title="Reset Device">
                                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr>
                            <td colspan="6" class="p-10 text-center text-slate-500 bg-slate-50 ">
                                <div class="mx-auto w-10 h-10 bg-slate-200 rounded-full flex items-center justify-center mb-2">
                                    <i data-lucide="key" class="w-5 h-5 text-slate-400"></i>
                                </div>
                                <p class="text-sm font-medium">Belum ada lisensi ditemukan.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="px-5 py-3 border-t border-slate-100 bg-white flex justify-between items-center shrink-0">
                <span class="text-xs text-slate-500">Hal <?= $page_num ?> dari <?= $total_pages ?></span>
                <div class="flex gap-1">
                    <?php if ($page_num > 1): ?>
                        <a href="?page=canvaslock&view=licenses&pnum=<?= $page_num-1 ?>&fapp=<?= $fapp ?>&fs=<?= $fs ?>&sq=<?= esc_attr($sq) ?>" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-xs font-semibold transition-colors">Prev</a>
                    <?php endif; ?>
                    <?php if ($page_num < $total_pages): ?>
                        <a href="?page=canvaslock&view=licenses&pnum=<?= $page_num+1 ?>&fapp=<?= $fapp ?>&fs=<?= $fs ?>&sq=<?= esc_attr($sq) ?>" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded text-xs font-semibold transition-colors">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$past_customers = $wpdb->get_results($wpdb->prepare(
    "SELECT name as assignee_name, email as assignee_email, wa_number as assignee_wa 
     FROM {$wpdb->prefix}cl_customers WHERE user_id=%d", 
    $uid
));
if(empty($past_customers)) $past_customers = [];
?>

<!-- Assign Modal -->
<div id="clAssignModal" class="fixed inset-0 z-[9999] bg-slate-900/50 backdrop-blur-sm hidden items-center justify-center p-4 opacity-0 transition-opacity">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl scale-95 transition-transform" id="clAssignModalContent">
        <form method="post">
            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
            <input type="hidden" name="cl_assign" value="1">
            <input type="hidden" name="lic_id" id="h_lic_id" value="">
            
            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50 rounded-t-2xl">
                <h3 class="font-bold text-slate-800 flex items-center">
                    <i data-lucide="send" class="w-5 h-5 mr-2 text-brand"></i> Assign & Kirim Akses
                </h3>
                <button type="button" onclick="closeAssign()" class="text-slate-400 hover:text-slate-600">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-5 space-y-4">
                <p class="text-[13px] text-slate-600 border border-brand/20 bg-brand/5 p-3 rounded-lg">Masukkan kontak pembeli. Sistem akan mengirim Kunci Lisensi dan URL Akses secara otomatis via Email dan WA.</p>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5 flex justify-between">
                        <span>Nama Pembeli <span class="text-slate-400 font-normal">(Opsional)</span></span>
                    </label>
                    <input type="text" name="a_name" id="a_name_input" list="past_customers_list" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand/20 focus:border-brand" autocomplete="off" placeholder="Pilih atau ketik nama pembeli...">
                    <datalist id="past_customers_list">
                        <?php foreach($past_customers as $c): ?>
                        <option value="<?= esc_attr($c->assignee_name) ?>"><?= esc_html($c->assignee_name) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Pembeli <span class="text-slate-400 font-normal">(Opsional)</span></label>
                    <input type="email" name="a_email" id="a_email_input" class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand/20 focus:border-brand">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">WhatsApp Pembeli <span class="text-slate-400 font-normal">(Opsional)</span></label>
                    <input type="text" name="a_wa" id="a_wa_input" placeholder="62812..." class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-brand/20 focus:border-brand">
                </div>
            </div>
            
            <div class="p-5 border-t border-slate-100 bg-slate-50 flex justify-end gap-3 rounded-b-2xl">
                <button type="button" onclick="closeAssign()" class="px-4 py-2.5 rounded-xl font-semibold text-slate-600 bg-slate-200 hover:bg-slate-300 transition-colors">Batal</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl font-semibold text-white bg-brand hover:bg-[#002b6b] transition-colors flex items-center shadow-sm text-sm">
                    Kirim Lisensi <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const pastCustomersMap = <?= wp_json_encode(array_values($past_customers)) ?>;
document.addEventListener('DOMContentLoaded', () => {
    const aNameInp = document.getElementById('a_name_input');
    const aEmailInp = document.getElementById('a_email_input');
    const aWaInp = document.getElementById('a_wa_input');
    if(aNameInp) {
        aNameInp.addEventListener('input', (e) => {
            const match = pastCustomersMap.find(c => c.assignee_name === e.target.value);
            if (match) {
                if (match.assignee_email) aEmailInp.value = match.assignee_email;
                if (match.assignee_wa) aWaInp.value = match.assignee_wa;
            }
        });
    }
});

function openAssign(id) {
    document.getElementById('h_lic_id').value = id;
    const modal = document.getElementById('clAssignModal');
    const content = document.getElementById('clAssignModalContent');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
    }, 10);
}
function closeAssign() {
    const modal = document.getElementById('clAssignModal');
    const content = document.getElementById('clAssignModalContent');
    modal.classList.add('opacity-0');
    content.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}

function openEditModal(id, appId, maxDev, exp, label) {
    document.getElementById('edit_lic_id').value = id;
    document.getElementById('edit_app_id').value = appId;
    document.getElementById('edit_max_devices').value = maxDev;
    document.getElementById('edit_expires_at').value = exp;
    document.getElementById('edit_label').value = label;
    const modal = document.getElementById('clEditModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => modal.classList.remove('opacity-0'), 10);
}
function closeEditModal() {
    const modal = document.getElementById('clEditModal');
    modal.classList.add('opacity-0');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}
</script>

<!-- Edit License Modal -->
<div id="clEditModal" class="hidden fixed inset-0 z-[99] bg-slate-900/50 flex items-center justify-center p-4 opacity-0 transition-opacity duration-200">
    <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl border border-slate-100 transition-transform duration-200">
        <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center"><i data-lucide="edit" class="w-5 h-5 mr-2 text-blue-500"></i> Edit Lisensi Aktif</h3>
        <p class="text-xs text-slate-500 mb-5 leading-relaxed">Ubah batasan serta aplikasi tujuan untuk lisensi ini. Berlaku seketika setelah disimpan.</p>
        <form method="post">
            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
            <input type="hidden" name="cl_edit_lic" value="1">
            <input type="hidden" name="edit_lic_id" id="edit_lic_id">
            
            <div class="mb-4">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Aplikasi Canvas</label>
                <select name="edit_app_id" id="edit_app_id" class="w-full border border-slate-200 rounded-lg p-2.5 text-sm bg-slate-50 text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-brand/20">
                    <option value="0">Global (Semua Aplikasi Anda)</option>
                    <?php if ($my_apps): foreach($my_apps as $a): ?>
                        <option value="<?= $a->id ?>"><?= esc_html($a->app_name) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Max Device (Limitasi Perangkat)</label>
                <input type="number" name="edit_max_devices" id="edit_max_devices" min="1" max="999" required class="w-full border border-slate-200 rounded-lg p-2 text-sm bg-slate-50 font-mono focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            
            <div class="mb-4">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Label Lisensi</label>
                <input type="text" name="edit_label" id="edit_label" class="w-full border border-slate-200 rounded-lg p-2 text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            
            <div class="mb-6">
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Batas Waktu (Expired Date)</label>
                <input type="date" name="edit_expires_at" id="edit_expires_at" class="w-full border border-slate-200 rounded-lg p-2 text-sm bg-slate-50 font-mono focus:outline-none focus:ring-2 focus:ring-brand/20">
                <p class="text-[10px] text-slate-400 mt-1">Biarkan kosong jika lisensi bersifat Life-Time (tanpa batas waktu).</p>
            </div>
            
            <div class="flex justify-end gap-3 border-t border-slate-100 pt-5">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-slate-200 text-slate-600 rounded-lg text-sm font-bold hover:bg-slate-300 transition-colors">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 transition-colors text-white rounded-lg text-sm font-bold shadow-sm flex items-center"><i data-lucide="save" class="w-4 h-4 mr-2"></i> Simpan</button>
            </div>
        </form>
    </div>
</div>
