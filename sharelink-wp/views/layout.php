<?php
if (!defined('ABSPATH')) exit;
$view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'dashboard';

$site_icon = get_site_icon_url(512);
$pwa_icon = empty($site_icon) ? 'https://sm-xyz.github.io/embed/SM-Logo-smxyz-500-full.png' : $site_icon;
$manifest = [
    "name" => "ShareLink AI Dashboard",
    "short_name" => "ShareLink AI",
    "start_url" => admin_url('admin.php?page=canvaslock'),
    "display" => "standalone",
    "background_color" => "#f8fafc",
    "theme_color" => "#003888",
    "icons" => [["src" => $pwa_icon, "sizes" => "512x512", "type" => "image/png", "purpose" => "any maskable"]]
];
$manifest_json = "data:application/manifest+json;base64," . base64_encode(json_encode($manifest));
?>
<!-- Full Screen Takeover for UI -->
<link rel="manifest" href="<?= $manifest_json ?>">
<meta name="theme-color" content="#003888">
<style>
    html, body { width: 100%; height: 100%; overflow: hidden; }
    html, body, html.wp-toolbar, html.wp-toolbar body { margin: 0 !important; margin-top: 0 !important; padding: 0 !important; padding-top: 0 !important; }
    #wpadminbar, #adminmenuwrap, #adminmenuback, #wpfooter, .update-nag, .notice { display: none !important; }
    #wpcontent, #wpbody-content { margin: 0 !important; padding: 0 !important; height: 100%; overflow: auto; background-color: #f8fafc; }
    #wpbody { display: flex; flex-direction: column; height: 100%; }
    @media screen and (max-width: 782px) {
        html, body, html.wp-toolbar, html.wp-toolbar body { margin-top: 0 !important; padding-top: 0 !important; }
    }
</style>
<script>
    const originalWarn = console.warn;
    console.warn = function() {
        if (arguments[0] && typeof arguments[0] === 'string' && arguments[0].includes('cdn.tailwindcss.com should not be used in production')) return;
        originalWarn.apply(console, arguments);
    };
</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: '#003888',
          accent: '#ff6600',
          accentHover: '#e65c00'
        }
      }
    }
  }
</script>
<script src="https://unpkg.com/lucide@latest"></script>

<div class="fixed inset-0 flex bg-slate-50 text-slate-800 font-sans z-[99999]" style="font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;">
    <!-- Mobile overlay -->
    <div id="mobile-overlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <div id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-brand text-white flex flex-col z-50 transition-transform transform -translate-x-full md:translate-x-0 md:relative shadow-xl border-r border-[#002b6b]">
        <div class="flex items-center justify-between h-16 px-6 border-b border-black/10 shrink-0 bg-black/10">
            <div class="flex items-center">
                <img src="https://sm-xyz.github.io/embed/SM-Logo-smxyz-500-full.png" alt="Logo" class="w-8 h-8 rounded shrink-0 mr-3 object-contain bg-white p-1">
                <span class="font-bold text-lg text-white tracking-tight">Share<span class="text-amber-400">Link</span> AI</span>
            </div>
            <button class="md:hidden text-white/70 hover:text-white" onclick="toggleSidebar()">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <div class="px-6 py-4 border-b border-black/10 flex flex-col">
            <span class="text-xs font-semibold text-white/50 uppercase tracking-widest mb-1">Workspace</span>
            <div class="flex items-center gap-2 text-white font-medium">
                <div class="w-6 h-6 rounded-full bg-accent text-white flex items-center justify-center text-xs font-bold shrink-0">
                    <?= strtoupper(substr(wp_get_current_user()->display_name, 0, 1)) ?>
                </div>
                <span class="truncate"><?= esc_html(wp_get_current_user()->display_name) ?></span>
            </div>
        </div>

        <nav class="flex-1 py-4 px-4 space-y-1.5 overflow-y-auto">
            <?php
            $is_admin = current_user_can('manage_options');
            
            if ($is_admin) {
                $sections = [
                    'admin_area' => [
                        'label' => 'Admin Area',
                        'color' => 'text-emerald-400',
                        'items' => [
                            'workspaces'   => ['icon' => 'users', 'label' => 'Workspaces'],
                            'all_apps'     => ['icon' => 'database', 'label' => 'All Apps'],
                            'all_customers' => ['icon' => 'user-check', 'label' => 'All App User'],
                            'webhook_admin'=> ['icon' => 'webhook', 'label' => 'Webhook Admin'],
                            'settings'     => ['icon' => 'settings', 'label' => 'Global Setting'],
                            'admin_text'   => ['icon' => 'file-text', 'label' => 'Admin Text'],
                        ]
                    ],
                    'member_area' => [
                        'label' => 'Member Area',
                        'color' => 'text-amber-400',
                        'items' => [
                            'dashboard'    => ['icon' => 'layout-dashboard', 'label' => 'Dashboard'],
                            'analytics'    => ['icon' => 'bar-chart-2', 'label' => 'Analytics'],
                            'apps'         => ['icon' => 'grid', 'label' => 'Canvas Apps'],
                            'licenses'     => ['icon' => 'key', 'label' => 'Lisensi'],
                            'customer_members' => ['icon' => 'user-check', 'label' => 'App User'],
                            'webhook'      => ['icon' => 'webhook', 'label' => 'Integrations'],
                            'autoresponder'=>['icon' => 'message-square-dashed', 'label' => 'Autoresponder'],
                            'history'      => ['icon' => 'history', 'label' => 'Riwayat & Log'],
                            'tutorial'     => ['icon' => 'help-circle', 'label' => 'Tutorial'],
                            'profile'      => ['icon' => 'user-cog', 'label' => 'Edit Profile'],
                        ]
                    ]
                ];
            } else {
                $sections = [
                    'member_area' => [
                        'label' => 'Member Area',
                        'color' => 'text-amber-400',
                        'items' => [
                            'dashboard'    => ['icon' => 'layout-dashboard', 'label' => 'Dashboard'],
                            'analytics'    => ['icon' => 'bar-chart-2', 'label' => 'Analytics'],
                            'apps'         => ['icon' => 'grid', 'label' => 'Canvas Apps'],
                            'licenses'     => ['icon' => 'key', 'label' => 'Lisensi'],
                            'customer_members' => ['icon' => 'user-check', 'label' => 'App User'],
                            'webhook'      => ['icon' => 'webhook', 'label' => 'Integrations'],
                            'autoresponder'=>['icon' => 'message-square-dashed', 'label' => 'Autoresponder'],
                            'history'      => ['icon' => 'history', 'label' => 'Riwayat & Log'],
                            'tutorial'     => ['icon' => 'help-circle', 'label' => 'Tutorial'],
                            'profile'      => ['icon' => 'user-cog', 'label' => 'Edit Profile'],
                        ]
                    ]
                ];
            }

            foreach ($sections as $section_id => $sec) {
                echo "<div class='mt-6 px-4 mb-2 select-none border-b border-white/10 pb-1'>";
                echo "<span class='text-[10px] font-bold {$sec['color']} uppercase tracking-widest'>{$sec['label']}</span>";
                echo "</div>";
                
                foreach ($sec['items'] as $key => $nav) {
                    $is_active = $view === $key;
                    $active_class = $is_active ? 'bg-white/10 text-white font-bold ring-1 ring-white/20' : 'hover:bg-white/5 text-white/70 hover:text-white';
                    $url = admin_url("admin.php?page=canvaslock&view={$key}");
                    echo "<a href='{$url}' class='flex items-center px-4 py-3 rounded-xl transition-all text-sm font-medium {$active_class}'>";
                    echo "<i data-lucide='{$nav['icon']}' class='w-5 h-5 mr-3'></i> {$nav['label']}</a>";
                }
            }
            ?>
        </nav>
        <div class="p-4 border-t border-black/10 bg-black/10">
            <a href="<?= wp_logout_url(wp_login_url()) ?>" class="flex items-center px-4 py-3 text-sm font-bold text-red-300 hover:text-white hover:bg-red-500/20 rounded-xl transition-all">
                <i data-lucide="log-out" class="w-5 h-5 mr-3"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <!-- Top Header -->
        <header class="h-16 bg-white shadow-sm border-b border-slate-200 flex items-center px-4 md:px-8 shrink-0 z-40 justify-between gap-2">
            <div class="flex items-center min-w-0 flex-1">
                <button class="md:hidden text-slate-500 hover:text-brand mr-4 shrink-0" onclick="toggleSidebar()">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
                <h1 class="text-lg md:text-xl font-bold text-brand tracking-tight capitalize truncate">
                    <?php 
                        $page_title = str_replace(['_', '-'], ' ', $view);
                        if ($view === 'generator') $page_title = 'Login Script Generator';
                        if ($view === 'admin_text') $page_title = 'Admin Text';
                        if ($view === 'customer_members') $page_title = 'App User';
                        if ($view === 'tutorial') $page_title = 'Tutorial';
                        echo esc_html(ucwords($page_title));
                    ?>
                </h1>
            </div>
            
            <div class="flex items-center gap-2 md:gap-4 shrink-0">
                <?php
                global $wpdb;
                $uid = get_current_user_id();
                $ht = $wpdb->prefix . 'cl_history';
                $latest_hist = $wpdb->get_results($wpdb->prepare("SELECT * FROM $ht WHERE user_id=%d ORDER BY id DESC LIMIT 5", $uid));
                $count_hist = count($latest_hist);
                
                $last_read_id = (int)get_user_meta($uid, 'cl_last_notif_read', true);
                $has_unread = false;
                if ($count_hist > 0 && $latest_hist[0]->id > $last_read_id) {
                    $has_unread = true;
                }
                ?>
                <div class="relative" id="cl-notif-container">
                    <button onclick="toggleNotif(event)" class="relative p-2 text-slate-500 hover:text-brand bg-slate-100 hover:bg-slate-200 rounded-lg transition-all shadow-sm">
                        <i data-lucide="bell" class="w-5 h-5"></i>
                        <?php if($has_unread): ?>
                        <span id="cl-notif-badge" class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full ring-2 ring-white"></span>
                        <?php endif; ?>
                    </button>
                    <!-- Dropdown -->
                    <div id="cl-notif-dropdown" class="hidden absolute right-0 mt-2 w-[280px] sm:w-80 bg-white rounded-xl shadow-2xl border border-slate-200 z-[999] overflow-hidden transition-all" style="max-height: 400px; max-width: calc(100vw - 20px);">
                        <div class="flex flex-col h-full max-h-[400px]">
                            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                                <span class="font-bold text-slate-800">Notifikasi</span>
                                <a href="<?= admin_url('admin.php?page=canvaslock&view=history') ?>" class="text-xs text-brand hover:underline font-semibold">Lihat Semua</a>
                            </div>
                            <div class="overflow-y-auto w-full flex-1">
                                <?php if($count_hist > 0): foreach($latest_hist as $hl): 
                                    $isSuccess = $hl->type === 'success';
                                ?>
                                <div class="px-4 py-3 border-b border-slate-50 hover:bg-slate-50 transition-colors flex items-start gap-3">
                                    <div class="mt-0.5 shrink-0">
                                        <i data-lucide="<?= $isSuccess ? 'check-circle-2' : ($hl->type === 'error' ? 'alert-circle' : 'info') ?>" class="w-4 h-4 <?= $isSuccess ? 'text-emerald-500' : ($hl->type === 'error' ? 'text-red-500' : 'text-blue-500') ?>"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm text-slate-700 leading-snug whitespace-normal" style="word-break: break-word;"><?= esc_html($hl->message) ?></p>
                                        <span class="text-[10px] text-slate-400 mt-1 block"><?= esc_html(wp_date('d M Y H:i', strtotime($hl->created_at))) ?></span>
                                    </div>
                                </div>
                                <?php endforeach; else: ?>
                                <div class="px-4 py-6 text-center text-slate-400 text-sm">Tidak ada notifikasi.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- click outside to close notif -->
                <script>
                function toggleNotif(e) {
                    if (e) {
                        e.stopPropagation();
                    }
                    const drop = document.getElementById('cl-notif-dropdown');
                    if (drop) {
                        drop.classList.toggle('hidden');
                        if (!drop.classList.contains('hidden')) {
                            const badge = document.getElementById('cl-notif-badge');
                            if (badge && badge.style.display !== 'none') {
                                badge.style.display = 'none';
                                fetch('<?= admin_url('admin-ajax.php') ?>', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: 'action=cl_mark_notif_read&_ajax_nonce=<?= wp_create_nonce('cl_notif_read') ?>'
                                });
                            }
                        }
                    }
                }
                document.addEventListener('click', function(e) {
                    const dropdown = document.getElementById('cl-notif-dropdown');
                    const btn = document.querySelector('button[onclick="toggleNotif(event)"]');
                    if (dropdown && !dropdown.classList.contains('hidden')) {
                        if (!dropdown.contains(e.target) && (!btn || !btn.contains(e.target))) {
                            dropdown.classList.add('hidden');
                        }
                    }
                });
                </script>

                <button onclick="window.location.reload(true)" class="bg-indigo-50 hover:bg-indigo-100 text-indigo-700 px-3 py-2 rounded-lg text-sm font-semibold transition-all border border-indigo-200 flex items-center shadow-sm" title="Refresh">
                    <i data-lucide="refresh-cw" class="w-4 h-4 md:mr-2"></i> <span class="hidden md:inline">Refresh</span>
                </button>
                <?php if ($is_admin): ?>
                <a href="<?= admin_url() ?>" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-semibold transition-all border border-slate-200 flex items-center shadow-sm" title="WP Admin">
                    <i data-lucide="layout-grid" class="w-4 h-4 md:mr-2"></i> <span class="hidden md:inline">WP Admin</span>
                </a>
                <?php endif; ?>
            </div>
        </header>
        
        <!-- Scrollable Page Content -->
        <main class="flex-1 overflow-auto p-4 md:p-6 bg-slate-50 relative">
            <div class="w-full mx-auto min-h-full pb-16 2xl:px-8">
                <div id="toast-container" class="fixed bottom-6 right-6 z-50 flex flex-col gap-3"></div>
                <?php
                $file = CL_PLUGIN_DIR . "views/{$view}.php";
                if (file_exists($file)) {
                    require $file;
                } else {
                    echo "<div class='p-5 bg-red-50 text-red-700 rounded-xl border border-red-200 font-medium flex items-center'><i data-lucide='alert-triangle' class='w-5 h-5 mr-3'></i> Tampilan tidak ditemukan.</div>";
                }
                ?>
            </div>
        </main>
    </div>
</div>

<script>
    lucide.createIcons();
    
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        sidebar.classList.toggle('-translate-x-full');
        sidebar.classList.toggle('translate-x-0');
        overlay.classList.toggle('hidden');
    }

    function showToast(msg, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const isSuccess = type === 'success';
        
        toast.className = `transform transition-all duration-300 translate-y-10 opacity-0 flex items-center px-5 py-4 rounded-xl shadow-xl text-sm font-medium ${isSuccess ? 'bg-brand text-white border border-brand' : 'bg-red-50 text-red-600 border border-red-200'}`;
        toast.innerHTML = `<i data-lucide="${isSuccess ? 'check-circle-2' : 'alert-circle'}" class="w-5 h-5 mr-3 ${isSuccess ? 'text-accent' : 'text-red-500'}"></i> <span>${msg}</span>`;
        container.appendChild(toast);
        lucide.createIcons();
        
        requestAnimationFrame(() => {
            setTimeout(() => toast.classList.remove('translate-y-10', 'opacity-0'), 10);
        });
        
        setTimeout(() => {
            toast.classList.add('translate-y-10', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
</script>
