<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    echo '<p>Anda tidak memiliki akses ke halaman ini.</p>';
    exit;
}

if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_test_api'])) {
    $fonnte = get_option('cl_fonnte_token');
    $mailketing = get_option('cl_mailketing_token');
    $target_wa = sanitize_text_field($_POST['test_wa']);
    $target_email = sanitize_email($_POST['test_email']);
    
    $res_msg = '';
    
    if ($fonnte && $target_wa) {
        $wa_res = wp_remote_post('https://api.fonnte.com/send', [
            'headers' => ['Authorization' => $fonnte],
            'body' => ['target' => $target_wa, 'message' => 'Halo! Ini pesan tes dari sistem Sharelink AI.']
        ]);
        $res_msg .= is_wp_error($wa_res) ? "WA Gagal. " : "WA Terkirim. ";
    }
    
    if ($mailketing && $target_email) {
        $email_res = cl_send_email($target_email, 'Tes Email Sharelink AI', '<p>Halo! Ini pesan tes email dari sistem Webhook Sharelink AI.</p>');
        if (is_wp_error($email_res)) {
            $res_msg .= "Email Gagal. ";
        } else {
            $res_body = wp_remote_retrieve_body($email_res);
            $res_msg .= "Email Sent API. Result: " . esc_js(substr($res_body, 0, 100));
        }
    }
    
    if($res_msg) {
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('".esc_js($res_msg)."'));</script>";
    }
}

if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_save_settings'])) {
    update_option('cl_cors_origin', sanitize_text_field($_POST['cors_origin']));
    update_option('cl_track_usage', isset($_POST['track_usage']) ? 1 : 0);
    update_option('cl_reg_link', esc_url_raw($_POST['reg_link']));
    update_option('cl_reg_text', sanitize_text_field($_POST['reg_text']));
    update_option('cl_turnstile_sitekey', sanitize_text_field($_POST['turnstile_sitekey']));
    update_option('cl_turnstile_secret', sanitize_text_field($_POST['turnstile_secret']));
    update_option('cl_fonnte_token', sanitize_text_field($_POST['fonnte_token']));
    update_option('cl_mailketing_token', sanitize_text_field($_POST['mailketing_token']));
    update_option('cl_mailketing_sender', sanitize_text_field($_POST['mailketing_sender']));
    update_option('cl_mailketing_email', sanitize_email($_POST['mailketing_email']));
    update_option('cl_admin_wa', sanitize_text_field($_POST['admin_wa']));
    
    update_option('cl_smtp_enabled', isset($_POST['smtp_enabled']) ? 1 : 0);
    update_option('cl_smtp_host', sanitize_text_field($_POST['smtp_host']));
    update_option('cl_smtp_port', intval($_POST['smtp_port']));
    update_option('cl_smtp_user', sanitize_text_field($_POST['smtp_user']));
    update_option('cl_smtp_pass', sanitize_text_field($_POST['smtp_pass']));
    update_option('cl_smtp_secure', sanitize_text_field($_POST['smtp_secure']));
    
    echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Pengaturan global disimpan.'));</script>";
}

$cors = get_option('cl_cors_origin', '*');
$track = get_option('cl_track_usage', 1);
$reg_link = get_option('cl_reg_link', '');
$reg_text = get_option('cl_reg_text', 'Belum punya akun, beli di sini.');
$turnstile_sitekey = get_option('cl_turnstile_sitekey', '');
$turnstile_secret = get_option('cl_turnstile_secret', '');
$fonnte_token = get_option('cl_fonnte_token', '');
$mailketing_token = get_option('cl_mailketing_token', '');
$mailketing_sender = get_option('cl_mailketing_sender', get_bloginfo('name'));
$mailketing_email = get_option('cl_mailketing_email', get_option('admin_email'));
$admin_wa = get_option('cl_admin_wa', '');

$smtp_enabled = get_option('cl_smtp_enabled', 0);
$smtp_host = get_option('cl_smtp_host', '');
$smtp_port = get_option('cl_smtp_port', 465);
$smtp_user = get_option('cl_smtp_user', '');
$smtp_pass = get_option('cl_smtp_pass', '');
$smtp_secure = get_option('cl_smtp_secure', 'ssl');
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start pb-10">
    <div class="space-y-8">
        <div class="bg-blue-50 border border-brand/20 rounded-2xl p-5 flex items-start">
            <i data-lucide="shield-alert" class="w-6 h-6 text-brand mr-4 shrink-0"></i>
            <div>
                <h3 class="font-bold text-brand mb-1">Informasi Hak Akses (Isolasi Workspace)</h3>
                <p class="text-sm text-slate-700 leading-relaxed">
                    Pengguna dengan role <b>Subscriber</b> ke atas memiliki akses ke dashboard Sharelink AI namun <b>terisolasi</b> di Workspacenya sendiri. Mereka tidak bisa melihat aplikasi atau lisensi milik pengguna lain, dan otomatis dibelokkan (redirect) dari dashboard wp-admin standar.<br><br>
                    Hanya Anda (Administrator) yang melihat menu "Global Setting" ini.
                </p>
            </div>
        </div>

        <form method="post" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
        <input type="hidden" name="cl_save_settings" value="1">
        
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800 flex items-center">
                <i data-lucide="settings" class="w-5 h-5 mr-3 text-brand"></i>
                Konfigurasi Inti API & Tampilan
            </h2>
            <p class="text-sm text-slate-500 mt-1">Pengaturan ini akan berimbas pada seluruh pengguna dan Workspace.</p>
        </div>
        
        <div class="p-6 space-y-6">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Kalimat Link Registrasi Pembelian Akun</label>
                <input type="text" name="reg_text" value="<?= esc_attr($reg_text) ?>" placeholder="Belum punya akun, beli di sini."
                    class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Link Redirect Pembelian (Lynk.id / Orderhero)</label>
                <input type="url" name="reg_link" value="<?= esc_attr($reg_link) ?>" placeholder="https://lynk.id/..."
                    class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                <p class="text-xs text-slate-500 mt-2">Masukan link produk. Dikosongkan jika tidak ingin memunculkan tombol beli di halaman login.</p>
            </div>
        
            <div class="pt-4 border-t border-slate-100">
                <h3 class="text-sm font-bold text-slate-800 mb-3 flex items-center"><i data-lucide="shield-check" class="w-4 h-4 text-brand mr-2"></i> Keamanan Cloudflare Turnstile (Lost Password)</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Site Key</label>
                        <input type="text" name="turnstile_sitekey" value="<?= esc_attr($turnstile_sitekey) ?>" placeholder="1x00000000000000000000AA"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Secret Key</label>
                        <input type="password" name="turnstile_secret" value="<?= esc_attr($turnstile_secret) ?>" placeholder="1x0000000000000000000000000000000AA"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand font-mono">
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
                <h3 class="text-sm font-bold text-slate-800 mb-3 flex items-center"><i data-lucide="webhook" class="w-4 h-4 text-brand mr-2"></i> Integrasi Gateway Pihak Ketiga</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Nomor WhatsApp Admin (Notifikasi)</label>
                        <input type="text" name="admin_wa" value="<?= esc_attr($admin_wa) ?>" placeholder="628123456789"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Fonnte API Token (WhatsApp)</label>
                        <input type="password" name="fonnte_token" value="<?= esc_attr($fonnte_token) ?>" placeholder="Token WhatsApp dari Fonnte"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Mailketing API Token (Email)</label>
                        <input type="password" name="mailketing_token" value="<?= esc_attr($mailketing_token) ?>" placeholder="Token / API Key dari Mailketing"
                            class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand">
                    </div>
                </div>
                <div class="space-y-4 mt-4 text-slate-700 bg-orange-50/50 p-4 rounded-xl border border-orange-100">
                    <p class="text-[13px] mb-3"><strong>⚠️ Penting:</strong> Pastikan Sender Email sudah ditambahkan dan diverifikasi di menu "Add Domain" pada dashboard Mailketing Anda.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Sender Name (Email Nama Pengirim)</label>
                            <input type="text" name="mailketing_sender" value="<?= esc_attr($mailketing_sender) ?>" placeholder="Sharelink AI System"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Sender Email</label>
                            <input type="email" name="mailketing_email" value="<?= esc_attr($mailketing_email) ?>" placeholder="no-reply@domainanda.com"
                                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                        </div>
                    </div>
                </div>

                <div class="space-y-4 mt-6 text-slate-700 bg-emerald-50/50 p-4 rounded-xl border border-emerald-100">
                    <h3 class="text-sm font-bold text-slate-800 mb-2 flex items-center">
                        <i data-lucide="server" class="w-4 h-4 mr-2 text-emerald-600"></i> SMTP Settings (WordPress & Fallback)
                    </h3>
                    <p class="text-xs text-slate-500 mb-4 pb-4 border-b border-slate-100/50">Jika API gagal mengirim email, sistem otomatis akan menggunakan SMTP. Jika diaktifkan, ini akan ambil alih seluruh email WordPress.</p>
                    <label class="flex items-center space-x-3 cursor-pointer group mb-4">
                        <input type="checkbox" name="smtp_enabled" value="1" <?= $smtp_enabled ? 'checked' : '' ?> class="w-5 h-5 text-accent border-slate-300 rounded">
                        <span class="text-sm font-semibold text-slate-700">Aktifkan SMTP Override</span>
                    </label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Host</label>
                            <input type="text" name="smtp_host" value="<?= esc_attr($smtp_host) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="smtp.gmail.com">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Port</label>
                            <input type="number" name="smtp_port" value="<?= esc_attr($smtp_port) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="465">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">User / Email</label>
                            <input type="text" name="smtp_user" value="<?= esc_attr($smtp_user) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="admin@domain.com">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Pass (App Password)</label>
                            <input type="password" name="smtp_pass" value="<?= esc_attr($smtp_pass) ?>" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white" placeholder="***">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Security</label>
                            <select name="smtp_secure" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="ssl" <?= $smtp_secure === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                <option value="tls" <?= $smtp_secure === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="" <?= empty($smtp_secure) ? 'selected' : '' ?>>None</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
                <label class="block text-sm font-semibold text-slate-700 mb-2">CORS Origin (Keamanan Frontend)</label>
                <div class="flex gap-2">
                    <input type="text" name="cors_origin" value="<?= esc_attr($cors) ?>" 
                        class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand font-mono">
                    <button type="button" onclick="testCORS()" class="px-4 py-2 bg-slate-800 text-white font-semibold rounded-xl text-sm shrink-0 hover:bg-slate-700 transition">Test CORS</button>
                </div>
                <p class="text-[11px] text-slate-500 mt-2">Gunakan <code>*</code> untuk allow semua origin (ideal untuk Gemini yang berubah-ubah), atau domain spesifik (misal <code>https://aplikasiku.xyz</code>).<br><span class="text-green-600 block mt-1"><i data-lucide="shield-check" class="w-3 h-3 inline-block"></i> <b>v1.1 Security:</b> Walaupun diisi <code>*</code>, payload file (script Anda) sekarang tertanam & terenkripsi lokal di dalam file HTML secara kriptografis. API <code>/verify</code> hanya sebatas memberikan key kecil, sehingga script bebas dari inspeksi network.</span></p>
                <div id="cors-result" class="mt-3 text-xs p-3 rounded-xl hidden overflow-auto max-h-[200px]"></div>
            </div>
            
            <div class="pt-4 border-t border-slate-100">
                <label class="flex items-center space-x-3 cursor-pointer group">
                    <input type="checkbox" name="track_usage" value="1" <?= $track ? 'checked' : '' ?> 
                        class="w-5 h-5 text-accent border-slate-300 rounded focus:ring-accent cursor-pointer">
                    <span class="text-sm font-semibold text-slate-700 group-hover:text-slate-900">Rekam Metadata Penggunaan (Usage Tracking)</span>
                </label>
                <p class="text-xs text-slate-500 mt-2 pl-8">Jika aktif, sistem akan menghitung berapa kali kunci lisensi digunakan (API call).</p>
            </div>
            
        </div>
        
        <div class="p-5 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button type="submit" class="bg-brand hover:bg-[#002b6b] text-white font-semibold py-2.5 px-6 rounded-xl shadow-sm transition-all focus:ring-2 focus:ring-offset-2 focus:ring-brand flex items-center">
                <i data-lucide="save" class="w-4 h-4 mr-2"></i> Simpan Konfigurasi
            </button>
        </div>
    </form>
    </div><!-- End first column -->

    <div class="space-y-8">
    <form method="post" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
        <input type="hidden" name="cl_test_api" value="1">
        <div class="p-6 border-b border-slate-100 bg-slate-50">
            <h2 class="text-lg font-bold text-slate-800 flex items-center">
                <i data-lucide="flask-conical" class="w-5 h-5 mr-3 text-brand"></i>
                Test Koneksi API Fonnte & Mailketing
            </h2>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">No WA Tujuan Tes</label>
                <input type="text" name="test_wa" placeholder="628..." class="w-full border border-slate-200 rounded-lg px-4 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Email Tujuan Tes</label>
                <input type="email" name="test_email" placeholder="email@domain.com" class="w-full border border-slate-200 rounded-lg px-4 py-2 text-sm bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
            </div>
        </div>
        <div class="p-5 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white font-semibold py-2.5 px-6 rounded-xl shadow-sm transition-all focus:ring-2 focus:ring-slate-500 flex items-center">
                <i data-lucide="send" class="w-4 h-4 mr-2"></i> Kirim Tes
            </button>
        </div>
    </form>
    </div><!-- End second column -->
</div><!-- End grid -->
<script>
async function testCORS() {
    const resEl = document.getElementById('cors-result');
    resEl.style.display = 'block';
    resEl.className = 'mt-3 text-xs p-3 rounded-xl overflow-auto max-h-[200px] bg-slate-100 text-slate-800 border border-slate-200';
    resEl.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 inline animate-spin mr-2"></i> Menguji CORS Endpoint...';
    lucide.createIcons();
    
    try {
        const url = '<?= esc_url_raw(rest_url('canvas-app/v1/verify')) ?>';
        const res = await fetch(url, {
            method: 'OPTIONS', // trigger preflight or standard GET
        });
        
        // Let's also do a POST since OPTIONS might pass but POST fails if origin fails.
        // Doing a blind POST to check if CORS is set on webhook/verify
        const res2 = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ test_cors: true })
        });
        
        let dump = '<b>OPTIONS Test:</b> ' + res.status + ' ' + res.statusText + '<br>';
        dump += '<b>POST Test:</b> ' + res2.status + ' ' + res2.statusText + '<br><br>';
        dump += '<b>Response Headers (POST):</b><br>';
        for (let [key, value] of res2.headers.entries()) {
            dump += `<i>${key}</i>: ${value}<br>`;
        }
        resEl.innerHTML = dump;
        resEl.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-800');
        resEl.classList.remove('bg-slate-100', 'text-slate-800', 'border-slate-200');
    } catch(e) {
        resEl.innerHTML = '<b>Error:</b> ' + e.message + '<br><br>CORS mungkin memblokir ini atau endpoint tidak tersedia.';
        resEl.classList.add('bg-red-50', 'border-red-200', 'text-red-800');
        resEl.classList.remove('bg-slate-100', 'text-slate-800', 'border-slate-200');
    }
}
</script>
