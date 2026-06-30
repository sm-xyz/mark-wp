<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    echo '<p>Anda tidak memiliki akses ke halaman ini.</p>';
    exit;
}

global $wpdb;
$at = $wpdb->prefix . CL_APPS;
$lt = $wpdb->prefix . CL_LICS;
$logt = $wpdb->prefix . 'cl_api_logs';

    if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_approve_quota'])) {
        $req_uid = intval($_POST['user_id']);
        $add_amt = intval($_POST['quota_amount']);
        $cur_limit = (int)get_user_meta($req_uid, 'cl_quota_limit', true);
        if ($cur_limit < 1) $cur_limit = 100;
        
        update_user_meta($req_uid, 'cl_quota_limit', $cur_limit + $add_amt);
        delete_user_meta($req_uid, 'cl_quota_request');
        
        // Log History
        $hist = get_user_meta($req_uid, 'cl_quota_history', true) ?: [];
        $hist[] = [
            'date' => current_time('mysql'),
            'amount' => $add_amt
        ];
        update_user_meta($req_uid, 'cl_quota_history', $hist);
        
        // Send WA / Email notification to subscriber (Flow Quota Approved)
        $fonnte = get_option('cl_fonnte_token');
        $mailketing = get_option('cl_mailketing_token');
        $u_info = get_userdata($req_uid);
        
        $tpl_wa = get_option('cl_wa_tpl_quota', 'Halo {buyer_name}, Top-Up Lisensi sebesar {kuota_tambahan} telah disetujui. Total Kuota: {total_kuota}');
        $tpl_em = get_option('cl_em_tpl_quota', '');
        
        $total_q = $cur_limit + $add_amt;
        
        $tpl_wa = str_replace(['{buyer_name}', '{kuota_tambahan}', '{total_kuota}'], [$u_info->display_name, $add_amt, $total_q], $tpl_wa);
        $tpl_em = str_replace(['{buyer_name}', '{kuota_tambahan}', '{total_kuota}'], [$u_info->display_name, $add_amt, $total_q], $tpl_em);
        
        $u_wa = get_user_meta($req_uid, 'cl_wa_number', true);
        if ($fonnte && $u_wa) {
            wp_remote_post('https://api.fonnte.com/send', ['headers' => ['Authorization' => $fonnte], 'body' => ['target' => $u_wa, 'message' => $tpl_wa]]);
        }
        if ($mailketing && $u_info->user_email && $tpl_em) {
            cl_send_email($u_info->user_email, "Top-Up Lisensi Telah Disetujui", $tpl_em);
        }
        
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Quota berhasil ditambahkan. Notifikasi dikirim.'));</script>";
    }

    // Manual Quota Top-Up Request Handler
    if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_add_quota_manual'])) {
        $req_uid = intval($_POST['user_id']);
        $add_amt = intval($_POST['quota_amount']);
        if ($add_amt > 0) {
            $cur_limit = (int)get_user_meta($req_uid, 'cl_quota_limit', true);
            if ($cur_limit < 1) $cur_limit = 100;
            update_user_meta($req_uid, 'cl_quota_limit', $cur_limit + $add_amt);
            
            $hist = get_user_meta($req_uid, 'cl_quota_history', true) ?: [];
            $hist[] = [
                'date' => current_time('mysql'),
                'amount' => $add_amt,
                'note' => 'Manual Top-Up'
            ];
            update_user_meta($req_uid, 'cl_quota_history', $hist);
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Kuota berhasil ditambahkan secara manual.'));</script>";
        }
    }

    // User Lock/Unblock Action Handler
    if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_toggle_block'])) {
        $target_uid = intval($_POST['user_id']);
        if ($target_uid !== get_current_user_id()) {
            $cur_block = get_user_meta($target_uid, 'cl_user_blocked', true);
            if ($cur_block) {
                delete_user_meta($target_uid, 'cl_user_blocked');
                echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('User berhasil diaktifkan kembali.'));</script>";
            } else {
                update_user_meta($target_uid, 'cl_user_blocked', 1);
                echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('User berhasil diblokir.'));</script>";
            }
        } else {
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Anda tidak bisa memblokir diri sendiri!', 'error'));</script>";
        }
    }

    // Edit Workspace User
    if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_edit_wuser'])) {
        $edit_uid = intval($_POST['edit_uid']);
        $new_name = sanitize_text_field($_POST['edit_name']);
        $new_email = sanitize_email($_POST['edit_email']);
        $new_wa = cl_normalize_wa(sanitize_text_field($_POST['edit_wa']));
        
        $u_data = get_userdata($edit_uid);
        if ($u_data && !in_array('administrator', $u_data->roles) || current_user_can('manage_options')) {
            $update_data = [
                'ID' => $edit_uid,
                'display_name' => $new_name,
                'user_email' => $new_email
            ];
            $user_id = wp_update_user($update_data);
            if (!is_wp_error($user_id)) {
                update_user_meta($edit_uid, 'cl_wa_number', $new_wa);
                echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Profil user berhasil diperbarui.'));</script>";
            } else {
                echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Error: " . esc_js($user_id->get_error_message()) . "', 'error'));</script>";
            }
        }
    }

    // Delete Workspace User
    if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_del_wuser'])) {
        $del_uid = intval($_POST['del_uid']);
        if ($del_uid !== get_current_user_id()) {
            if (!function_exists('wp_delete_user')) {
                require_once(ABSPATH . 'wp-admin/includes/user.php');
            }
            // reassign posts to admin or just delete
            if (wp_delete_user($del_uid)) {
                // optionally we could delete apps, licenses associated with them, but let's let wp handle basic deletion. We can clean up custom tables too.
                $wpdb->delete($wpdb->prefix.'cl_apps', ['user_id' => $del_uid]);
                $wpdb->delete($wpdb->prefix.'cl_licenses', ['user_id' => $del_uid]);
                $wpdb->delete($wpdb->prefix.'cl_customers', ['user_id' => $del_uid]);
                
                echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('User berhasil dihapus.'));</script>";
            }
        }
    }

    $s = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $f_req = isset($_GET['f_req']) ? 1 : 0;

    $args = ['role__in' => ['administrator', 'subscriber']];
    if ($s) {
        $args['search'] = "*{$s}*";
        $args['search_columns'] = ['user_login', 'user_email', 'display_name'];
    }
    if ($f_req) {
        $args['meta_query'][] = ['key' => 'cl_quota_request', 'compare' => 'EXISTS'];
    }

    $users = get_users($args);

    if (empty($users) && $s && is_numeric($s)) {
        $wa_args = ['role__in' => ['administrator', 'subscriber'], 'meta_query' => [['key' => 'cl_wa_number', 'value' => $s, 'compare' => 'LIKE']]];
        if ($f_req) $wa_args['meta_query'][] = ['key' => 'cl_quota_request', 'compare' => 'EXISTS'];
        $users = get_users($wa_args);
    }
    
    // Custom Sort: Request Quota di atas
    usort($users, function($a, $b) {
        $a_req = (int)get_user_meta($a->ID, 'cl_quota_request', true);
        $b_req = (int)get_user_meta($b->ID, 'cl_quota_request', true);
        if ($a_req > 0 && $b_req == 0) return -1;
        if ($b_req > 0 && $a_req == 0) return 1;
        return 0;
    });
?>

<div class="max-w-7xl mx-auto pb-12">
    <div class="bg-blue-50 border border-brand/20 rounded-2xl p-5 flex items-start mb-6">
        <i data-lucide="users" class="w-6 h-6 text-brand mr-4 shrink-0"></i>
        <div>
            <h3 class="font-bold text-brand mb-1">Manajemen Workspace (Only Admin)</h3>
            <p class="text-sm text-slate-700 leading-relaxed max-w-4xl">
                Halaman ini menampilkan seluruh pengguna yang terdaftar di website beserta jumlah aplikasi dan lisensi di masing-masing workspacenya.<br>
                Anda bisa melakukan aksi persetujuan (approve) untuk request penambahan kuota lisensi dari sini.
            </p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50 shrink-0">
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i data-lucide="layout-grid" class="w-5 h-5 mr-3 text-brand"></i> Data Workspace Pengguna
            </h2>
            <form class="flex gap-2 text-sm max-w-sm w-full" method="get">
                <input type="hidden" name="page" value="canvaslock">
                <input type="text" name="s" value="<?= esc_attr($s) ?>" placeholder="Cari nama / email..." class="flex-1 w-full border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                <label class="flex items-center text-xs whitespace-nowrap px-2">
                    <input type="checkbox" name="f_req" value="1" <?= $f_req ? 'checked' : '' ?> class="mr-1"> Cuma Request
                </label>
                <button type="submit" class="bg-brand text-white px-3 py-2 rounded-lg font-semibold hover:bg-[#002b6b] transition text-xs whitespace-nowrap"><i data-lucide="search" class="w-4 h-4"></i></button>
            </form>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-slate-500 text-xs uppercase tracking-widest font-semibold">
                        <th class="px-6 py-4">User</th>
                        <th class="px-6 py-4">Kontak WA</th>
                        <th class="px-6 py-4 text-center">Total Apps</th>
                        <th class="px-6 py-4 text-center">Lisensi/Quota</th>
                        <th class="px-6 py-4 text-center">API Hit</th>
                        <th class="px-6 py-4">Bergabung</th>
                        <th class="px-6 py-4 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if ($users): foreach($users as $u): 
                        $uid = $u->ID;
                        $app_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $at WHERE user_id=%d", $uid));
                        $lic_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $lt WHERE user_id=%d", $uid));
                        $log_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $logt WHERE user_id=%d", $uid));
                        
                        $quota_limit = (int)get_user_meta($uid, 'cl_quota_limit', true);
                        if ($quota_limit < 1) $quota_limit = 100;
                        
                        $req_quota = (int)get_user_meta($uid, 'cl_quota_request', true);
                        $wa_num = get_user_meta($uid, 'cl_wa_number', true);
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-bold">
                                    <?= strtoupper(substr($u->display_name, 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800"><?= esc_html($u->display_name) ?></div>
                                    <div class="text-xs text-slate-500"><?= esc_html($u->user_email) ?></div>
                                    <div class="text-[10px] text-brand/70 font-semibold uppercase mt-0.5"><?= implode(', ', $u->roles) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <?= esc_html($wa_num ?: '-') ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="font-bold text-slate-700 bg-slate-100 px-3 py-1 rounded-full text-xs">
                                <?= number_format($app_count) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="font-bold border border-brand/20 text-brand bg-blue-50 px-3 py-1 rounded-full text-xs">
                                <?= number_format($lic_count) ?> / <?= number_format($quota_limit) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="font-bold text-accent bg-accent/10 px-3 py-1 rounded-full text-xs border border-accent/20">
                                <?= number_format($log_count) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500">
                            <?= date('d M Y', strtotime($u->user_registered)) ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex flex-col gap-2 items-center justify-center">
                                <?php if ($req_quota > 0): ?>
                                    <form method="post" onsubmit="return confirm('Approve penambahan kuota <?= $req_quota ?> untuk user ini? Pastikan ada bukti transfer.');" class="w-full">
                                        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
                                        <input type="hidden" name="cl_approve_quota" value="1">
                                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                                        <input type="hidden" name="quota_amount" value="<?= $req_quota ?>">
                                        <button class="bg-emerald-500 hover:bg-emerald-600 text-white text-[11px] font-bold px-3 py-1.5 rounded-lg flex items-center gap-1 w-full justify-center shadow-sm transition-all">
                                            <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Approve +<?= $req_quota ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <div class="flex items-center justify-center gap-2">
                                    <!-- Button open manual top up quota -->
                                    <button type="button" onclick="openManualQuotaModal(<?= $uid ?>, '<?= esc_js($u->display_name) ?>')" class="p-1.5 bg-slate-100 hover:bg-brand hover:text-white text-slate-700 rounded-lg border border-slate-200 transition-all flex items-center gap-1 shadow-sm" title="Top-Up Quota Manual">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> <span class="text-xs font-bold font-sans">Quota</span>
                                    </button>

                                    <!-- Toggle Block/Unblock Account Block Form -->
                                    <?php if ($uid !== get_current_user_id()): ?>
                                        <button type="button" onclick="openEditWUserModal(<?= $uid ?>, '<?= esc_js($u->display_name) ?>', '<?= esc_js($u->user_email) ?>', '<?= esc_js($wa_num) ?>')" class="p-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-lg border border-blue-200 shadow-sm transition-all" title="Edit Profil User">
                                            <i data-lucide="edit" class="w-3.5 h-3.5"></i>
                                        </button>
                                        
                                        <form method="post" class="m-0 p-0" onsubmit="return confirm('Apakah Anda yakin ingin <?= get_user_meta($uid, 'cl_user_blocked', true) ? 'mengaktifkan kembali' : 'memblokir' ?> user ini?');">
                                            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
                                            <input type="hidden" name="cl_toggle_block" value="1">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            <?php if (get_user_meta($uid, 'cl_user_blocked', true)): ?>
                                                <button type="submit" class="p-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 rounded-lg border border-emerald-250 shadow-sm transition-all" title="Aktifkan Account (Blocked)">
                                                    <i data-lucide="check-square" class="w-3.5 h-3.5"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="p-1.5 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg border border-rose-250 shadow-sm transition-all" title="Blokir Account">
                                                    <i data-lucide="ban" class="w-3.5 h-3.5"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Delete Workspace User Form -->
                                        <form method="post" class="m-0 p-0" onsubmit="return confirm('SANGAT BERBAHAYA! Anda yakin ingin menghapus permanen Workspace User ini beserta SEMUA aplikasi dan lisensinya? Tindakan ini tidak bisa dibatalkan.');">
                                            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
                                            <input type="hidden" name="cl_del_wuser" value="1">
                                            <input type="hidden" name="del_uid" value="<?= $uid ?>">
                                            <button type="submit" class="p-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg border border-red-200 shadow-sm transition-all" title="Hapus User">
                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="7" class="p-8 text-center text-slate-500">Belum ada pengguna / data tidak ditemukan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Manual Quota top-up -->
<div id="cl-manual-quota-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full shadow-2xl p-6 border border-slate-200">
        <div class="flex justify-between items-center mb-5">
            <h3 class="font-bold text-slate-800 text-lg">Top-Up Quota Manual</h3>
            <button type="button" onclick="closeManualQuotaModal()" class="text-slate-400 hover:text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg p-2">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form method="post">
            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
            <input type="hidden" name="cl_add_quota_manual" value="1">
            <input type="hidden" name="user_id" id="manual_quota_uid" value="">
            
            <div class="mb-5">
                <p class="text-sm text-slate-600 mb-4 font-medium">Top up kuota untuk user: <span id="manual_quota_uname" class="font-bold text-slate-800"></span></p>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Jumlah Kuota Tambahan</label>
                <input type="number" name="quota_amount" required min="1" placeholder="Cth: 100" class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            
            <div class="mt-6 flex gap-3">
                <button type="button" onclick="closeManualQuotaModal()" class="flex-1 px-4 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-semibold transition-all">Batal</button>
                <button type="submit" class="flex-1 bg-brand hover:bg-[#002b6b] text-white font-bold py-2 rounded-xl shadow-sm transition-all text-sm flex justify-center items-center">Simpan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openManualQuotaModal(userId, displayName) {
    document.getElementById('manual_quota_uid').value = userId;
    document.getElementById('manual_quota_uname').innerText = displayName;
    document.getElementById('cl-manual-quota-modal').classList.remove('hidden');
}

function closeManualQuotaModal() {
    document.getElementById('cl-manual-quota-modal').classList.add('hidden');
}

function openEditWUserModal(uid, name, email, wa) {
    document.getElementById('edit_uid').value = uid;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_wa').value = wa;
    document.getElementById('cl-edit-wuser-modal').classList.remove('hidden');
    document.getElementById('cl-edit-wuser-modal').classList.add('flex');
}

function closeEditWUserModal() {
    document.getElementById('cl-edit-wuser-modal').classList.add('hidden');
    document.getElementById('cl-edit-wuser-modal').classList.remove('flex');
}
</script>

<!-- Modal Edit Workspace User -->
<div id="cl-edit-wuser-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-sm w-full shadow-2xl p-6 border border-slate-200">
        <div class="flex justify-between items-center mb-5">
            <h3 class="font-bold text-slate-800 text-lg">Edit Profil Workspace</h3>
            <button type="button" onclick="closeEditWUserModal()" class="text-slate-400 hover:text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg p-2">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        <form method="post">
            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
            <input type="hidden" name="cl_edit_wuser" value="1">
            <input type="hidden" name="edit_uid" id="edit_uid" value="">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Lengkap</label>
                <input type="text" name="edit_name" id="edit_name" required class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Alamat Email</label>
                <input type="email" name="edit_email" id="edit_email" required class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nomor WhatsApp</label>
                <input type="text" name="edit_wa" id="edit_wa" placeholder="62812345..." class="w-full border border-slate-200 rounded-xl px-3.5 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            
            <div class="mt-6 flex gap-3">
                <button type="button" onclick="closeEditWUserModal()" class="flex-1 px-4 py-2 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-semibold transition-all">Batal</button>
                <button type="submit" class="flex-1 bg-brand hover:bg-[#002b6b] text-white font-bold py-2 rounded-xl shadow-sm transition-all text-sm flex justify-center items-center">Simpan Profil</button>
            </div>
        </form>
    </div>
</div>
