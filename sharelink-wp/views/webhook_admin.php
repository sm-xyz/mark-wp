<?php
if (!defined('ABSPATH')) exit;

$is_admin = current_user_can('manage_options');

if (!$is_admin) {
    wp_die('Access denied.');
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_admin_lynk_key'])) {
    if (isset($_POST['cl_admin_webhook_nonce']) && wp_verify_nonce($_POST['cl_admin_webhook_nonce'], 'cl_save_admin_webhook')) {
        update_option('cl_admin_lynk_merchant_key', sanitize_text_field($_POST['cl_admin_lynk_merchant_key']));
        update_option('cl_admin_saas_product_name', sanitize_text_field($_POST['cl_admin_saas_product_name']));
        echo '<div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200">Pengaturan Webhook Admin berhasil disimpan.</div>';
    }
}

$admin_member_secret = get_user_meta(get_current_user_id(), 'cl_webhook_secret', true);
if (empty($admin_member_secret)) {
    $admin_member_secret = wp_generate_password(16, false);
    update_user_meta(get_current_user_id(), 'cl_webhook_secret', $admin_member_secret);
}

$lynk_merchant_key = get_option('cl_admin_lynk_merchant_key', '');
$saas_product_name = get_option('cl_admin_saas_product_name', 'Sharelink SaaS Workspace');

// Fetch logs
global $wpdb;
$logs_table = $wpdb->prefix . 'cl_webhook_logs';

// Pagination
$per_page = 10;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

$total_logs = $wpdb->get_var("SELECT COUNT(id) FROM $logs_table WHERE user_id = 0");
$logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $logs_table WHERE user_id = 0 ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset));

$total_pages = ceil($total_logs / $per_page);

$webhook_url = rest_url('canvas-app/v1/webhook') . '?user_key=' . $admin_member_secret;
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Admin Integrations</h1>
        <p class="text-slate-500 mt-1">Setup integration webhook with Lynk.id to automatically generate Workspaces (SaaS) for buyers.</p>
    </div>

    <!-- Webhook URL Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="text-lg font-bold text-slate-800 flex items-center">
                    <i data-lucide="webhook" class="w-5 h-5 text-brand mr-2"></i> Admin Endpoint Configuration
                </h3>
                <p class="text-sm text-slate-500">Karena Lynk.id hanya mendukung 1 Webhook URL, gunakan Member Webhook URL berikut untuk SaaS anda.</p>
            </div>
        </div>
        <div class="p-6 space-y-6">
            
            <form method="POST" action="" class="space-y-4">
                <?php wp_nonce_field('cl_save_admin_webhook', 'cl_admin_webhook_nonce'); ?>
                
                <div class="relative">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">1. Webhook URL (Copy paste ke Lynk.id)</label>
                    <div class="flex">
                        <input type="text" id="webhook-url-input" class="flex-1 rounded-l-lg border-slate-300 bg-slate-50 text-slate-600 focus:border-brand focus:ring-brand sm:text-sm" readonly value="<?= esc_url($webhook_url) ?>">
                        <button type="button" onclick="copyWebhookUrl()" class="inline-flex items-center px-4 py-2 border border-l-0 border-slate-300 rounded-r-lg bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand">
                            <i data-lucide="copy" class="w-4 h-4 mr-2"></i> Copy
                        </button>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 relative">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">2. Admin Lynk.id Merchant Key</label>
                    <p class="text-xs text-slate-500 mb-3 border-l-2 border-brand pl-3">
                        Setelah Anda menyimpan Webhook URL di atas pada dashboard Lynk.id, Lynk.id akan men-generate Merchant Key.
                    </p>
                    <input type="text" name="cl_admin_lynk_merchant_key" placeholder="Masukkan Merchant Key dari Lynk.id" class="w-full rounded-lg border-slate-300 bg-white text-slate-800 focus:border-brand focus:ring-brand sm:text-sm" value="<?= esc_attr($lynk_merchant_key) ?>" required>
                </div>
                
                <div class="pt-4 border-t border-slate-100 relative">
                    <label class="block text-sm font-semibold text-slate-700 mb-1">3. Nama Produk SaaS di Lynk.id</label>
                    <p class="text-xs text-slate-500 mb-3 border-l-2 border-brand pl-3">
                        Masukkan nama produk persis seperti yang Anda buat di Lynk.id. Jika pesanan cocok dengan nama ini, Sharelink akan Membuatkan Workspace untuk pembeli. Jika tidak cocok, Sharelink akan mencari Canvas App Anda (Mode Penjualan Lisensi).
                    </p>
                    <input type="text" name="cl_admin_saas_product_name" placeholder="Misal: Pendaftaran Sharelink SaaS" class="w-full rounded-lg border-slate-300 bg-white text-slate-800 focus:border-brand focus:ring-brand sm:text-sm" value="<?= esc_attr($saas_product_name) ?>" required>
                </div>
                
                <div class="pt-2 flex justify-end">
                    <button type="submit" name="save_admin_lynk_key" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg bg-brand text-sm font-medium text-white hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand shadow-sm">
                        <i data-lucide="save" class="w-4 h-4 mr-2"></i> Simpan Pengaturan
                    </button>
                </div>
            </form>
            
            <div class="pt-4 border-t border-slate-100">
                <h4 class="text-sm font-bold text-slate-800 mb-2">Simulasi / Uji Coba Webhook Admin</h4>
                <div class="flex flex-col sm:flex-row gap-3">
                    <button onclick="testWebhookAdmin(this)" class="inline-flex items-center px-4 py-2 bg-brand text-white text-sm font-medium rounded-lg hover:bg-brand-600 transition-colors">
                        <i data-lucide="play" class="w-4 h-4 mr-2"></i> Test Admin (Order Workspace)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Webhook Logs -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="text-lg font-bold text-slate-800">Admin Webhook Logs</h3>
                <p class="text-sm text-slate-500">History request webhook yang masuk untuk generate Workspace.</p>
            </div>
            <button onclick="window.location.reload()" class="bg-white border text-slate-600 border-slate-300 hover:bg-slate-50 px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center shadow-sm">
                 <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Refresh
            </button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100">
                        <th class="py-3 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Date</th>
                        <th class="py-3 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Source</th>
                        <th class="py-3 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="py-3 px-6 text-xs font-semibold text-slate-500 uppercase tracking-wider">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada log request webhook admin.</td></tr>
                    <?php else: ?>
                        <?php foreach($logs as $log): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-6 whitespace-nowrap text-slate-600"><?= esc_html(date('d M Y H:i', strtotime($log->created_at))) ?></td>
                                <td class="py-3 px-6 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                        <?= esc_html(strtoupper($log->event_source)) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-6 whitespace-nowrap">
                                    <?php if($log->status_code >= 200 && $log->status_code < 300): ?>
                                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Success (<?= esc_html($log->status_code) ?>)</span>
                                    <?php else: ?>
                                      <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Failed (<?= esc_html($log->status_code) ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-6">
                                    <button onclick="viewLogPayload(`<?= htmlspecialchars(json_encode([
                                        'payload' => json_decode($log->payload),
                                        'response' => json_decode($log->response)
                                    ])) ?>`)" class="text-brand hover:text-brand-600 text-sm font-medium">Lihat Detail</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
                <div class="text-sm text-slate-500">
                    Showing <?= esc_html($offset + 1) ?> to <?= esc_html(min($offset + $per_page, $total_logs)) ?> of <?= esc_html($total_logs) ?> entries
                </div>
                <div class="flex gap-1">
                    <?php for($i=1; $i<=$total_pages; $i++): ?>
                        <a href="?page=sharelink-dashboard&view=webhook_admin&paged=<?= $i ?>" class="px-3 py-1 text-sm border rounded <?= ($i == $page) ? 'bg-brand text-white border-brand' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Detail Modal (same as member's) -->
<div id="cl-log-modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[60] flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-3xl w-full shadow-2xl p-6 border border-slate-200">
        <div class="flex justify-between items-center mb-5">
            <div>
                <h3 class="text-lg font-bold text-slate-800">Detail Webhook Log</h3>
                <p class="text-sm text-slate-500">Raw JSON data dari Payload & Response</p>
            </div>
            <button onclick="document.getElementById('cl-log-modal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>
        <div class="space-y-4 max-h-[60vh] overflow-y-auto">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Request Payload:</label>
                <div class="bg-slate-900 rounded-xl p-4">
                    <pre id="log-payload-pre" class="text-xs text-green-400 font-mono whitespace-pre-wrap"></pre>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">Endpoint Response:</label>
                <div class="bg-slate-900 rounded-xl p-4">
                    <pre id="log-response-pre" class="text-xs text-blue-400 font-mono whitespace-pre-wrap"></pre>
                </div>
            </div>
        </div>
        <div class="mt-6 flex justify-end">
             <button onclick="document.getElementById('cl-log-modal').classList.add('hidden')" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium rounded-xl transition-colors">Tutup</button>
        </div>
    </div>
</div>

<script>
function copyWebhookUrl() {
    var input = document.getElementById("webhook-url-input");
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value);
    showToast("Webhook URL disalin!", "success");
}

function viewLogPayload(dataStr) {
    try {
        const data = JSON.parse(dataStr);
        document.getElementById('log-payload-pre').textContent = JSON.stringify(data.payload, null, 2);
        document.getElementById('log-response-pre').textContent = JSON.stringify(data.response, null, 2);
        document.getElementById('cl-log-modal').classList.remove('hidden');
    } catch(e) {
        showToast("Gagal memuat detail log", "error");
    }
}

async function testWebhookAdmin(btn) {
    const originalText = btn.innerHTML;
    btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Sending...`;
    btn.disabled = true;

    try {
        const url = document.getElementById("webhook-url-input").value;
        const randId = Math.floor(Math.random() * 90000) + 10000;
        const testPayload = {
            "status": "paid",
            "type": "workspace",
            "product_name": "<?= esc_js($saas_product_name) ?>",
            "customer_name": "Test User Admin " + randId,
            "customer_email": "test@solusimarketing.xyz",
            "customer_phone": "6285156234820",
            "test_mode": true
        };

        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(testPayload)
        });

        setTimeout(() => {
            window.location.reload();
        }, 1500);

        if(res.ok) {
            showToast("Test webhook terkirim (Sukses)", "success");
        } else {
            showToast("Test webhook selesai (Status " + res.status + ")", "success");
        }

    } catch (e) {
        showToast("Error connection to webhook", "error");
        btn.innerHTML = originalText;
        btn.disabled = false;
        lucide.createIcons();
    }
}
</script>
