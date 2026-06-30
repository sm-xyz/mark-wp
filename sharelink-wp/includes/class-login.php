<?php
if (!defined('ABSPATH')) exit;

add_action('login_init', 'cl_fix_wplogin_warnings', 1);
function cl_fix_wplogin_warnings() {
    global $user_login, $error;
    if (!isset($user_login)) {
        $user_login = isset($_POST['log']) ? wp_unslash($_POST['log']) : '';
    }
    if (!isset($error)) {
        $error = '';
    }
}

add_action('login_enqueue_scripts', 'cl_custom_login_ui');
function cl_custom_login_ui() {
    $site_icon = get_site_icon_url(150);
    $logo_url = empty($site_icon) ? 'https://sm.free.nf/wp-content/uploads/2026/06/smxyz-logo-500px.png' : $site_icon;
    ?>
    <style>
        body.login { 
            background-color: #003888 !important; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif !important;
        }
        #login { width: 100%; max-width: 340px; padding: 0 16px; text-align: center; }
        #login h1 a { display: none; }
        #login h1::before {
            content: "";
            display: inline-block;
            width: 72px;
            height: 72px;
            background-image: url('<?= esc_url($logo_url) ?>');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            margin-bottom: 5px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        #login h1::after { 
            display: none !important;
        }
        #login form { 
            background: #ffffff; 
            border: none; 
            border-radius: 16px; 
            padding: 28px; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); 
            margin-top: 0;
            text-align: left;
        }
        #login form label { 
            color: #334155; 
            font-size: 13px; 
            font-weight: 600; 
            margin-bottom: 6px;
            display: block;
        }
        #login form input[type="text"], 
        #login form input[type="password"] { 
            width: 100% !important; 
            border: 2px solid #e2e8f0 !important; 
            background: #f8fafc !important; 
            color: #0f172a !important; 
            border-radius: 8px !important; 
            padding: 10px 12px !important; 
            margin-top: 4px !important; 
            font-size: 14px !important; 
            box-shadow: none !important;
            transition: all 0.2s !important;
        }
        #login form input[type="text"]:focus, 
        #login form input[type="password"]:focus {
            border-color: #003888 !important;
            outline: none !important;
            background: white !important;
        }
        #login form input[type="submit"] { 
            width: 100% !important; 
            background: #ff6600 !important; 
            border: none !important; 
            color: white !important; 
            border-radius: 8px !important; 
            padding: 10px !important; 
            font-size: 14px !important; 
            font-weight: 700 !important; 
            cursor: pointer !important; 
            transition: all 0.2s !important; 
            margin-top: 20px !important; 
            text-shadow: none !important;
        }
        #login form input[type="submit"]:hover { 
            background: #e65c00 !important; 
            transform: translateY(-1px) !important;
            box-shadow: 0 10px 15px -3px rgba(255,102,0,0.3) !important;
        }
        .login #backtoblog, .login #nav { text-align: center; margin-top: 24px; }
        .login .forgetmenot { display: flex !important; align-items: center !important; float: left !important; margin-top: 16px !important; margin-bottom: 0 !important; }
        .login .forgetmenot label { display: flex !important; align-items: center !important; gap: 8px !important; float: none !important; margin: 0 !important; line-height: 1 !important; height: auto !important; width: auto !important; }
        .login .forgetmenot input[type="checkbox"] { float: none !important; margin: 0 10px 0 0 !important; padding: 0 !important; width: 16px !important; height: 16px !important; flex-shrink: 0 !important; vertical-align: middle !important; }
        .login #backtoblog a, .login #nav a { color: rgba(255,255,255,0.8) !important; text-decoration: none; font-size: 14px; font-weight: 500; }
        .login #backtoblog a:hover, .login #nav a:hover { color: white !important; text-decoration: underline; }
        .login .message, .login .notice, .login #login_error {
            border-radius: 10px;
            background: white;
            border-left: 4px solid #ef4444;
            color: #334155;
            padding: 12px 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .login .message { border-left-color: #003888; }
        
        /* Lost Password specifics */
        body.login-action-lostpassword #login form {
            padding-bottom: 24px;
        }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var h1 = document.querySelector('#login h1');
        if (h1) {
            var div = document.createElement('div');
            div.innerHTML = 'Share<span style="color: #fbbf24;">Link</span> AI';
            div.style.fontSize = '26px';
            div.style.fontWeight = '800';
            div.style.color = 'white';
            div.style.textAlign = 'center';
            div.style.marginBottom = '20px';
            div.style.letterSpacing = '-0.5px';
            h1.appendChild(div);
        }
    });
    </script>
    <?php
}

// Turnstile Integration for Login, Lost Password, & Reset Password
add_action('login_form', 'cl_add_turnstile_form');
add_action('lostpassword_form', 'cl_add_turnstile_form');
add_action('resetpass_form', 'cl_add_turnstile_form');
function cl_add_turnstile_form() {
    $site_key = get_option('cl_turnstile_sitekey', '');
    if (!empty($site_key)) {
        echo '<div style="margin-top:20px; margin-bottom: 20px; display:flex; justify-content:center; min-height: 65px;"><div class="cf-turnstile" data-size="flexible" data-theme="auto" data-sitekey="' . esc_attr($site_key) . '"></div></div>';
        echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
}

add_filter('authenticate', 'cl_verify_turnstile_on_login', 20, 3);
function cl_verify_turnstile_on_login($user, $username, $password) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log'])) {
        $secret = get_option('cl_turnstile_secret', '');
        $site_key = get_option('cl_turnstile_sitekey', '');
        
        if (!empty($secret) && !empty($site_key)) {
            $response = isset($_POST['cf-turnstile-response']) ? sanitize_text_field($_POST['cf-turnstile-response']) : '';
            if (empty($response)) {
                return new WP_Error('turnstile_error', '<strong>Error</strong>: Harap verifikasi CAPTCHA terlebih dahulu.');
            }
            
            $verify_resp = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                'body' => array(
                    'secret'   => $secret,
                    'response' => $response,
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                )
            ));
            
            if (is_wp_error($verify_resp)) {
                return new WP_Error('turnstile_error', '<strong>Error</strong>: Gagal memvalidasi CAPTCHA.');
            }
            
            $verify_body = wp_remote_retrieve_body($verify_resp);
            $verify_json = json_decode($verify_body);
            
            if (!$verify_json->success) {
                return new WP_Error('turnstile_error', '<strong>Error</strong>: CAPTCHA tidak valid.');
            }
        }
    }
    return $user;
}

add_action('lostpassword_post', 'cl_verify_turnstile_on_lost_password');
function cl_verify_turnstile_on_lost_password($errors) {
    if (isset($_POST['wp-submit'])) {
        $secret = get_option('cl_turnstile_secret', '');
        $site_key = get_option('cl_turnstile_sitekey', '');
        
        if (!empty($secret) && !empty($site_key)) {
            $response = isset($_POST['cf-turnstile-response']) ? sanitize_text_field($_POST['cf-turnstile-response']) : '';
            if (empty($response)) {
                $errors->add('turnstile_error', '<strong>Error</strong>: Harap verifikasi CAPTCHA terlebih dahulu.');
                return;
            }
            
            $verify_resp = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                'body' => array(
                    'secret'   => $secret,
                    'response' => $response,
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                )
            ));
            
            if (is_wp_error($verify_resp)) {
                $errors->add('turnstile_error', '<strong>Error</strong>: Gagal memvalidasi CAPTCHA.');
                return;
            }
            
            $verify_body = wp_remote_retrieve_body($verify_resp);
            $verify_json = json_decode($verify_body);
            
            if (!$verify_json->success) {
                $errors->add('turnstile_error', '<strong>Error</strong>: CAPTCHA tidak valid.');
            }
        }
    }
}

add_action('validate_password_reset', 'cl_verify_turnstile_on_reset_password', 10, 2);
function cl_verify_turnstile_on_reset_password($errors, $user) {
    if (isset($_POST['pass1']) && isset($_POST['pass2'])) {
        $secret = get_option('cl_turnstile_secret', '');
        $site_key = get_option('cl_turnstile_sitekey', '');
        
        if (!empty($secret) && !empty($site_key)) {
            $response = isset($_POST['cf-turnstile-response']) ? sanitize_text_field($_POST['cf-turnstile-response']) : '';
            if (empty($response)) {
                $errors->add('turnstile_error', '<strong>Error</strong>: Harap verifikasi CAPTCHA terlebih dahulu.');
                return;
            }
            
            $verify_resp = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
                'body' => array(
                    'secret'   => $secret,
                    'response' => $response,
                    'remoteip' => $_SERVER['REMOTE_ADDR']
                )
            ));
            
            if (is_wp_error($verify_resp)) {
                $errors->add('turnstile_error', '<strong>Error</strong>: Gagal memvalidasi CAPTCHA.');
                return;
            }
            
            $verify_body = wp_remote_retrieve_body($verify_resp);
            $verify_json = json_decode($verify_body);
            
            if (!$verify_json->success) {
                $errors->add('turnstile_error', '<strong>Error</strong>: CAPTCHA tidak valid.');
            }
        }
    }
}
