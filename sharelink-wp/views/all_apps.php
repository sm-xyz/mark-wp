<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    echo '<p>Anda tidak memiliki akses ke halaman ini.</p>';
    exit;
}

global $wpdb;
$at = $wpdb->prefix . CL_APPS;
$lt = $wpdb->prefix . CL_LICS;

$s = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Handle search query
$query = "SELECT a.*, u.display_name, u.user_email,
    (SELECT COUNT(*) FROM $lt WHERE app_id=a.id) as lic_total,
    (SELECT COUNT(*) FROM $lt WHERE app_id=a.id AND status='active') as lic_active
    FROM $at a 
    LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID";

if ($s) {
    $query .= $wpdb->prepare(" WHERE a.app_name LIKE %s OR u.display_name LIKE %s", '%' . $wpdb->esc_like($s) . '%', '%' . $wpdb->esc_like($s) . '%');
}

$query .= " ORDER BY a.id DESC";
$all_apps = $wpdb->get_results($query);
?>

<div class="max-w-7xl mx-auto pb-12">
    <div class="bg-blue-50 border border-brand/20 rounded-2xl p-5 flex items-start mb-6">
        <i data-lucide="database" class="w-6 h-6 text-brand mr-4 shrink-0"></i>
        <div>
            <h3 class="font-bold text-brand mb-1">Seluruh Aplikasi Pengguna (Only Admin)</h3>
            <p class="text-sm text-slate-700 leading-relaxed max-w-4xl">
                Halaman ini menampilkan seluruh aplikasi canvas milik semua pengguna di sistem. Anda dapat melihat tautan, lisensi aktif, serta mengunduh atau menyalin full script aplikasinya (HTML/CSS/JS) secara langsung.
            </p>
        </div>
    </div>

    <!-- Main Table Container -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <h2 class="text-base font-bold text-slate-800 flex items-center">
                <i data-lucide="layout-grid" class="w-5 h-5 mr-3 text-brand"></i> Data Semua Aplikasi
            </h2>
            <form class="flex gap-2 text-sm max-w-xs w-full" method="get">
                <input type="hidden" name="page" value="canvaslock">
                <input type="hidden" name="view" value="all_apps">
                <input type="text" name="s" value="<?= esc_attr($s) ?>" placeholder="Cari nama / pemilik..." class="flex-1 w-full border border-slate-200 rounded-lg px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-brand/20">
                <button type="submit" class="bg-brand text-white px-3 py-2 rounded-lg font-semibold hover:bg-[#002b6b] transition text-xs"><i data-lucide="search" class="w-4 h-4"></i></button>
            </form>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-slate-500 text-xs uppercase tracking-widest font-semibold">
                        <th class="px-6 py-4 text-center w-12">No</th>
                        <th class="px-6 py-4">Aplikasi & Pemilik</th>
                        <th class="px-6 py-4 text-center">Lisensi Ter-assign</th>
                        <th class="px-6 py-4">Tautan Akses</th>
                        <th class="px-6 py-4 text-center">Salin Script</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php 
                    if ($all_apps): 
                        $no = 1;
                        foreach($all_apps as $a): 
                            $custom_url = rtrim(home_url(), '/') . '/ai/' . $a->custom_slug;
                    ?>
                    <tr class="hover:bg-slate-50/50 transition-colors">
                        <td class="px-6 py-4 text-center text-slate-400 font-bold"><?= $no++ ?></td>
                        <td class="px-6 py-4">
                            <div class="font-bold text-slate-800 text-base"><?= esc_html($a->app_name) ?></div>
                            <div class="text-xs text-slate-500 mt-1 flex items-center gap-1">
                                <i data-lucide="user" class="w-3.5 h-3.5 text-slate-400"></i>
                                Pemilik: <span class="font-semibold text-brand"><?= esc_html($a->display_name ?: 'System Admin') ?></span> (<?= esc_html($a->user_email ?: 'admin') ?>)
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-block px-2.5 py-1 bg-blue-50 text-brand border border-brand/10 rounded-full font-bold text-xs shadow-sm">
                                <?= $a->lic_active ?> Aktif / <?= $a->lic_total ?> Total
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <?php if (empty($a->canvas_link)): ?>
                                <span class="text-xs text-slate-400 italic">Belum disetting</span>
                            <?php else: ?>
                                <div class="space-y-1">
                                    <div class="truncate max-w-[220px]" title="<?= esc_attr($a->canvas_link) ?>">
                                        <span class="text-[10px] text-slate-400 uppercase block font-semibold">Gemini Link</span>
                                        <a href="<?= esc_url($a->canvas_link) ?>" target="_blank" class="text-brand text-xs hover:underline font-medium"><?= esc_html($a->canvas_link) ?></a>
                                    </div>
                                    <?php if ($a->custom_slug): ?>
                                        <div class="truncate max-w-[220px]">
                                            <span class="text-[10px] text-slate-400 uppercase block font-semibold">Custom Slug</span>
                                            <a href="<?= esc_url($custom_url) ?>" target="_blank" class="text-blue-600 text-xs hover:underline font-semibold font-mono"><?= esc_html($custom_url) ?></a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <?php if (!empty($a->payload)): ?>
                                <button type="button" onclick="openScriptPopup('<?= esc_js($a->app_name) ?>', document.getElementById('script_content_<?= $a->id ?>').value)" class="px-4 py-2 bg-slate-100 hover:bg-brand hover:text-white border border-slate-250 text-slate-700 font-bold rounded-lg text-xs transition-all shadow-sm flex items-center gap-1.5 mx-auto">
                                    <i data-lucide="code" class="w-3.5 h-3.5"></i> Get Script
                                </button>
                                <textarea id="script_content_<?= $a->id ?>" class="hidden"><?= esc_textarea($a->payload) ?></textarea>
                            <?php else: ?>
                                <span class="text-xs text-slate-400 italic">Script belum diunggah</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="5" class="p-8 text-center text-slate-500">Belum ada aplikasi yang terdaftar.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Overlay for Script Sharing -->
<div id="cl-script-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl p-6 border border-slate-200">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-slate-800 text-lg flex items-center gap-2">
                <i data-lucide="terminal" class="w-5 h-5 text-brand"></i>
                Full Script (HTML/CSS/JS): <span id="script_modal_app_name" class="text-brand"></span>
            </h3>
            <button type="button" onclick="closeScriptPopup()" class="text-slate-400 hover:text-slate-600 bg-slate-50 hover:bg-slate-100 rounded-lg p-2">
                <i data-lucide="x" class="w-4 h-4"></i>
            </button>
        </div>
        
        <div class="bg-slate-950 rounded-xl p-4 h-[350px] mb-5">
            <textarea id="script_modal_textarea" readonly class="w-full h-full bg-transparent border-none text-xs font-mono text-slate-300 focus:outline-none focus:ring-0 resize-none leading-relaxed" spellcheck="false"></textarea>
        </div>
        
        <div class="flex gap-3">
            <button type="button" onclick="closeScriptPopup()" class="flex-1 px-4 py-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-semibold transition-all">Tutup</button>
            <button type="button" onclick="copyModalScript()" class="flex-1 bg-brand hover:bg-[#002b6b] text-white font-bold py-2.5 rounded-xl text-sm shadow-sm transition-all flex items-center justify-center gap-1.5">
                <i data-lucide="copy" class="w-4 h-4"></i> Salin Script
            </button>
        </div>
    </div>
</div>

<script>
function openScriptPopup(appName, scriptContent) {
    document.getElementById('script_modal_app_name').innerText = appName;
    document.getElementById('script_modal_textarea').value = scriptContent;
    document.getElementById('cl-script-modal').classList.remove('hidden');
}

function closeScriptPopup() {
    document.getElementById('cl-script-modal').classList.add('hidden');
}

function copyModalScript() {
    const el = document.getElementById('script_modal_textarea');
    el.select();
    document.execCommand('copy');
    showToast('Full script aplikasi berhasil disalin ke clipboard!');
}
</script>
