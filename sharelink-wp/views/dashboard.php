<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$at = $wpdb->prefix . CL_APPS; $lt = $wpdb->prefix . CL_LICS;
$uid = get_current_user_id();
$current_user = wp_get_current_user();

if (isset($_GET['saved'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Aplikasi & login script berhasil disimpan di halaman ini.'));</script>";
}

// 1. Quota Request Submission
if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_req_quota'])) {
    $req_amt = intval($_POST['quota_pkg']); // 200 or 1000
    $member_wa = sanitize_text_field($_POST['member_wa']);
    
    update_user_meta($uid, 'cl_quota_request', $req_amt);
    
    $fonnte = get_option('cl_fonnte_token');
    $admin_wa = get_option('cl_admin_wa');
    
    if ($fonnte) {
        $price = ($req_amt == 1000) ? 'Rp 500.000' : 'Rp 200.000';
        
        if (!empty($member_wa)) {
            $wa_member = get_option('cl_wa_tpl_quota_req', '');
            $wa_member = str_replace('{member_name}', $current_user->display_name, $wa_member);
            $wa_member = str_replace('{quota_amount}', $req_amt, $wa_member);
            $wa_member = str_replace('{total_harga}', $price, $wa_member);
            
            wp_remote_post('https://api.fonnte.com/send', [
                'headers' => ['Authorization' => $fonnte],
                'body' => ['target' => $member_wa, 'message' => $wa_member]
            ]);
        }
        
        if (!empty($admin_wa)) {
            $wa_admin = get_option('cl_wa_tpl_quota_admin', '');
            $wa_admin = str_replace('{member_name}', $current_user->display_name, $wa_admin);
            $wa_admin = str_replace('{member_email}', $current_user->user_email, $wa_admin);
            $wa_admin = str_replace('{quota_amount}', $req_amt, $wa_admin);
            
            wp_remote_post('https://api.fonnte.com/send', [
                'headers' => ['Authorization' => $fonnte],
                'body' => ['target' => $admin_wa, 'message' => $wa_admin]
            ]);
        }
    }
    echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Request berhasil. Silakan cek WhatsApp Anda untuk petunjuk transfer.'));</script>";
}

// 2. Delete App Submission
if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_del_app'])) {
    $id = intval($_POST['app_id']);
    $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $at WHERE id=%d", $id));
    if ($owner == $uid) {
        $wpdb->delete($at, ['id' => $id], ['%d']);
        $wpdb->query($wpdb->prepare("DELETE FROM $lt WHERE app_id=%d", $id));
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Aplikasi & lisensi berhasil dihapus secara permanen.'));</script>";
    }
}

// 3. Duplicate App Submission
if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_dup_app'])) {
    $id = intval($_POST['app_id']);
    $erow = $wpdb->get_row($wpdb->prepare("SELECT * FROM $at WHERE id=%d AND user_id=%d", $id, $uid));
    if ($erow) {
        $base_name = $erow->app_name;
        // Strip out existing suffix like " - copy X" or " (Duplikat)"
        $base_name = preg_replace('/ - copy \d+$/', '', $base_name);
        $base_name = str_replace(' (Duplikat)', '', $base_name);
        
        $copied_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $at WHERE user_id=%d AND app_name LIKE %s", 
            $uid, 
            $wpdb->esc_like($base_name . ' - copy ') . '%'
        ));
        
        $new_suffix = $copied_count + 1;
        $new_name = $base_name . ' - copy ' . $new_suffix;
        
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $at WHERE user_id=%d AND app_name=%s", $uid, $new_name))) {
            $new_suffix++;
            $new_name = $base_name . ' - copy ' . $new_suffix;
        }

        $new_slug = !empty($erow->custom_slug) ? $erow->custom_slug . '-' . wp_generate_password(4, false) : null;
        if ($new_slug) {
            // Ensure Slug is unique
            while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $at WHERE custom_slug=%s", $new_slug))) {
                $new_slug = $erow->custom_slug . '-' . wp_generate_password(4, false);
            }
        }
        
        $inserted = $wpdb->insert($at, [
            'user_id' => $uid,
            'app_name' => $new_name,
            'description' => $erow->description,
            'canvas_link' => $erow->canvas_link,
            'login_script' => '', // Reset login script so they generate a fresh one for the duplicated ID
            'gk_config' => $erow->gk_config,
            'payload' => $erow->payload,
            'custom_slug' => $new_slug,
            'created_at' => current_time('mysql')
        ]);

        if ($inserted) {
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Aplikasi " . esc_js($new_name) . " berhasil diduplikat!'));</script>";
        } else {
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Gagal menduplikat aplikasi.', 'error'));</script>";
        }
    }
}

// 4. Save Custom Link Setup Popup Submission
if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_save_custom_link'])) {
    $id = intval($_POST['link_app_id']);
    $owner = $wpdb->get_var($wpdb->prepare("SELECT user_id FROM $at WHERE id=%d", $id));
    if ($owner == $uid) {
        $canvas_link_val = esc_url_raw($_POST['popup_canvas_link'] ?? '');
        $custom_slug_val = sanitize_title($_POST['popup_custom_slug'] ?? '');
        
        if ($custom_slug_val) {
            $check_sql = $wpdb->prepare("SELECT id FROM $at WHERE custom_slug = %s AND id != %d", $custom_slug_val, $id);
            if ($wpdb->get_var($check_sql)) {
                $custom_slug_val = $custom_slug_val . '-' . wp_generate_password(4, false);
            }
        }
        
        $wpdb->update($at, [
            'canvas_link' => $canvas_link_val,
            'custom_slug' => $custom_slug_val
        ], ['id' => $id, 'user_id' => $uid]);
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Link Gemini & Custom Link berhasil disimpan!'));</script>";
    }
}

// 5. Query Statistics and Apps
$where_uid = $wpdb->prepare("user_id = %d", $uid);
$total_apps = (int)$wpdb->get_var("SELECT COUNT(*) FROM $at WHERE $where_uid");
$total_lics = (int)$wpdb->get_var("SELECT COUNT(*) FROM $lt WHERE $where_uid");
$now = current_time('mysql');
$active_lics = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $lt WHERE status='active' AND (expires_at IS NULL OR expires_at > %s) AND $where_uid", $now));
$inactive_lics = $total_lics - $active_lics;

$quota_limit = (int)get_user_meta($uid, 'cl_quota_limit', true);
if ($quota_limit < 1) $quota_limit = 100;
$req_quota = (int)get_user_meta($uid, 'cl_quota_request', true);

$s = sanitize_text_field($_GET['s'] ?? '');
$where = $where_uid;
if ($s) {
    $where .= $wpdb->prepare(" AND (app_name LIKE %s)", '%' . $wpdb->esc_like($s) . '%');
}
$apps = $wpdb->get_results("SELECT a.*, 
    (SELECT COUNT(*) FROM $lt WHERE app_id=a.id AND user_id=$uid) AS lic_t, 
    (SELECT COUNT(*) FROM $lt WHERE app_id=a.id AND status='active' AND (expires_at IS NULL OR expires_at > '$now') AND user_id=$uid) AS lic_a 
    FROM $at a WHERE $where ORDER BY a.created_at DESC");
?>

<!-- Statistics Blocks -->
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-8">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-start">
        <div class="p-2.5 bg-brand/10 rounded-xl mb-4 text-brand"><i data-lucide="smartphone" class="w-6 h-6"></i></div>
        <span class="text-3xl font-bold text-slate-800 tracking-tight leading-none mb-1"><?= $total_apps ?></span>
        <span class="text-sm font-medium text-slate-500">Total Workspace Apps</span>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-start">
        <div class="p-2.5 bg-accent/10 rounded-xl mb-4 text-accent"><i data-lucide="pie-chart" class="w-6 h-6"></i></div>
        <div class="flex items-end gap-2 mb-1">
            <span class="text-3xl font-bold text-slate-800 tracking-tight leading-none"><?= $total_lics ?></span>
            <span class="text-lg font-bold text-slate-400 mb-0.5">/ <?= $quota_limit ?></span>
        </div>
        <span class="text-sm font-medium text-slate-500">Lisensi Terpakai / Quota</span>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-start">
        <div class="p-2.5 bg-emerald-50 rounded-xl mb-4 text-emerald-600"><i data-lucide="check-circle-2" class="w-6 h-6"></i></div>
        <span class="text-3xl font-bold text-slate-800 tracking-tight leading-none mb-1"><?= $active_lics ?></span>
        <span class="text-sm font-medium text-slate-500">Lisensi Aktif</span>
    </div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 flex flex-col items-start">
        <div class="p-2.5 bg-slate-100 rounded-xl mb-4 text-slate-600"><i data-lucide="x-circle" class="w-6 h-6"></i></div>
        <span class="text-3xl font-bold text-slate-800 tracking-tight leading-none mb-1"><?= $inactive_lics ?></span>
        <span class="text-sm font-medium text-slate-500">Lisensi Nonaktif</span>
    </div>
</div>

<!-- Modal Add Quota Requests -->
<div id="cl-quota-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl p-6 border border-slate-200">
        <div class="flex justify-between items-center mb-5">
            <h3 class="font-bold text-slate-800 text-lg">Pilih Paket Penambahan Kuota</h3>
            <button type="button" onclick="document.getElementById('cl-quota-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg p-2"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        
        <form method="post" onsubmit="return confirm('Lanjutkan request kuota? Pastikan nomor WA Anda sudah benar.');">
            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
            <input type="hidden" name="cl_req_quota" value="1">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">No. WA Konfirmasi & Petunjuk Transfer</label>
                <input type="text" name="member_wa" required placeholder="Cth: 628123456789" class="w-full border border-slate-200 rounded-lg px-3 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            
            <div class="space-y-4 pt-2 border-t border-slate-100">
                <label class="flex items-center justify-between p-4 border border-slate-200 hover:border-brand bg-slate-50 hover:bg-white rounded-xl cursor-pointer transition-colors group">
                    <div class="flex items-center">
                        <input type="radio" name="quota_pkg" value="200" required class="w-4 h-4 text-brand">
                        <div class="ml-3">
                            <div class="font-bold text-slate-800 group-hover:text-brand transition-colors">+200 Kuota Lisensi</div>
                            <div class="text-xs text-slate-500">Hanya Rp 1.000 / lisensi</div>
                        </div>
                    </div>
                    <div class="font-bold text-slate-800">Rp 200.000</div>
                </label>
                
                <label class="flex items-center justify-between p-4 border border-brand/40 hover:border-brand bg-blue-50 hover:bg-white rounded-xl cursor-pointer transition-colors group relative overflow-hidden">
                    <div class="absolute top-0 right-0 bg-brand text-white text-[10px] font-bold px-2 py-0.5 rounded-bl-lg">BEST VALUE</div>
                    <div class="flex items-center">
                        <input type="radio" name="quota_pkg" value="1000" required class="w-4 h-4 text-brand">
                        <div class="ml-3">
                            <div class="font-bold text-slate-800 group-hover:text-brand transition-colors">+1000 Kuota Lisensi</div>
                            <div class="text-xs text-slate-500">Hanya Rp 500 / lisensi (-50%)</div>
                        </div>
                    </div>
                    <div class="font-bold text-brand">Rp 500.000</div>
                </label>
            </div>
            
            <div class="mt-6">
                <button type="submit" class="w-full bg-brand hover:bg-[#002b6b] text-white font-bold py-3 rounded-xl shadow-sm transition-all focus:ring-2 focus:ring-offset-2 flex justify-center items-center">
                    <i data-lucide="send-to-back" class="w-4 h-4 mr-2"></i> Request Quota Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Popup Modal: Setup Link Gemini Canvas & Custom link -->
<div id="cl-custom-link-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl p-6 border border-slate-200">
        <div class="flex justify-between items-center mb-5">
            <h3 class="font-bold text-slate-800 text-lg">Link Canvas & Custom</h3>
            <button type="button" onclick="closeLinkPopup()" class="text-slate-400 hover:text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg p-2">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        
        <form method="post" id="popup_link_form">
            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
            <input type="hidden" name="cl_save_custom_link" value="1">
            <input type="hidden" name="link_app_id" id="popup_link_app_id" value="">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Link Gemini Canvas <span class="text-red-500">*</span></label>
                    <input type="url" name="popup_canvas_link" id="popup_canvas_link" placeholder="https://gemini.google.com/app/..." required
                        class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                    <p class="text-[11px] text-slate-400 mt-1">Masukkan Link Gemini Canvas Anda.</p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Custom Link Slug</label>
                    <div class="flex gap-2">
                        <div class="flex flex-1 items-stretch border border-slate-200 rounded-xl overflow-hidden shadow-sm transition-all">
                            <span class="px-2.5 bg-slate-150 border-r border-slate-200 text-xs text-slate-500 select-none flex items-center">/ai/</span>
                            <input type="text" name="popup_custom_slug" id="popup_custom_slug" placeholder="custom-slug" pattern="[a-zA-Z0-9-]+" title="Hanya huruf, angka, dan strip (-)"
                                class="flex-1 px-3 py-2 text-sm focus:outline-none font-mono placeholder:font-sans">
                        </div>
                        <button type="button" onclick="checkPopupSlugLive()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-bold text-xs shadow-sm transition-all shrink-0">Cek Slug</button>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1" id="popup_slug_status_msg">Meredirect ke Link Gemini Canvas di atas.</p>
                </div>
            </div>
            
            <div class="mt-8 flex gap-3">
                <button type="button" onclick="closeLinkPopup()" class="flex-1 px-4 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-semibold transition-all">Batal</button>
                <button type="submit" class="flex-1 bg-brand hover:bg-[#002b6b] text-white font-bold py-2 rounded-xl shadow-sm transition-all text-sm flex justify-center items-center">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Main Table Area -->
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 md:p-6 border-b border-slate-100 flex flex-col md:flex-row md:justify-between md:items-center bg-white gap-4">
        <h2 class="text-lg font-bold text-slate-800 flex items-center">
            Aplikasi Canvas Saya
        </h2>
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <?php if ($req_quota > 0): ?>
                <div class="px-4 py-2 bg-orange-50 text-orange-600 rounded-xl border border-orange-200 text-sm font-semibold flex items-center justify-center whitespace-nowrap">
                    <i data-lucide="clock" class="w-4 h-4 mr-2"></i> Pending +<?= $req_quota ?>
                </div>
            <?php else: ?>
                <button onclick="document.getElementById('cl-quota-modal').classList.remove('hidden')" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-semibold transition-all shadow-sm flex flex-col sm:flex-row items-center justify-center whitespace-nowrap">
                    <i data-lucide="zap" class="w-4 h-4 sm:mr-2 mb-1 sm:mb-0"></i> Add Quota
                </button>
            <?php endif; ?>
            
            <form method="get" class="w-full sm:w-auto flex items-center gap-2">
                <input type="hidden" name="page" value="canvaslock">
                <div class="flex border border-slate-200 rounded-xl bg-white overflow-hidden shadow-sm">
                    <input type="text" name="s" value="<?= esc_attr($s) ?>" placeholder="Cari nama aplikasi..." class="flex-1 min-w-0 px-3 py-2 text-sm focus:outline-none">
                    <button type="submit" class="px-3 text-slate-500 hover:text-brand border-l border-slate-150 transition-colors"><i data-lucide="search" class="w-4 h-4"></i></button>
                </div>
                <a href="<?= admin_url('admin.php?page=canvaslock&view=apps') ?>" class="px-4 py-2 bg-brand hover:bg-[#002b6b] text-white rounded-xl text-sm font-semibold transition-all shadow-sm flex items-center justify-center whitespace-nowrap gap-1.5 hover:no-underline">
                    <i data-lucide="plus" class="w-4.5 h-4.5"></i> Add App
                </a>
            </form>
        </div>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-auto">
        <?php if ($apps): ?>
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-slate-500 text-xs uppercase tracking-widest font-semibold/70">
                        <th class="p-4 pl-6 w-12 text-center">No</th>
                        <th class="p-4">Nama Aplikasi</th>
                        <th class="p-4 text-center w-24">Lisensi</th>
                        <th class="p-4 w-1/4">Link Akses</th>
                        <th class="p-4 w-1/5 text-center">Login Script</th>
                        <th class="p-4 text-right pr-6">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php 
                    $no = 1;
                    foreach ($apps as $a):
                        $custom_url = rtrim(home_url(), '/') . '/ai/' . $a->custom_slug;
                        $has_login_script = !empty($a->login_script);
                    ?>
                        <tr class="hover:bg-slate-50/40 transition-all">
                            <td class="p-4 pl-6 text-center text-slate-400 font-bold"><?= $no++ ?></td>
                            <td class="p-4">
                                <div class="font-bold text-slate-850 hover:text-brand transition-colors text-base"><?= esc_html($a->app_name) ?></div>
                                <div class="text-xs text-slate-500 mt-0.5 truncate max-w-xs"><?= esc_html($a->description ?: 'Tanpa deskripsi') ?></div>
                                <div class="text-[10px] text-slate-400 mt-1 font-mono">ID: <?= $a->id ?> • <?= date('d M Y', strtotime($a->created_at)) ?></div>
                            </td>
                            <td class="p-4 text-center">
                                <a href="<?= admin_url('admin.php?page=canvaslock&view=licenses&app_filter='.$a->id) ?>" class="inline-block px-3 py-1 bg-brand/5 border border-brand/15 hover:bg-brand/10 text-brand rounded-full text-xs font-bold transition-all shadow-sm" title="Total Lisensi">
                                    <?= $a->lic_t ?>
                                </a>
                            </td>
                            <td class="p-4">
                                <?php if (empty($a->canvas_link)): ?>
                                    <button type="button" onclick="openLinkPopup(<?= $a->id ?>, '<?= esc_js($a->canvas_link) ?>', '<?= esc_js($a->custom_slug) ?>')" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-brand text-white hover:bg-[#002b6b] rounded-lg text-xs font-bold transition-all border border-brand/20 shadow-sm">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> Link Canvas & Custom
                                    </button>
                                <?php else: ?>
                                    <div class="space-y-1.5 min-w-[240px]">
                                        <div class="text-[11px] truncate max-w-sm" title="<?= esc_attr($a->canvas_link) ?>">
                                            <span class="text-slate-400 block mb-0.5 font-semibold">Link Canvas:</span>
                                            <a href="<?= esc_url($a->canvas_link) ?>" target="_blank" class="text-brand hover:underline font-medium"><?= esc_html($a->canvas_link) ?></a>
                                        </div>
                                        <div class="text-[11px] truncate max-w-sm">
                                            <span class="text-slate-400 block mb-0.5 font-semibold">Custom Link:</span>
                                            <a href="<?= esc_url($custom_url) ?>" target="_blank" class="text-blue-600 hover:underline font-semibold font-mono"><?= esc_html($custom_url) ?></a>
                                        </div>
                                        <button type="button" onclick="openLinkPopup(<?= $a->id ?>, '<?= esc_js($a->canvas_link) ?>', '<?= esc_js($a->custom_slug) ?>')" class="text-xs text-slate-500 hover:text-brand font-bold flex items-center gap-1 mt-1 transition-colors">
                                            <i data-lucide="edit-2" class="w-3 h-3"></i> Edit Link
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center">
                                <?php if ($has_login_script): ?>
                                    <div class="hidden" id="login_script_<?= $a->id ?>"><?= htmlspecialchars($a->login_script) ?></div>
                                    <button type="button" onclick="copyLoginScript(<?= $a->id ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-all border border-slate-200 shadow-sm whitespace-nowrap">
                                        <i data-lucide="copy" class="w-3.5 h-3.5"></i> Copy Script
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs text-amber-600 bg-amber-50 px-2.5 py-1 rounded-md font-semibold border border-amber-200 whitespace-nowrap">Belum dibuat</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-right pr-6">
                                <div class="flex justify-end items-center gap-2">
                                    <!-- Action 1: Delete App -->
                                    <form method="post" class="m-0 p-0" onsubmit="return confirm('Hapus app ini dan semua lisensinya secara permanen?');">
                                        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
                                        <input type="hidden" name="app_id" value="<?= $a->id ?>">
                                        <input type="hidden" name="cl_del_app" value="1">
                                        <button type="submit" class="p-2 bg-white text-rose-500 hover:bg-rose-50 hover:text-rose-700 rounded-lg transition-colors border border-slate-200 hover:border-rose-250 shadow-sm" title="Hapus Aplikasi">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>

                                    <!-- Action 2: Duplicate App -->
                                    <form method="post" class="m-0 p-0">
                                        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
                                        <input type="hidden" name="app_id" value="<?= $a->id ?>">
                                        <input type="hidden" name="cl_dup_app" value="1">
                                        <button type="submit" class="p-2 bg-white text-indigo-600 hover:bg-indigo-50 hover:text-indigo-800 rounded-lg transition-colors border border-slate-200 hover:border-indigo-250 shadow-sm" title="Duplikat Aplikasi">
                                            <i data-lucide="copy" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                    
                                    <!-- Action 3: Edit App -->
                                    <a href="<?= admin_url("admin.php?page=canvaslock&view=apps&edit=".$a->id) ?>" class="px-3.5 py-2 bg-brand text-white hover:bg-[#002b6b] rounded-lg transition-colors font-bold text-xs shadow-sm flex items-center gap-1">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit App
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="text-center py-16 bg-white rounded-2xl border border-dashed border-slate-300 m-6">
                <div class="w-16 h-16 bg-brand/10 text-brand rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="grid" class="w-8 h-8"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800 mb-2">Workspace Anda Kosong</h3>
                <p class="text-slate-500 mb-6 max-w-sm mx-auto">Mulai dengan menambahkan aplikasi pertama Anda untuk diintegrasikan dengan Gemini Canvas.</p>
                <?php if (!$s): ?>
                    <a href="<?= admin_url('admin.php?page=canvaslock&view=apps') ?>" class="inline-flex items-center px-6 py-2.5 bg-accent text-white font-bold rounded-xl hover:bg-accentHover transition shadow-sm text-sm">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i> Buat Aplikasi Baru
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const popupCanvasLink = document.getElementById('popup_canvas_link');
const popupCustomSlug = document.getElementById('popup_custom_slug');
const popupSlugMsg = document.getElementById('popup_slug_status_msg');

function checkPopupSlugConstraint() {
    if (!popupCanvasLink.value.trim()) {
        popupCustomSlug.disabled = true;
        popupCustomSlug.classList.add('bg-slate-100', 'cursor-not-allowed');
    } else {
        popupCustomSlug.disabled = false;
        popupCustomSlug.classList.remove('bg-slate-100', 'cursor-not-allowed');
    }
}

// Intercept attempts to type into slug input when Gemini link is blank
popupCustomSlug.addEventListener('mousedown', function(e) {
    if (popupCustomSlug.disabled) {
        e.preventDefault();
        showToast('Wajib mengisi link gemini canvas dulu', 'error');
        popupCanvasLink.focus();
    }
});
popupCustomSlug.addEventListener('keydown', function(e) {
    if (popupCustomSlug.disabled) {
        e.preventDefault();
        showToast('Wajib mengisi link gemini canvas dulu', 'error');
        popupCanvasLink.focus();
    }
});

popupCanvasLink.addEventListener('input', checkPopupSlugConstraint);
popupCanvasLink.addEventListener('change', checkPopupSlugConstraint);

function openLinkPopup(appId, currentLink, currentSlug) {
    document.getElementById('popup_link_app_id').value = appId;
    popupCanvasLink.value = currentLink;
    popupCustomSlug.value = currentSlug;
    
    popupSlugMsg.innerText = "Meredirect ke Link Gemini Canvas di atas.";
    popupSlugMsg.className = "text-[11px] text-slate-400 mt-1";
    
    checkPopupSlugConstraint();
    document.getElementById('cl-custom-link-modal').classList.remove('hidden');
}

function closeLinkPopup() {
    document.getElementById('cl-custom-link-modal').classList.add('hidden');
}

function checkPopupSlugLive() {
    const slug = popupCustomSlug.value.trim();
    const appId = document.getElementById('popup_link_app_id').value;
    if (!slug) {
        showToast('Tulis slug yang ingin dicek.', 'error');
        return;
    }
    
    popupSlugMsg.innerText = "Mengecek ketersediaan slug...";
    popupSlugMsg.className = "text-[11px] text-slate-500 mt-1";
    
    const formData = new FormData();
    formData.append('action', 'cl_check_slug');
    formData.append('slug', slug);
    if(appId) {
        formData.append('id', appId);
    }
    
    fetch('<?= admin_url('admin-ajax.php') ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.available) {
            popupSlugMsg.innerText = "✓ Slug tersedia dan bisa digunakan.";
            popupSlugMsg.className = "text-[11px] text-emerald-600 font-semibold mt-1";
            showToast('Slug tersedia!');
        } else {
            popupSlugMsg.innerText = "✗ Slug sudah digunakan atau tidak valid.";
            popupSlugMsg.className = "text-[11px] text-rose-500 font-semibold mt-1";
            showToast('Slug sudah digunakan.', 'error');
        }
    })
    .catch(e => {
        popupSlugMsg.innerText = "Gagal memverifikasi slug.";
        popupSlugMsg.className = "text-[11px] text-rose-500 mt-1";
    });
}

function copyLoginScript(appId) {
    var txt = document.getElementById('login_script_' + appId).innerText;
    if (txt) {
        navigator.clipboard.writeText(txt).then(() => {
            showToast('Login script berhasil dicopy!');
        }).catch(err => {
            console.error(err);
            showToast('Gagal copy script', 'error');
        });
    }
}
</script>
