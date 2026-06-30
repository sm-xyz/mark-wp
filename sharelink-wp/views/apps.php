<?php
if (!defined('ABSPATH')) exit;
global $wpdb;
$at = $wpdb->prefix . CL_APPS; $lt = $wpdb->prefix . CL_LICS;
$uid = get_current_user_id();

$api_url = rtrim(get_rest_url(null, 'canvas-app/v1/verify'), '/');

$eid  = intval($_GET['edit'] ?? $_POST['edit_id'] ?? 0);
$erow = $eid > 0 ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $at WHERE id=%d AND user_id=%d", $eid, $uid)) : null;

// Populate values from database
$app_name = $erow ? $erow->app_name : '';
$description = $erow ? $erow->description : '';
$canvas_link = $erow ? $erow->canvas_link : '';
$custom_slug = $erow ? $erow->custom_slug : '';
$payload = $erow ? $erow->payload : '';

$gk = ($erow && $erow->gk_config) ? json_decode($erow->gk_config, true) : [];
$vis_placeholder = $gk['vis_placeholder'] ?? 'Ketik Lisensi Cth: CL-XXXX';
$vis_btn_text = $gk['vis_btn_text'] ?? 'Buka Kunci';
$vis_bg_color = $gk['vis_bg_color'] ?? '#003888';
$vis_btn_color = $gk['vis_btn_color'] ?? '#ff6600';
$vis_no_license_text = $gk['vis_no_license_text'] ?? 'Belum punya lisensi?';
$vis_buy_text = $gk['vis_buy_text'] ?? 'Beli di Sini';
$lynk_id_url = $gk['lynk_id_url'] ?? '';
$logo_url = $gk['vis_logo'] ?? '';
$webhook_default_limit = $gk['webhook_default_limit'] ?? 100;
$webhook_default_expired = $gk['webhook_default_expired'] ?? 0;

// Handle redirected toast messages
if (isset($_GET['saved'])) {
    echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Aplikasi & login script berhasil disimpan!'));</script>";
}

// Handle Form Submission (Generate / Save)
if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act')) {
    $name = sanitize_text_field($_POST['app_name'] ?? '');
    $desc = sanitize_text_field($_POST['description'] ?? '');
    $payload_post = isset($_POST['payload']) ? wp_unslash($_POST['payload']) : '';
    
    // Extract from markdown wrappers if user pasted directly from AI
    if ($payload_post) {
        $payload_post = trim($payload_post);
        if (preg_match('/```[a-zA-Z0-9-]*\s+(.*?)```/is', $payload_post, $matches)) {
            $payload_post = trim($matches[1]);
        }
    }
    
    $canvas_link_post = isset($_POST['canvas_link']) ? esc_url_raw($_POST['canvas_link']) : ($erow ? $erow->canvas_link : '');
    $custom_slug_post = isset($_POST['custom_slug']) ? sanitize_title($_POST['custom_slug']) : ($erow ? $erow->custom_slug : '');
    if ($custom_slug_post === '') {
        $custom_slug_post = null;
    }
    
    $vis_placeholder_post = sanitize_text_field($_POST['vis_placeholder'] ?? '');
    $vis_btn_text_post = sanitize_text_field($_POST['vis_btn_text'] ?? '');
    $vis_bg_color_post = sanitize_hex_color($_POST['vis_bg_color'] ?? '#003888');
    $vis_btn_color_post = sanitize_hex_color($_POST['vis_btn_color'] ?? '#ff6600');
    $vis_no_license_text_post = sanitize_text_field($_POST['vis_no_license_text'] ?? '');
    $vis_buy_text_post = sanitize_text_field($_POST['vis_buy_text'] ?? '');
    $lynk_id_url_post = esc_url_raw($_POST['lynk_id_url'] ?? '');
    $webhook_default_limit_post = intval($_POST['webhook_default_limit'] ?? 100);
    $webhook_default_expired_post = intval($_POST['webhook_default_expired'] ?? 0);
    
    // Logo Upload Handling
    $logo_saved = $_POST['existing_logo_url'] ?? '';
    if (!empty($_FILES['vis_logo_file']['name'])) {
        $uploaded = wp_handle_upload($_FILES['vis_logo_file'], ['test_form' => false]);
        if (!isset($uploaded['error']) && isset($uploaded['url'])) {
            if ($_FILES['vis_logo_file']['size'] <= 1048576) {
                $logo_saved = $uploaded['url'];
            } else {
                echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Ukuran file logo melebihi batas 1MB.', 'error'));</script>";
            }
        }
    }

    $gk_config_arr = [
        'vis_placeholder' => $vis_placeholder_post,
        'vis_btn_text' => $vis_btn_text_post,
        'vis_bg_color' => $vis_bg_color_post,
        'vis_btn_color' => $vis_btn_color_post,
        'vis_no_license_text' => $vis_no_license_text_post,
        'vis_buy_text' => $vis_buy_text_post,
        'lynk_id_url' => $lynk_id_url_post,
        'vis_logo' => $logo_saved,
        'webhook_default_limit' => $webhook_default_limit_post,
        'webhook_default_expired' => $webhook_default_expired_post
    ];

    if ($eid > 0) {
        $existing_gk_str = $wpdb->get_var($wpdb->prepare("SELECT gk_config FROM $at WHERE id=%d", $eid));
        $existing_gk = json_decode($existing_gk_str, true) ?: [];
        $gk_config_arr['app_secret'] = $existing_gk['app_secret'] ?? base64_encode(random_bytes(32));
    } else {
        $gk_config_arr['app_secret'] = base64_encode(random_bytes(32));
    }

    $gk_config_json = json_encode($gk_config_arr);

    if ($custom_slug_post) {
        $check_sql = $wpdb->prepare("SELECT id FROM $at WHERE custom_slug = %s AND id != %d", $custom_slug_post, $eid);
        if ($wpdb->get_var($check_sql)) {
            $custom_slug_post = $custom_slug_post . '-' . wp_generate_password(4, false);
        }
    }

    if ($name && $payload_post) {
        $name_check_sql = $wpdb->prepare("SELECT id FROM $at WHERE app_name = %s AND user_id = %d AND id != %d", $name, $uid, $eid);
        if ($wpdb->get_var($name_check_sql)) {
            cl_insert_history($uid, 'Gagal menyimpan. Nama aplikasi sudah digunakan di workspace Anda.', 'error');
            echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Nama aplikasi sudah digunakan di workspace Anda. Silakan cari nama lain!', 'error'));</script>";
            if ($eid > 0) {
                $erow = $wpdb->get_row($wpdb->prepare("SELECT * FROM $at WHERE id=%d AND user_id=%d", $eid, $uid));
            }
        } else {
            $db_error = false;
            if ($eid > 0) {
                // Determine format
                $updated = $wpdb->update($at, [
                    'app_name' => $name,
                    'description' => $desc,
                    'canvas_link' => $canvas_link_post,
                    'payload' => $payload_post,
                    'gk_config' => $gk_config_json,
                    'custom_slug' => $custom_slug_post
                ], ['id' => $eid, 'user_id' => $uid],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%d', '%d']);
                
                if ($updated === false) {
                    $db_error = true;
                    cl_insert_history($uid, 'Gagal merubah data aplikasi di database. Cek script payload Anda.', 'error');
                    echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Gagal merubah data di database! Cek script payload Anda.', 'error'));</script>";
                } else {
                    cl_insert_history($uid, "Aplikasi '{$name}' berhasil diperbarui.", 'success');
                }
            } else {
                $inserted = $wpdb->insert($at, [
                    'user_id' => $uid,
                    'app_name' => $name,
                    'description' => $desc,
                    'canvas_link' => $canvas_link_post,
                    'login_script' => '',
                    'payload' => $payload_post,
                    'gk_config' => $gk_config_json,
                    'custom_slug' => $custom_slug_post,
                    'created_at' => current_time('mysql')
                ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
                
                if (!$inserted) {
                    $db_error = true;
                    cl_insert_history($uid, 'Gagal menyimpan aplikasi baru ke database. Cek script payload.', 'error');
                    echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Gagal menyimpan ke database! Cek script payload yang Anda masukkan.', 'error'));</script>";
                } else {
                    $eid = $wpdb->insert_id;
                    cl_insert_history($uid, "Aplikasi baru '{$name}' berhasil dibuat.", 'success');
                }
            }

            if ($eid > 0 && !$db_error) {
                $erow = $wpdb->get_row($wpdb->prepare("SELECT * FROM $at WHERE id=%d AND user_id=%d", $eid, $uid));

                $app_id = $eid;
                $title = $name;
                $tagline = $desc;
                $placeholder = $vis_placeholder_post ?: 'Ketik Lisensi Cth: CL-XXXX';
                $btn_text = $vis_btn_text_post ?: 'Buka Kunci';
                $bg_color = $vis_bg_color_post ?: '#003888';
                $btn_color = $vis_btn_color_post ?: '#ff6600';
                $no_lic_txt = $vis_no_license_text_post ?: 'Belum punya lisensi?';
                $buy_txt = $vis_buy_text_post ?: 'Beli di Sini';
                
                $custom_lynk_embed = '';
                if (!empty($lynk_id_url_post)) {
                    $custom_lynk_embed = '
                    <div style="margin-top:0px;text-align:center;">
                        <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:0 0 10px;">'.esc_html($no_lic_txt).'</p>
                        <a href="'.esc_url($lynk_id_url_post).'" target="_blank" style="display:inline-block;background:rgba(255,255,255,0.15);color:#ffffff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;border:1px solid rgba(255,255,255,0.25);transition:all 0.2s;" onmouseover="this.style.background=\'rgba(255,255,255,0.25)\'" onmouseout="this.style.background=\'rgba(255,255,255,0.15)\'">'.esc_html($buy_txt).'</a>
                    </div>';
                }
                
                $logo_html = '';
                if ($logo_saved) {
                    $logo_html = '<img src="'.esc_url($logo_saved).'" style="max-width:100%;max-height:80px;border-radius:8px;margin:0 auto 20px;display:block;" alt="Logo">';
                } else {
                    $logo_html = '<div style="width:64px;height:64px;background:'.$bg_color.';border-radius:16px;margin:0 auto 20px;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);"><svg style="width:32px;height:32px;color:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></div>';
                }
                
                $payload_str = $payload_post ?: ' ';

                 $compiled_login_script = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <!-- Gembok Script Styles -->
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, sans-serif; }
        .cl-hidden { display: none !important; }
        .cl-modal-overlay { position: fixed; inset: 0; display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 999994; background-color: rgba(0,0,0,0.6); gap: 16px; }
        .cl-modal-box { background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); max-width: 400px; width: 90%; max-height: 90vh; overflow: auto; padding: 32px 24px; text-align: center; }
        @keyframes cl-spin { to { transform: rotate(360deg); } }
        .cl-spinner { animation: cl-spin 1s linear infinite; }
        #cl-app { width: 100%; min-height: 100vh; background-color: transparent; }
        #license-input { box-sizing: border-box; width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; outline: none; text-align: center; font-family: monospace; letter-spacing: 1px; transition: border-color 0.15s, box-shadow 0.15s; }
        #license-input:focus { border-color: {$bg_color} !important; box-shadow: 0 0 0 4px rgba(0,0,0,0.05) !important; }
        #cl-btn-validate { box-sizing: border-box; width: 100%; padding: 14px 16px; border-radius: 8px; font-weight: 700; color: white; border: none; cursor: pointer; background-color: {$btn_color}; transition: opacity 0.2s; font-size: 16px; }
        #cl-btn-validate:hover { opacity: 0.9; }
        #cl-btn-close-msg { margin-top: 24px; box-sizing: border-box; width: 100%; padding: 12px 16px; border-radius: 8px; font-weight: 600; color: white; border: none; cursor: pointer; background-color: {$btn_color}; transition: opacity 0.2s; font-size: 16px; }
        #cl-btn-close-msg:hover { opacity: 0.9; }
    </style>
    <script>
    (function(){
        var pid = "{$app_id}";
        var savedDec = null;
        try {
            if (window.name && window.name.indexOf('{') === 0) {
                var cache = JSON.parse(window.name);
                if (cache && cache['cl_dec_key_' + pid]) {
                    savedDec = cache['cl_dec_key_' + pid];
                }
            }
        } catch(e){}
        if (!savedDec) {
            try {
                savedDec = localStorage.getItem('cl_dec_key_' + pid) || sessionStorage.getItem('cl_dec_key_' + pid);
            } catch(e){}
        }
        if (!savedDec) {
            try {
                var state = history.state;
                if (state && typeof state === 'object' && state['cl_dec_key_' + pid]) {
                    savedDec = state['cl_dec_key_' + pid];
                }
            } catch(e){}
        }
        if (!savedDec) {
            try {
                var params = new URLSearchParams(window.location.search || window.location.hash.replace('#', '?'));
                savedDec = params.get('cl_dec') || params.get('cl_dec_key');
            } catch(e){}
        }
        if (savedDec) {
            var css = '#licenseModal, #license-loader, #messageModal { display: none !important; }';
            var head = document.head || document.getElementsByTagName('head')[0];
            var style = document.createElement('style');
            style.id = 'cl-fast-hide';
            style.type = 'text/css';
            style.appendChild(document.createTextNode(css));
            head.appendChild(style);
        }
    })();
    </script>
</head>
<body style="background-color: #f1f5f9;">

    <!-- Loader -->
    <div id="license-loader" class="cl-modal-overlay cl-hidden">
        <div class="cl-modal-box">
            <svg class="cl-spinner" style="width:48px;height:48px;margin:0 auto 16px;color:{$bg_color};" fill="none" viewBox="0 0 24 24">
                <circle style="opacity: 0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path style="opacity: 0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p style="color: #374151; font-size: 1.125rem; font-weight: 600; margin: 0;">Memverifikasi Lisensi...</p>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="licenseModal" class="cl-modal-overlay" style="background-color:{$bg_color};">
        <div class="cl-modal-box col-box">
            <div style="margin-bottom:28px;">
                {$logo_html}
                <h3 style="font-size:1.25rem; font-weight:700; color:#111827; margin:0 0 8px;">{$title}</h3>
                <p style="color:#6b7280; font-size:0.875rem; margin:0;">{$tagline}</p>
            </div>
            
            <div id="msg-error" class="cl-hidden" style="background:#fef2f2;color:#b91c1c;padding:12px 14px;border-radius:12px;margin-bottom:20px;font-size:13px;font-weight:500;border:1px solid #fecaca;"></div>
            
            <div style="margin-bottom:20px;">
                <input type="text" id="license-input" placeholder="{$placeholder}">
            </div>
            <button type="button" id="cl-btn-validate">
                {$btn_text}
            </button>
        </div>
        {$custom_lynk_embed}
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="cl-modal-overlay cl-hidden">
        <div class="cl-modal-box">
            <div id="msg-icon" style="margin: 0 auto 16px; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center;"></div>
            <h3 id="msg-title" style="font-size: 1.125rem; font-weight: 700; color: #111827; margin:0 0 8px;"></h3>
            <p id="msg-text" style="color: #6b7280; font-size: 0.875rem; margin:0;"></p>
            <button type="button" id="cl-btn-close-msg">Tutup</button>
        </div>
    </div>

    <!-- App Container - HIDDEN by default, populated on success -->
    <div id="cl-app" class="cl-hidden"></div>

    <!-- LOGIKA JAVASCRIPT -->
    <script>
    const geminiApiKey = ""; // Tangkap API Key dari environment Gemini Canvas otomatis (Gemini akan replace param kosong ini jadi key asli).
    (function() {
        var PROJECT_ID = "{$app_id}";
        var API_URL = "{$api_url}";
        if (API_URL.indexOf('http://') === 0 && window.location.protocol === 'https:') {
            API_URL = API_URL.replace('http://', 'https://');
        }
        var BRAND_COLOR = "{$bg_color}";
        var isValidating = false;
        var appInjected = false;

        function $(id) { return document.getElementById(id); }
        function show(id) { var el = $(id); if(el) el.classList.remove('cl-hidden'); }
        function hide(id) { var el = $(id); if(el) el.classList.add('cl-hidden'); }

        function getFingerprint() {
            var w = Math.max(screen.width, screen.height);
            var h = Math.min(screen.width, screen.height);
            var c = [navigator.userAgent, navigator.language, w, h, new Date().getTimezoneOffset()];
            return btoa(c.join('|')).slice(0, 32);
        }

        function showMessage(title, text, success) {
            $('msg-title').textContent = title;
            $('msg-text').innerHTML = text;
            $('msg-icon').innerHTML = success 
                ? '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="' + BRAND_COLOR + '" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path></svg>'
                : '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="m15 9-6 6"></path><path d="m9 9 6 6"></path></svg>';
            $('msg-icon').style.backgroundColor = success ? BRAND_COLOR + '20' : '#fee2e2';
            hide('licenseModal');
            hide('license-loader');
            show('messageModal');
        }

        var _store = {};
        function storeSet(key, val) {
            try { localStorage.setItem(key, val); } catch(e) {}
            try { sessionStorage.setItem(key, val); } catch(e) {}
            try {
                var cache = {};
                if (window.name && window.name.indexOf('{') === 0) {
                    cache = JSON.parse(window.name);
                }
                cache[key] = val;
                window.name = JSON.stringify(cache);
            } catch(ex) {}
            try {
                var state = history.state || {};
                if (typeof state !== 'object') state = {};
                state[key] = val;
                history.replaceState(state, document.title);
            } catch(ex) {}
            _store[key] = val;
        }
        function storeGet(key) {
            try {
                if (window.name && window.name.indexOf('{') === 0) {
                    var cache = JSON.parse(window.name);
                    if (cache && cache[key]) return cache[key];
                }
            } catch(e) {}
            try {
                var state = history.state;
                if (state && typeof state === 'object' && state[key]) {
                    return state[key];
                }
            } catch(ex) {}
            try { var v = localStorage.getItem(key); if(v) return v; } catch(e) {}
            try { var v = sessionStorage.getItem(key); if(v) return v; } catch(e) {}
            return _store[key] || null;
        }
        function storeRemove(key) {
            try { localStorage.removeItem(key); } catch(e) {}
            try { sessionStorage.removeItem(key); } catch(e) {}
            try {
                if (window.name && window.name.indexOf('{') === 0) {
                    var cache = JSON.parse(window.name);
                    if (cache) {
                        delete cache[key];
                        window.name = JSON.stringify(cache);
                    }
                }
            } catch(ex) {}
            try {
                var state = history.state;
                if (state && typeof state === 'object' && state[key]) {
                    delete state[key];
                    history.replaceState(state, document.title);
                }
            } catch(ex) {}
            delete _store[key];
        }

        function removeFastHide() {
            try {
                var el = document.getElementById('cl-fast-hide');
                if (el && el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            } catch(e){}
        }

        async function rn(p) {
            document.documentElement.style.opacity = '0';
            document.body.style = '';
            document.body.className = '';
            
            var hideIds = ['licenseModal', 'license-loader', 'messageModal'];
            for(var i=0; i<hideIds.length; i++) {
                if($(hideIds[i])) $(hideIds[i]).style.display = 'none';
            }

            var decodedHtml;
            try {
                var pTrim = p.trim();
                var b64Regex = new RegExp('^[a-zA-Z0-9\\\\+\\\\/\\\\=\\\\s]+$');
                if (b64Regex.test(pTrim)) {
                    decodedHtml = decodeURIComponent(atob(pTrim));
                } else {
                    decodedHtml = p;
                }
            } catch(e) { decodedHtml = p; }

            const parser = new DOMParser();
            const doc = parser.parseFromString(decodedHtml, 'text/html');

            const headElements = Array.from(doc.head.childNodes);
            const bodyHtml = doc.body.innerHTML;
            const scripts = Array.from(doc.querySelectorAll('script'));

            document.head.innerHTML = '';
            document.body.innerHTML = '';

            headElements.forEach(el => {
                if(el.tagName !== 'SCRIPT') document.head.appendChild(el.cloneNode(true));
            });

            document.body.innerHTML = bodyHtml;

            for(const old of scripts) {
                await new Promise(res => {
                    const s = document.createElement('script');
                    Array.from(old.attributes).forEach(a => s.setAttribute(a.name, a.value));
                    s.text = old.innerHTML;
                    if(s.src) {
                        s.onload = res;
                        s.onerror = res;
                    }
                    if (typeof geminiApiKey !== 'undefined' && geminiApiKey && geminiApiKey.trim() !== '' && !s.src) {
                        s.text = s.text.replace(/const\s+apiKey\s*=\s*(['"]).*?\1\s*;/gi, 'const apiKey = "' + geminiApiKey + '";');
                    }
                    document.body.appendChild(s);
                    if(!s.src) res(); 
                });
            }

            window['__cl_injected_' + PROJECT_ID] = true;

            if (typeof window.onload === 'function') window.onload(new Event('load'));
            window.dispatchEvent(new Event('DOMContentLoaded'));
            document.dispatchEvent(new Event('DOMContentLoaded'));
            window.dispatchEvent(new Event('load'));

            // If Babel standalone was injected, we might need to manually trigger it
            if (typeof Babel !== 'undefined' && Babel.transformScriptTags) {
                Babel.transformScriptTags();
            }

            setTimeout(() => {
                document.documentElement.style.transition = 'opacity 0.3s ease-in-out';
                document.documentElement.style.opacity = '1';
            }, 50);
        }

        function validate(autoKey = null) {
            if(isValidating) return;
            if(window['__cl_injected_' + PROJECT_ID]) return;
            var input = $('license-input');
            var k = autoKey || (input ? input.value.trim() : '');
            if(!k) {
                if(!autoKey && input) input.focus();
                return;
            }
            
            isValidating = true;
            hide('licenseModal'); 
            show('license-loader');
            hide('msg-error');
            
            var fp = storeGet('cl_dev_' + PROJECT_ID) || getFingerprint();
            storeSet('cl_dev_' + PROJECT_ID, fp);
            
            fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ license: k, app_id: PROJECT_ID, fingerprint: fp })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                isValidating = false;
                if(data.valid && data.payload) {
                    storeSet('cl_key_' + PROJECT_ID, k);
                    storeSet('cl_dec_key_' + PROJECT_ID, data.payload);
                    try {
                        document.cookie = 'cl_key_' + PROJECT_ID + '=' + encodeURIComponent(k) + '; path=/; max-age=31536000';
                    } catch(ex){}

                    setTimeout(() => {
                        rn(data.payload);
                    }, 500);
                } else {
                    hide('license-loader');
                    removeFastHide();
                    storeRemove('cl_key_' + PROJECT_ID);
                    storeRemove('cl_dec_key_' + PROJECT_ID);
                    try {
                        document.cookie = 'cl_key_' + PROJECT_ID + '=; path=/; max-age=0';
                    } catch(ex){}
                    if(!autoKey) {
                        var err = $('msg-error');
                        if (err) { err.innerText = data.message || 'Lisensi tidak valid'; show('msg-error'); }
                        show('licenseModal');
                    } else {
                        show('licenseModal');
                    }
                }
            })
            .catch(function(e) {
                isValidating = false;
                hide('license-loader');
                removeFastHide();
                if(!autoKey) {
                    var err = $('msg-error');
                    if (err) { err.innerText = 'Gagal terhubung ke server'; show('msg-error'); }
                }
                show('licenseModal');
            });
        }

        function closeMessage() {
            hide('messageModal');
            show('licenseModal');
        }

        window.CanvasLock = {
            validate: validate,
            closeMessage: closeMessage
        };

        function initLock() {
            if(window['__cl_injected_' + PROJECT_ID]) return;
            var input = $('license-input');
            if(input) {
                input.addEventListener('keypress', function(e) { 
                    if(e.key === 'Enter') {
                        e.preventDefault();
                        validate(); 
                    }
                });
            }
            
            var btnValidate = $('cl-btn-validate');
            if (btnValidate) {
                btnValidate.addEventListener('click', function(e) {
                    e.preventDefault();
                    validate();
                });
            }

            var btnCloseMsg = $('cl-btn-close-msg');
            if (btnCloseMsg) {
                btnCloseMsg.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeMessage();
                });
            }
            
            var decryptedKey = storeGet('cl_dec_key_' + PROJECT_ID);
            if (decryptedKey) {
                rn(decryptedKey);
                return;
            }
            
            var saved = storeGet('cl_key_' + PROJECT_ID);
            if (!saved) {
                try {
                    var match = document.cookie.match('(^|;) ?cl_key_' + PROJECT_ID + '=([^;]*)(;|$)');
                    if (match) saved = decodeURIComponent(match[2]);
                } catch(e){}
            }
            
            if(saved) validate(saved);
            else if(input) input.focus();
        }

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            initLock();
        } else {
            document.addEventListener('DOMContentLoaded', initLock);
        }
    })();
    </script>
</body>
</html>
HTML;
                $wpdb->update($at, ['login_script' => $compiled_login_script], ['id' => $eid, 'user_id' => $uid], ['%s'], ['%d', '%d']);
                
                $redirect_url = admin_url('admin.php?page=canvaslock&view=apps&edit=' . $eid . '&saved=1');
                echo "<script>window.location.replace('" . esc_url_raw($redirect_url) . "');</script>";
                exit;
            }

            $_GET['edit'] = $eid;
            $erow = $wpdb->get_row($wpdb->prepare("SELECT * FROM $at WHERE id=%d AND user_id=%d", $eid, $uid));
            
            // Refresh local PHP variables
            if ($erow) {
                $app_name = $erow->app_name;
                $description = $erow->description;
                $canvas_link = $erow->canvas_link;
                $custom_slug = $erow->custom_slug;
                $payload = $erow->payload;
                $gk = json_decode($erow->gk_config, true) ?: [];
                $vis_placeholder = $gk['vis_placeholder'] ?? '';
                $vis_btn_text = $gk['vis_btn_text'] ?? '';
                $vis_bg_color = $gk['vis_bg_color'] ?? '#003888';
                $vis_btn_color = $gk['vis_btn_color'] ?? '#ff6600';
                $vis_no_license_text = $gk['vis_no_license_text'] ?? '';
                $vis_buy_text = $gk['vis_buy_text'] ?? '';
                $lynk_id_url = $gk['lynk_id_url'] ?? '';
                $logo_url = $gk['vis_logo'] ?? '';
                $webhook_default_limit = $gk['webhook_default_limit'] ?? 100;
                $webhook_default_expired = $gk['webhook_default_expired'] ?? 0;
            }
        }
    } else {
        cl_insert_history($uid, 'Gagal menyimpan. Nama Aplikasi dan Full Script Gembok wajib diisi!', 'error');
        echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Nama Aplikasi dan Full Script wajib diisi!', 'error'));</script>";
    }
}

// Decode payload for display in textarea (Base64 Payload Vault)
$display_payload = $payload;
if (!empty($display_payload) && preg_match('/^[a-zA-Z0-9\+\/\=\n\r]+$/', $display_payload)) {
    $decoded = base64_decode(trim($display_payload), true);
    if ($decoded !== false) {
        $urldecoded = urldecode($decoded);
        if ($urldecoded !== '') {
            $display_payload = $urldecoded;
        }
    }
}

$has_generated = ($erow && !empty($erow->login_script) && isset($_GET['saved']));
?>

<!-- Master Two Column Layout -->
<form method="post" enctype="multipart/form-data" class="m-0 p-0" id="cl-app-form">
    <?php wp_nonce_field('cl_act', '_clnonce'); ?>
    <input type="hidden" name="edit_id" value="<?= $eid ?>">
    <input type="hidden" name="existing_logo_url" id="existing_logo_url" value="<?= esc_attr($logo_url) ?>">

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
        
        <!-- KOLOM KIRI: Card Aplikasi & Card Login Gate -->
        <div class="space-y-6">
            
            <!-- Card Aplikasi -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-slate-800 flex items-center">
                        <i data-lucide="layout" class="w-5 h-5 mr-3 text-brand"></i>
                        Aplikasi Gemini Canvas
                    </h2>
                    <?php if ($erow): ?>
                        <a href="<?= admin_url('admin.php?page=canvaslock&view=apps') ?>" class="text-xs font-bold text-slate-500 hover:text-brand bg-white px-3 py-1.5 rounded-lg border border-slate-200 transition-colors shadow-sm">
                            Buat Baru
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="p-6 space-y-4">
                    <div>
                        <div class="mb-3 border-l-2 border-amber-500 bg-amber-50 p-3 rounded-r-lg">
                            <p class="text-xs text-amber-800 font-medium">
                                <i data-lucide="alert-triangle" class="inline w-4 h-4 mr-1"></i>
                                Penting: Pastikan <span class="font-bold">Nama Aplikasi</span> yang Anda daftarkan di sini sama persis dengan nama produk yang ada di Lynk.id atau Mayar.id agar integrasi pengiriman lisensi otomatis via webhook dapat berjalan dengan lancar saat ada pembeli.
                            </p>
                        </div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Nama Aplikasi <span class="text-red-500">*</span></label>
                        <input type="text" name="app_name" id="app_name" value="<?= esc_attr($app_name) ?>" placeholder="Cth: AI Analitik Pro" required
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand bg-slate-50 focus:bg-white text-slate-800 font-medium">
                        <p class="text-[11px] text-slate-400 mt-1">Otomatis menjadi Judul login script.</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Deskripsi <span class="text-red-500">*</span></label>
                        <input type="text" name="description" id="description" value="<?= esc_attr($description) ?>" placeholder="Cth: Masukkan lisensi premium" required
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand bg-slate-50 focus:bg-white text-slate-800 font-medium">
                        <p class="text-[11px] text-slate-400 mt-1">Otomatis menjadi Tagline Login Script.</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Full Script (html,css,js) <span class="text-red-500">*</span></label>
                        <textarea name="payload" id="payload" rows="5" required placeholder="Paste kode HTML Single-File Anda di sini..."
                            class="w-full border border-slate-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand bg-slate-900 text-slate-200 font-mono leading-relaxed resize-y min-h-[120px] lg:min-h-[250px]"><?= esc_textarea($display_payload) ?></textarea>
                        <p class="text-[11px] text-slate-400 mt-1">Ini hanya HTML Canvas / App saja, JANGAN include login script.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6 pt-4 border-t border-slate-100">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Default Webhook Limit Device</label>
                            <input type="number" name="webhook_default_limit" min="1" max="999" value="<?= esc_attr($webhook_default_limit) ?>" 
                                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 bg-slate-50 focus:bg-white text-slate-800">
                            <p class="text-[10px] text-slate-400 mt-1">Sbg default saat auto-generate lisensi via webhook. (1-999)</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Default Webhook Expired</label>
                            <input type="number" name="webhook_default_expired" min="0" max="9999" value="<?= esc_attr($webhook_default_expired) ?>" placeholder="0 (Lifetime)"
                                class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 bg-slate-50 focus:bg-white text-slate-800">
                            <p class="text-[10px] text-slate-400 mt-1">Masa aktif lisensi (hari). Kosongkan / nol utk Lifetime.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Login Gate -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 bg-slate-50">
                    <h2 class="text-lg font-bold text-slate-800 flex items-center">
                        <i data-lucide="shield" class="w-5 h-5 mr-3 text-accent"></i>
                        Login Gate
                    </h2>
                </div>
                
                <div class="p-6 space-y-4">
                    <!-- Form 3: Upload Logo -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5 flex justify-between">
                            <span>Upload Logo Login (Max 1MB)</span>
                            <?php if ($logo_url): ?>
                                <span class="text-xs text-brand font-semibold flex items-center gap-1"><i data-lucide="check" class="w-3.5 h-3.5"></i> Logo Terupload</span>
                            <?php endif; ?>
                        </label>
                        <input type="file" name="vis_logo_file" id="vis_logo_file" accept="image/png, image/jpeg, image/gif, image/svg+xml"
                            class="w-full text-xs text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:font-bold file:bg-brand/10 file:text-brand hover:file:bg-brand/20 cursor-pointer">
                    </div>

                    <!-- Form 4: Placeholder Teks -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Placeholder Teks</label>
                        <input type="text" name="vis_placeholder" id="vis_placeholder" value="<?= esc_attr($vis_placeholder) ?>" placeholder="Cth: Ketik Lisensi Cth: CL-XXXX"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand bg-slate-50 focus:bg-white text-slate-800">
                    </div>

                    <!-- Form 5: Button Teks -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Button Teks</label>
                        <input type="text" name="vis_btn_text" id="vis_btn_text" value="<?= esc_attr($vis_btn_text) ?>" placeholder="Cth: Buka Kunci"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand bg-slate-50 focus:bg-white text-slate-800">
                    </div>

                    <!-- Row: Warna BG & Warna Button (Forms 6 & 7) -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Warna BG</label>
                            <input type="color" name="vis_bg_color" id="vis_bg_color" value="<?= esc_attr($vis_bg_color) ?>"
                                class="w-full h-11 border border-slate-200 rounded-xl p-1 bg-white cursor-pointer shadow-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-1">Warna Button</label>
                            <input type="color" name="vis_btn_color" id="vis_btn_color" value="<?= esc_attr($vis_btn_color) ?>"
                                class="w-full h-11 border border-slate-200 rounded-xl p-1 bg-white cursor-pointer shadow-sm">
                        </div>
                    </div>

                    <!-- Form 8: Teks Upsell -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Teks Upsell</label>
                        <input type="text" name="vis_no_license_text" id="vis_no_license_text" value="<?= esc_attr($vis_no_license_text) ?>" placeholder="Cth: Belum punya lisensi?"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand bg-slate-50 focus:bg-white text-slate-800">
                    </div>

                    <!-- Form 9: Text button Upsell -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Teks Button Upsell</label>
                        <input type="text" name="vis_buy_text" id="vis_buy_text" value="<?= esc_attr($vis_buy_text) ?>" placeholder="Cth: Beli di Sini"
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand bg-slate-50 focus:bg-white text-slate-800">
                    </div>

                    <!-- Form 10: Link produk / landing page -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Link Produk / Landing Page</label>
                        <input type="url" name="lynk_id_url" id="lynk_id_url" value="<?= esc_attr($lynk_id_url) ?>" placeholder="https://..."
                            class="w-full border border-slate-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand bg-slate-50 focus:bg-white text-slate-800">
                    </div>
                </div>
            </div>

            <!-- Unified Submit Button -->
            <div class="flex gap-4">
                <button type="submit" name="cl_save_app" value="1" id="cl_save_btn"
                    class="w-full bg-slate-800 hover:bg-slate-900 border border-slate-800 text-white font-bold py-3.5 px-6 rounded-2xl shadow-sm transition-all duration-150 flex items-center justify-center">
                    <i data-lucide="save" class="w-5 h-5 mr-2"></i> Simpan & Generate App
                </button>
            </div>

        </div>

        <!-- KOLOM KANAN: Live Preview & Hasil Generated Script -->
        <div class="space-y-6 lg:sticky lg:top-8">
            
            <!-- Card Live Preview -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-800 flex items-center">
                        <i data-lucide="eye" class="w-5 h-5 mr-3 text-brand"></i>
                        Live Preview (Real-Time Sandbox)
                    </h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-150 text-green-700 bg-emerald-50 border border-emerald-200 animate-pulse">Dinamis</span>
                </div>
                <!-- Box Screen Sandbox Wrapper -->
                <div class="w-full bg-slate-100 relative h-[420px] shadow-inner overflow-hidden border-t border-slate-100">
                    <iframe id="cl_preview_frame" class="w-full h-full border-none"></iframe>
                </div>
            </div>

            <!-- Card Hasil Generated Script (Shows only after script has been compiled/generated) -->
            <?php if ($has_generated): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 bg-slate-50 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <h2 class="text-base font-bold text-slate-800 flex items-center">
                        <i data-lucide="code" class="w-5 h-5 mr-3 text-brand"></i>
                        Hasil Generated Script Gembok
                    </h2>
                    
                    <button type="button" onclick="copyCompiledScript()" class="w-full sm:w-auto px-4 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-bold transition-all border border-indigo-200 flex items-center justify-center shadow-sm whitespace-nowrap">
                        <i data-lucide="copy" class="w-3.5 h-3.5 mr-1.5 shrink-0"></i> Copy Script
                    </button>
                </div>
                
                <div class="w-full bg-slate-950 p-5 h-40 lg:h-[280px]">
                    <textarea id="compiled_script_output" readonly class="w-full h-full bg-transparent border-none text-xs font-mono text-slate-300 focus:outline-none focus:ring-0 resize-none leading-relaxed" spellcheck="false"
                    ><?= esc_textarea($erow->login_script) ?></textarea>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</form>

<script>
document.getElementById('cl-app-form').addEventListener('submit', function(e) {
    if (!this.dataset.b64done) {
        var pField = document.getElementById('payload');
        if (pField && pField.value) {
            try {
                // Determine if it already looks like a pure base64 (e.g. from an edit error state)
                if (!/^[a-zA-Z0-9\+\/\=\n\r]+$/.test(pField.value.trim())) {
                    pField.value = btoa(encodeURIComponent(pField.value));
                }
            } catch(ex) {
                console.error("Base64 encode failed", ex);
            }
        }
        this.dataset.b64done = 'true';
    }
});

window.hasCopiedScript = false;
window.liveLogoUrl = "<?= esc_js($logo_url) ?>";

function checkSlugLive() {
    const slugInput = document.getElementById('custom_slug');
    const msgEl = document.getElementById('slug_status_msg');
    const slug = slugInput.value.trim();
    if(!slug) {
        showToast('Tulis slug yang ingin dicek.', 'error');
        return;
    }
    
    msgEl.innerText = "Mengecek ketersediaan slug...";
    msgEl.className = "text-[11px] text-slate-500 mt-1";
    
    const formData = new FormData();
    formData.append('action', 'cl_check_slug');
    formData.append('slug', slug);
    <?php if($eid > 0): ?>
    formData.append('id', '<?= $eid ?>');
    <?php endif; ?>
    
    fetch('<?= admin_url('admin-ajax.php') ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.available) {
            msgEl.innerText = "✓ Slug tersedia dan bisa digunakan.";
            msgEl.className = "text-[11px] text-emerald-600 font-semibold mt-1";
            showToast('Slug tersedia!');
        } else {
            msgEl.innerText = "✗ Slug sudah digunakan atau tidak valid.";
            msgEl.className = "text-[11px] text-rose-500 font-semibold mt-1";
            showToast('Slug sudah digunakan.', 'error');
        }
    })
    .catch(e => {
        msgEl.innerText = "Gagal memverifikasi slug.";
        msgEl.className = "text-[11px] text-rose-500 mt-1";
    });
}

function copyCompiledScript() {
    const txt = document.getElementById('compiled_script_output');
    if (!txt || !txt.value) {
        showToast('Script belum di-generate atau element tidak ditemukan.', 'error');
        return;
    }
    
    const textToCopy = txt.value;
    
    const successAction = () => {
        showToast('Login gembok script berhasil disalin ke clipboard!');
        window.hasCopiedScript = true;
        const saveBtn = document.getElementById('cl_save_btn');
        if (saveBtn) {
            saveBtn.className = "flex-1 bg-accent hover:bg-accentHover border border-accent text-white font-bold py-3.5 px-6 rounded-2xl shadow-sm transition-all flex items-center justify-center";
            saveBtn.disabled = false;
        }
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(textToCopy).then(() => {
            successAction();
        }).catch((err) => {
            console.error('Clipboard API failed', err);
            fallbackCopy(txt, successAction);
        });
    } else {
        fallbackCopy(txt, successAction);
    }
}

function fallbackCopy(txtElement, onSuccess) {
    try {
        const textArea = document.createElement('textarea');
        textArea.value = txtElement.value;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        textArea.style.top = '0';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        
        const successful = document.execCommand('copy');
        document.body.removeChild(textArea);
        
        if (successful) {
            onSuccess();
        } else {
            showToast('Browser memblokir aksi copy. Silakan copy manual secara langsung.', 'error');
        }
    } catch (err) {
        console.error('Fallback execCommand failed', err);
        showToast('Browser memblokir aksi copy. Silakan copy manual secara langsung.', 'error');
    }
}

function renderLivePreviewSandbox() {
    const titleVal = document.getElementById('app_name').value || 'Akses Aplikasi';
    const descVal = document.getElementById('description').value || 'Masukkan kunci lisensi Anda';
    const placeholderVal = document.getElementById('vis_placeholder').value || 'Ketik Lisensi Cth: CL-XXXX';
    const btnTextVal = document.getElementById('vis_btn_text').value || 'Buka Kunci';
    const bgColorVal = document.getElementById('vis_bg_color').value || '#003888';
    const btnColorVal = document.getElementById('vis_btn_color').value || '#ff6600';
    const upsellTextVal = document.getElementById('vis_no_license_text').value || 'Belum punya lisensi?';
    const upsellBtnTextVal = document.getElementById('vis_buy_text').value || 'Beli di Sini';
    const productUrlVal = document.getElementById('lynk_id_url').value || '';
    
    let logoHtml = '';
    if (window.liveLogoUrl) {
        logoHtml = `<img src="${window.liveLogoUrl}" style="max-width:100%;max-height:80px;border-radius:8px;margin:0 auto 20px;display:block;" alt="Logo">`;
    } else {
        logoHtml = `<div style="width:64px;height:64px;background:${bgColorVal};border-radius:16px;margin:0 auto 20px;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1);"><svg style="width:32px;height:32px;color:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg></div>`;
    }
    
    let upsellHtml = '';
    if (productUrlVal) {
        upsellHtml = `
        <div style="margin-top:24px;text-align:center;">
            <p style="color:rgba(255,255,255,0.8);font-size:13px;margin:0 0 10px;">${upsellTextVal}</p>
            <a href="${productUrlVal}" target="_blank" style="display:inline-block;background:rgba(255,255,255,0.15);color:#ffffff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;border:1px solid rgba(255,255,255,0.25);transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.15)'">${upsellBtnTextVal}</a>
        </div>`;
    }
    
    const htmlFrame = `
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; padding: 24px; font-family: system-ui, -apple-system, sans-serif; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 100vh; background-color: ${bgColorVal}; overflow-x: hidden; }
            .cl-modal-box { background: white; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); max-width: 400px; width: 100%; padding: 32px 24px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="cl-modal-box">
            <div style="margin-bottom:28px;">
                ${logoHtml}
                <h3 style="font-size:1.25rem; font-weight:700; color:#111827; margin:0 0 8px;">${titleVal}</h3>
                <p style="color:#6b7280; font-size:0.875rem; margin:0;">${descVal}</p>
            </div>
            <div style="margin-bottom:20px;">
                <input type="text" placeholder="${placeholderVal}" readonly style="box-sizing:border-box; width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; text-align: center; outline: none; letter-spacing: 1px;">
            </div>
            <button type="button" onclick="parent.showToast('Visual Preview: Membuka Kunci Sandbox!')" style="box-sizing:border-box; width: 100%; padding: 14px 16px; border-radius: 8px; font-weight: 700; color: white; border: none; background-color: ${btnColorVal}; cursor: pointer; transition: opacity 0.2s; font-size: 16px;">
                ${btnTextVal}
            </button>
        </div>
        ${upsellHtml}
    </body>
    </html>`;
    
    const frame = document.getElementById('cl_preview_frame');
    if (frame) {
        frame.srcdoc = htmlFrame;
    }
}

// Attach instant state change listener on all customization fields
['app_name', 'description', 'vis_placeholder', 'vis_btn_text', 'vis_bg_color', 'vis_btn_color', 'vis_no_license_text', 'vis_buy_text', 'lynk_id_url'].forEach(id => {
    const element = document.getElementById(id);
    if(element) {
        element.addEventListener('input', renderLivePreviewSandbox);
        element.addEventListener('change', renderLivePreviewSandbox);
    }
});

// Attach logo file drag reader preview
document.getElementById('vis_logo_file').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(evt) {
            window.liveLogoUrl = evt.target.result;
            renderLivePreviewSandbox();
        };
        reader.readAsDataURL(file);
    }
});

document.addEventListener('DOMContentLoaded', () => {
    renderLivePreviewSandbox();
    
    const saveBtn = document.getElementById('cl_save_btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function(e) {
            const hasGenerated = <?= $has_generated ? 'true' : 'false' ?>;
            if (hasGenerated && !window.hasCopiedScript) {
                e.preventDefault();
                showToast('Silakan klik button Copy Script terlebih dahulu sebelum menyimpan!', 'error');
            }
        });
    }
});
</script>
