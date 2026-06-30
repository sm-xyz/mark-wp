<?php
if (!defined('ABSPATH')) exit;
$user = wp_get_current_user();
$uid = $user->ID;

if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_save_profile'])) {
    $email = sanitize_email($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $wa_number = cl_normalize_wa(sanitize_text_field($_POST['wa_number'] ?? ''));
    $display_name = sanitize_text_field($_POST['display_name'] ?? '');
    
    $userdata = [
        'ID' => $uid,
        'user_email' => $email,
    ];
    if (!empty($pass)) {
        $userdata['user_pass'] = $pass;
    }
    if (!empty($display_name)) {
        $userdata['display_name'] = $display_name;
    }
    
    $user_id = wp_update_user($userdata);
    
    if (!empty($wa_number)) {
        update_user_meta($uid, 'cl_wa_number', $wa_number);
    }
    
    if (is_wp_error($user_id)) {
        $err = esc_js($user_id->get_error_message());
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('{$err}', 'error'));</script>";
    } else {
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Profil berhasil diperbarui.'));</script>";
        if (!empty($pass)) {
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Password diubah. Silakan login kembali jika sesi terputus.'));</script>";
        }
        $user = wp_get_current_user(); // refresh
    }
}

?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start pb-12 mt-4 md:mt-8">
    
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex items-center gap-4 bg-slate-50">
        <div class="w-16 h-16 rounded-full bg-brand text-white flex items-center justify-center text-2xl font-bold shrink-0">
            <?= strtoupper(substr($user->display_name, 0, 1)) ?>
        </div>
        <div>
            <h2 class="text-xl font-bold text-brand"><?= esc_html($user->display_name) ?></h2>
            <p class="text-slate-500 text-sm"><?= esc_html($user->user_email) ?></p>
        </div>
    </div>
    
    <form method="post" class="p-6 space-y-6">
        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
        <input type="hidden" name="cl_save_profile" value="1">
        
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Username (Tidak bisa diubah)</label>
            <input type="text" value="<?= esc_attr($user->user_login) ?>" disabled 
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-100 text-slate-500 cursor-not-allowed">
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Nama Lengkap</label>
            <input type="text" name="display_name" value="<?= esc_attr($user->display_name) ?>" required
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all">
        </div>

        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Email Akun</label>
            <input type="email" name="email" value="<?= esc_attr($user->user_email) ?>" required
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all">
        </div>
        
        <div>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">No WhatsApp (Penting)</label>
            <input type="text" name="wa_number" value="<?= esc_attr(get_user_meta($uid, 'cl_wa_number', true)) ?>" placeholder="628..." required
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all">
            <p class="text-xs text-slate-500 mt-1.5">Wajib diisi agar sistem notifikasi & konfirmasi top-up kuota dapat terkirim.</p>
        </div>
        
        <div class="pt-4 border-t border-slate-100">
            <h3 class="font-bold text-slate-800 mb-4">Ubah Password</h3>
            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Password Baru</label>
            <input type="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah password"
                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all">
        </div>
        
        <div class="pt-4">
            <button type="submit" class="w-full sm:w-auto bg-accent hover:bg-accentHover text-white font-semibold py-3 px-8 rounded-xl shadow-sm transition-all focus:ring-2 focus:ring-offset-2 focus:ring-accent flex items-center justify-center">
                <i data-lucide="save" class="w-4 h-4 mr-2"></i> Simpan Perubahan
            </button>
        </div>
    </form>
    </div>

    <!-- Security Column / Illu Shield Hook -->
    <div>
        <?php do_action('illu_shield_profile_section', $uid); ?>
    </div>
</div>
