<?php
if (!defined('ABSPATH')) exit;

$uid = get_current_user_id();

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lynk_merchant_key'])) {
    if (isset($_POST['cl_webhook_nonce']) && wp_verify_nonce($_POST['cl_webhook_nonce'], 'cl_save_webhook')) {
        update_user_meta($uid, 'cl_lynk_merchant_key', sanitize_text_field($_POST['cl_lynk_merchant_key']));
        cl_insert_history($uid, "Konfigurasi integrasi Webhook (Lynk.id / Mayar) berhasil diperbarui.");
        echo '<div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200">Merchant Key berhasil disimpan.</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scalev_signing_secret'])) {
    if (isset($_POST['cl_webhook_nonce']) && wp_verify_nonce($_POST['cl_webhook_nonce'], 'cl_save_webhook')) {
        update_user_meta($uid, 'cl_scalev_signing_secret', sanitize_text_field($_POST['cl_scalev_signing_secret']));
        cl_insert_history($uid, "Konfigurasi integrasi Webhook (Scalev) berhasil diperbarui.");
        echo '<div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 border border-green-200">Signing Secret Scalev berhasil disimpan.</div>';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate_cl_webhook_secret'])) {
    if (isset($_POST['cl_webhook_nonce']) && wp_verify_nonce($_POST['cl_webhook_nonce'], 'cl_save_webhook')) {
        $secret_key = wp_generate_password(16, false);
        update_user_meta($uid, 'cl_webhook_secret', $secret_key);
        cl_insert_history($uid, "Regenerasi User Key Webhook berhasil dilakukan.");
        echo '<div class="p-4 mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 border border-yellow-200">User Key Webhook berhasil diperbarui. URL webhook lama kini tidak valid lagi, pastikan Anda juga memperbarui URL di platform pihak ketiga.</div>';
    }
}

$secret_key = get_user_meta($uid, 'cl_webhook_secret', true);
if (empty($secret_key)) {
    $secret_key = wp_generate_password(16, false);
    update_user_meta($uid, 'cl_webhook_secret', $secret_key);
}

$lynk_merchant_key = get_user_meta($uid, 'cl_lynk_merchant_key', true);
$scalev_signing_secret = get_user_meta($uid, 'cl_scalev_signing_secret', true);

// Fetch logs
global $wpdb;
$logs_table = $wpdb->prefix . 'cl_webhook_logs';

// Pagination
$per_page = 10;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

$total_logs = $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM $logs_table WHERE user_id = %d", $uid));
$logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM $logs_table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d", $uid, $per_page, $offset));

$total_pages = ceil($total_logs / $per_page);

$webhook_url = rest_url('canvas-app/v1/webhook') . '?user_key=' . $secret_key;
?>

<div class="max-w-6xl mx-auto space-y-6">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-slate-800 tracking-tight">Integrations</h1>
        <p class="text-slate-500 mt-1">Setup integration webhook with Lynk.id atau Mayar.id and test your connection.</p>
    </div>

    <!-- Navigation Tabs -->
    <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-1.5 scrollbar-thin overflow-x-auto">
        <button onclick="switchWebhookTab('tab-lynk')" id="btn-tab-lynk" class="webhook-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-bold text-center transition-all bg-brand text-white shadow-sm flex items-center justify-center gap-2">
            <i data-lucide="link" class="w-4 h-4"></i> Lynk.id
        </button>
        <button onclick="switchWebhookTab('tab-scalev')" id="btn-tab-scalev" class="webhook-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
            <i data-lucide="shopping-bag" class="w-4 h-4"></i> Scalev
        </button>
        <button onclick="switchWebhookTab('tab-mayar')" id="btn-tab-mayar" class="webhook-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
            <i data-lucide="credit-card" class="w-4 h-4"></i> Mayar.id
        </button>
    </div>

    <!-- Webhook Setup Card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Tab 1: Lynk.id -->
        <div id="tab-lynk" class="webhook-tab-content block">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 flex items-center">
                        <i data-lucide="webhook" class="w-5 h-5 text-brand mr-2"></i> Lynk.id Configuration
                    </h3>
                    <p class="text-sm text-slate-500">Integrasikan pembelian melalui Lynk.id dengan webhook url ini.</p>
                </div>
            </div>
            <div class="p-6 space-y-6">
                
                <div class="space-y-4">
                    <div class="relative">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">1. Webhook URL (Copy paste ke Lynk.id)</label>
                        <form method="POST" action="" class="flex">
                            <?php wp_nonce_field('cl_save_webhook', 'cl_webhook_nonce'); ?>
                            <input type="text" id="webhook-url-input-lynk" class="flex-1 rounded-l-lg border-slate-300 bg-slate-50 text-slate-600 focus:border-brand focus:ring-brand sm:text-sm" readonly value="<?= esc_url($webhook_url) ?>">
                            <button type="button" onclick="copyWebhookUrl('webhook-url-input-lynk')" class="inline-flex items-center px-4 py-2 border border-l-0 border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand">
                                <i data-lucide="copy" class="w-4 h-4 mr-2"></i> Copy
                            </button>
                            <button type="submit" name="regenerate_cl_webhook_secret" onclick="return confirm('Apakah Anda yakin ingin melakukan Regenerate Webhook Key? URL Webhook yang lama akan hangus dan tidak dapat digunakan lagi.')" class="inline-flex items-center px-4 py-2 border border-l-0 border-slate-300 rounded-r-lg bg-white text-sm font-medium text-red-500 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 border-l">
                                <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Regenerate
                            </button>
                        </form>
                    </div>

                    <form method="POST" action="" class="relative pt-4 border-t border-slate-100">
                        <?php wp_nonce_field('cl_save_webhook', 'cl_webhook_nonce'); ?>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">2. Lynk.id Merchant Key</label>
                        <p class="text-xs text-slate-500 mb-3 border-l-2 border-brand pl-3">
                            Setelah Anda mensave Webhook URL di dashboard Lynk.id, Lynk.id akan men-generate Merchant Key. Copy-paste key tersebut ke sini untuk verifikasi (X-Lynk-Signature).
                        </p>
                        <div class="flex">
                            <input type="text" name="cl_lynk_merchant_key" placeholder="Masukkan Merchant Key dari Lynk.id" class="flex-1 rounded-l-lg border-slate-300 bg-white text-slate-800 focus:border-brand focus:ring-brand sm:text-sm" value="<?= esc_attr($lynk_merchant_key) ?>" required>
                            <button type="submit" name="save_lynk_merchant_key" class="inline-flex items-center px-4 py-2 border border-transparent rounded-r-lg bg-brand text-sm font-medium text-white hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand shadow-sm">
                                <i data-lucide="save" class="w-4 h-4 mr-2"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="pt-4 border-t border-slate-100">
                    <h4 class="text-sm font-bold text-slate-800 mb-2">Simulasi / Uji Coba Webhook Member (Lynk.id)</h4>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="testWebhook(this, 'license')" class="inline-flex items-center px-4 py-2 bg-brand text-white text-sm font-medium rounded-lg hover:bg-brand-600 transition-colors">
                            <i data-lucide="play" class="w-4 h-4 mr-2"></i> Test Member (Order License)
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 2: Scalev -->
        <div id="tab-scalev" class="webhook-tab-content hidden">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 flex items-center">
                        <i data-lucide="webhook" class="w-5 h-5 text-brand mr-2"></i> Scalev Configuration
                    </h3>
                    <p class="text-sm text-slate-500">Integrasikan pembelian melalui Scalev.id dengan webhook url ini.</p>
                </div>
            </div>
            <div class="p-6 space-y-6">
                
                <div class="space-y-4">
                    <div class="relative">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">1. Webhook URL (Copy paste ke menu Webhooks di App Scalev)</label>
                        <form method="POST" action="" class="flex">
                            <?php wp_nonce_field('cl_save_webhook', 'cl_webhook_nonce'); ?>
                            <input type="text" id="webhook-url-input-scalev" class="flex-1 rounded-l-lg border-slate-300 bg-slate-50 text-slate-600 focus:border-brand focus:ring-brand sm:text-sm" readonly value="<?= esc_url($webhook_url) ?>">
                            <button type="button" onclick="copyWebhookUrl('webhook-url-input-scalev')" class="inline-flex items-center px-4 py-2 border border-l-0 border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand">
                                <i data-lucide="copy" class="w-4 h-4 mr-2"></i> Copy
                            </button>
                            <button type="submit" name="regenerate_cl_webhook_secret" onclick="return confirm('Apakah Anda yakin ingin melakukan Regenerate Webhook Key? URL Webhook yang lama akan hangus dan tidak dapat digunakan lagi.')" class="inline-flex items-center px-4 py-2 border border-l-0 border-slate-300 rounded-r-lg bg-white text-sm font-medium text-red-500 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 border-l">
                                <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Regenerate
                            </button>
                        </form>
                    </div>

                    <form method="POST" action="" class="relative pt-4 border-t border-slate-100">
                        <?php wp_nonce_field('cl_save_webhook', 'cl_webhook_nonce'); ?>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">2. Scalev Signing Secret</label>
                        <p class="text-xs text-slate-500 mb-3 border-l-2 border-brand pl-3">
                            Ambil Signing Secret dari halaman Webhooks di Scalev dan paste ke sini untuk verifikasi payload (X-Scalev-Hmac-Sha256). Pastikan centang event <b>order.updated</b>.
                        </p>
                        <div class="flex">
                            <input type="text" name="cl_scalev_signing_secret" placeholder="Masukkan Signing Secret dari Scalev" class="flex-1 rounded-l-lg border-slate-300 bg-white text-slate-800 focus:border-brand focus:ring-brand sm:text-sm" value="<?= esc_attr($scalev_signing_secret) ?>" required>
                            <button type="submit" name="save_scalev_signing_secret" class="inline-flex items-center px-4 py-2 border border-transparent rounded-r-lg bg-brand text-sm font-medium text-white hover:bg-brand-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand shadow-sm">
                                <i data-lucide="save" class="w-4 h-4 mr-2"></i> Simpan
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="pt-4 border-t border-slate-100">
                    <h4 class="text-sm font-bold text-slate-800 mb-2">Simulasi / Uji Coba Webhook Member (Scalev)</h4>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="testWebhookScalev(this)" class="inline-flex items-center px-4 py-2 bg-brand text-white text-sm font-medium rounded-lg hover:bg-brand-600 transition-colors">
                            <i data-lucide="play" class="w-4 h-4 mr-2"></i> Test Webhook Scalev
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 3: Mayar.id -->
        <div id="tab-mayar" class="webhook-tab-content hidden">
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-800 flex items-center">
                        <i data-lucide="webhook" class="w-5 h-5 text-brand mr-2"></i> Mayar.id Configuration
                    </h3>
                    <p class="text-sm text-slate-500">Integrasikan produk digital Mayar.id. Event webhook secara otomatis dikenali tanpa Merchant Key.</p>
                </div>
            </div>
            <div class="p-6 space-y-6">
                <div class="space-y-4">
                    <div class="relative">
                        <label class="block text-sm font-semibold text-slate-700 mb-1">1. URL Webhook (Copy paste ke menu Integration -> Webhook Mayar)</label>
                        <form method="POST" action="" class="flex">
                            <?php wp_nonce_field('cl_save_webhook', 'cl_webhook_nonce'); ?>
                            <input type="text" id="webhook-url-input-mayar" class="flex-1 rounded-l-lg border-slate-300 bg-slate-50 text-slate-600 focus:border-brand focus:ring-brand sm:text-sm" readonly value="<?= esc_url($webhook_url) ?>">
                            <button type="button" onclick="copyWebhookUrl('webhook-url-input-mayar')" class="inline-flex items-center px-4 py-2 border border-l-0 border-slate-300 bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand">
                                <i data-lucide="copy" class="w-4 h-4 mr-2"></i> Copy
                            </button>
                            <button type="submit" name="regenerate_cl_webhook_secret" onclick="return confirm('Apakah Anda yakin ingin melakukan Regenerate Webhook Key? URL Webhook yang lama akan hangus dan tidak dapat digunakan lagi.')" class="inline-flex items-center px-4 py-2 border border-l-0 border-slate-300 rounded-r-lg bg-white text-sm font-medium text-red-500 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 border-l">
                                <i data-lucide="refresh-cw" class="w-4 h-4 mr-2"></i> Regenerate
                            </button>
                        </form>
                    </div>
                    <div class="pt-4 border-t border-slate-100">
                        <p class="text-sm text-slate-600">Pastikan event yang terpicu di Mayar adalah <b>payment.received</b> atau saat transaksi berhasil dibayar.</p>
                    </div>
                </div>
                
                <div class="pt-4 border-t border-slate-100">
                    <h4 class="text-sm font-bold text-slate-800 mb-2">Simulasi / Uji Coba Webhook Member (Mayar.id)</h4>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="testWebhookMayar(this)" class="inline-flex items-center px-4 py-2 bg-slate-800 text-white text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors">
                            <i data-lucide="play" class="w-4 h-4 mr-2"></i> Test Webhook Mayar
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Webhook Logs -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="text-lg font-bold text-slate-800">Webhook Logs</h3>
                <p class="text-sm text-slate-500">History request webhook yang masuk dari pihak ketiga.</p>
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
                        <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada log request webhook.</td></tr>
                    <?php else: ?>
                        <?php foreach($logs as $log): ?>
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-3 px-6 whitespace-nowrap text-slate-600"><?= esc_html(date('d M Y H:i', strtotime($log->created_at))) ?></td>
                                <td class="py-3 px-6 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
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
                        <a href="?page=sharelink-dashboard&view=webhook&paged=<?= $i ?>" class="px-3 py-1 text-sm border rounded <?= ($i == $page) ? 'bg-brand text-white border-brand' : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Log Detail Modal -->
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
function switchWebhookTab(tabId) {
    document.querySelectorAll('.webhook-tab-content').forEach(el => {
        el.classList.remove('block');
        el.classList.add('hidden');
    });
    
    document.querySelectorAll('.webhook-tab-btn').forEach(btn => {
        btn.className = "webhook-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all flex items-center justify-center gap-2";
    });
    
    const chosenEl = document.getElementById(tabId);
    if(chosenEl) {
        chosenEl.classList.remove('hidden');
        chosenEl.classList.add('block');
    }
    
    const activeBtn = document.getElementById('btn-' + tabId);
    if(activeBtn) {
        activeBtn.className = "webhook-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-bold text-center transition-all bg-brand text-white shadow-sm flex items-center justify-center gap-2";
    }
}

function copyWebhookUrl(inputId) {
    var input = document.getElementById(inputId || "webhook-url-input-lynk");
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

async function testWebhook(btn, type) {
    const originalText = btn.innerHTML;
    btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Sending...`;
    btn.disabled = true;

    try {
        const url = document.getElementById("webhook-url-input-lynk").value;
        const testPayload = {
            "status": "paid",
            "type": type,
            "product_name": type === 'workspace' ? "Sharelink SaaS" : "App Global",
            "customer_name": "Test User",
            "customer_email": "test@solusimarketing.xyz",
            "customer_phone": "6285156234820",
            "test_mode": true
        };

        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(testPayload)
        });

        // if success or fail, we still reload to see the log
        setTimeout(() => {
            window.location.reload();
        }, 1500);

        if(res.ok) {
            showToast("Test webhook terkirim (Sukses)", "success");
        } else {
            showToast("Test webhook selesai (Status " + res.status + ")", "success"); // we say success because the ping went through
        }

    } catch (e) {
        showToast("Error connection to webhook", "error");
        btn.innerHTML = originalText;
        btn.disabled = false;
        if(window.lucide) lucide.createIcons();
    }
}

async function testWebhookMayar(btn) {
    const originalText = btn.innerHTML;
    btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Sending...`;
    btn.disabled = true;

    try {
        const url = document.getElementById("webhook-url-input-mayar").value;
        const testPayload = {
            "event": "payment.received",
            "data": {
                "id": "test-uuid-1234",
                "status": "SUCCESS",
                "customerName": "Test Mayar",
                "customerEmail": "test@solusimarketing.xyz",
                "customerMobile": "6285156234820",
                "productName": "App Global"
            },
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
        if(window.lucide) lucide.createIcons();
    }
}

async function testWebhookScalev(btn) {
    const originalText = btn.innerHTML;
    btn.innerHTML = `<i data-lucide="loader-2" class="w-4 h-4 mr-2 animate-spin"></i> Sending...`;
    btn.disabled = true;

    try {
        const url = document.getElementById("webhook-url-input-scalev").value;
        const testPayload = {
            "type": "scalev",
            "payment_status": "paid",
            "customer_name": "Test Scalev User",
            "customer_email": "test@solusimarketing.xyz",
            "customer_phone": "6285156234820",
            "product_name": "App Global",
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
        if(window.lucide) lucide.createIcons();
    }
}
</script>
