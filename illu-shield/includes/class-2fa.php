<?php
if (!defined('ABSPATH')) exit;

class Illu_Shield_2FA {
    
    public function __construct() {
        $settings = get_option('illu_shield_settings', []);
        
        if (($settings['enable_2fa'] ?? 'yes') === 'yes') {
            // Profile hooks
            add_action('illu_shield_profile_section', [$this, 'render_profile_section']);
            add_action('show_user_profile', [$this, 'render_wp_admin_profile']);
            add_action('edit_user_profile', [$this, 'render_wp_admin_profile']);
            add_action('personal_options_update', [$this, 'handle_wp_admin_profile_update']);
            add_action('edit_user_profile_update', [$this, 'handle_wp_admin_profile_update']);
            
            add_action('init', [$this, 'handle_profile_actions']);
            add_action('init', [$this, 'handle_magic_link']);
            add_action('admin_init', [$this, 'enforce_admin_2fa']);
            
            // Login hooks
            add_filter('authenticate', [$this, 'intercept_login'], 99, 3);
            add_action('login_form_illu_2fa', [$this, 'render_2fa_login_form']);
            add_action('login_enqueue_scripts', [$this, 'login_styles']);
        }
    }

    public function handle_magic_link() {
        if (isset($_GET['illu_magic_login'])) {
            $token = sanitize_text_field($_GET['illu_magic_login']);
            $uid = get_transient('illu_magic_' . $token);
            if ($uid) {
                delete_transient('illu_magic_' . $token);
                $user = get_user_by('id', $uid);
                if ($user) {
                    wp_set_auth_cookie($uid, false);
                    update_user_meta($uid, 'illu_last_activity', time());
                    do_action('wp_login', $user->user_login, $user);
                    
                    // Mark 2FA as passed via magic link
                    $trust_token = wp_generate_password(64, false);
                    setcookie('illu_2fa_trust_' . $uid, $trust_token, [
                        'expires'  => time() + (30 * DAY_IN_SECONDS),
                        'path'     => COOKIEPATH ?: '/',
                        'domain'   => COOKIE_DOMAIN,
                        'secure'   => is_ssl(),
                        'httponly' => true,
                        'samesite' => 'Strict',
                    ]);
                    $trusted_devices = get_user_meta($uid, 'illu_2fa_trusted_devices', true) ?: [];
                    $trusted_devices[] = wp_hash($trust_token);
                    update_user_meta($uid, 'illu_2fa_trusted_devices', array_slice($trusted_devices, -5));

                    wp_safe_redirect(admin_url());
                    exit;
                }
            }
            wp_die('Magic link tidak valid atau sudah kedaluwarsa.');
        }
    }

    public function enforce_admin_2fa() {
        if (defined('DOING_AJAX') && DOING_AJAX) return;
        
        $settings = get_option('illu_shield_settings', []);
        $require_admin_2fa = ($settings['require_2fa_admin'] ?? 'yes') === 'yes';
        
        if ($require_admin_2fa && current_user_can('administrator')) {
            $user_id = get_current_user_id();
            $is_enabled = get_user_meta($user_id, 'illu_2fa_enabled', true) === 'yes';
            
            // Only allow access to profile.php, admin-ajax.php, and admin-post.php
            $current_screen = function_exists('get_current_screen') ? get_current_screen() : null;
            $is_profile_page = defined('IS_PROFILE_PAGE') && IS_PROFILE_PAGE;
            
            global $pagenow;
            $allowed_pages = ['profile.php', 'admin-ajax.php', 'admin-post.php'];
            
            if (!$is_enabled && !in_array($pagenow, $allowed_pages)) {
                wp_safe_redirect(admin_url('profile.php?illu_2fa_required=1'));
                exit;
            }
        }
    }

    public function render_wp_admin_profile($user) {
        $is_enabled = get_user_meta($user->ID, 'illu_2fa_enabled', true) === 'yes';
        ?>
        <h2>Illu Shield - 2FA Security</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label>Two-Factor Authentication</label></th>
                <td>
                    <?php if ($is_enabled): ?>
                        <?php 
                        $new_codes = get_transient('illu_2fa_new_recovery_codes_' . $user->ID);
                        if ($new_codes): 
                        ?>
                            <div style="background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; margin-bottom: 15px; border-radius: 4px;">
                                <strong>Please save these Recovery Codes now!</strong><br>
                                They will not be shown again. Use them to log in if you lose access to your device.<br><br>
                                <code><?php echo implode('</code> <code>', array_map('esc_html', $new_codes)); ?></code>
                            </div>
                        <?php endif; ?>
                        <p style="color: green; font-weight: bold;">✓ 2FA is currently ENABLED for this account.</p>
                        <p><label><input type="checkbox" name="illu_disable_2fa" value="1" id="illu_disable_2fa_checkbox"> Check this box to disable 2FA.</label></p>
                        <div id="illu_disable_2fa_wrapper" style="display:none; margin-top:10px; padding:10px; background:#fff; border:1px solid #ccc; border-radius:4px;">
                            <p><strong>Verify to Disable:</strong></p>
                            <input type="text" name="illu_disable_totp_code" pattern="\d{6}" maxlength="6" placeholder="000000" class="regular-text" style="letter-spacing: 2px; font-family: monospace;">
                            <p class="description">Enter the 6-digit code from your app to confirm disabling 2FA.</p>
                        </div>
                        <script>
                            document.getElementById('illu_disable_2fa_checkbox').addEventListener('change', function() {
                                document.getElementById('illu_disable_2fa_wrapper').style.display = this.checked ? 'block' : 'none';
                            });
                        </script>
                        <?php wp_nonce_field('illu_2fa_wpadmin_action', '_illu_2fa_wpadmin_nonce'); ?>
                    <?php else: ?>
                        <?php
                        $secret = Illu_Shield_TOTP::generate_secret();
                        $temp_key = 'illu_2fa_setup_' . $user->ID;
                        set_transient($temp_key, $secret, 15 * MINUTE_IN_SECONDS);

                        $issuer = urlencode(get_bloginfo('name'));
                        $account = urlencode($user->user_email);
                        $otpauth_url = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}";
                        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth_url);
                        ?>
                        <div style="background: #fff; border: 1px solid #ccc; padding: 15px; display: inline-block; border-radius: 4px;">
                            <p><strong>1. Scan this QR Code with your Authenticator app:</strong></p>
                            <img src="<?php echo esc_url($qr_url); ?>" alt="QR Code" style="margin-bottom: 10px;">
                            <p>Or enter this secret key manually:<br>
                                <code style="filter: blur(4px); cursor: pointer; display: inline-block; user-select: none;" onclick="this.style.filter='none'; this.style.userSelect='auto';" title="Klik untuk tampilkan"><?php echo esc_html(implode(' ', str_split($secret, 4))); ?></code>
                                <br><small>Klik pada area blur di atas untuk menampilkan kode rahasia</small>
                            </p>
                            
                            <hr style="margin: 15px 0;">
                            
                            <p><strong>2. Verify with code to enable:</strong></p>
                            <input type="text" name="illu_totp_code" pattern="\d{6}" maxlength="6" placeholder="000000" class="regular-text" style="letter-spacing: 2px; font-family: monospace;">
                            <p class="description">Enter the 6-digit code from your app and update profile to enable.</p>
                        </div>
                        <?php wp_nonce_field('illu_2fa_wpadmin_action', '_illu_2fa_wpadmin_nonce'); ?>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function handle_wp_admin_profile_update($user_id) {
        if (!current_user_can('edit_user', $user_id)) return false;
        
        $is_enabled = get_user_meta($user_id, 'illu_2fa_enabled', true) === 'yes';

        if ($is_enabled && isset($_POST['illu_disable_2fa']) && $_POST['illu_disable_2fa'] === '1' && isset($_POST['_illu_2fa_wpadmin_nonce']) && wp_verify_nonce($_POST['_illu_2fa_wpadmin_nonce'], 'illu_2fa_wpadmin_action')) {
            $disable_code = sanitize_text_field($_POST['illu_disable_totp_code'] ?? '');
            $secret = get_user_meta($user_id, 'illu_2fa_secret', true);
            
            if (Illu_Shield_TOTP::verify_totp($secret, $disable_code)) {
                delete_user_meta($user_id, 'illu_2fa_secret');
                delete_user_meta($user_id, 'illu_2fa_enabled');
                delete_user_meta($user_id, 'illu_2fa_recovery_codes');
            } else {
                add_action('user_profile_update_errors', function($errors) {
                    $errors->add('illu_2fa_required', '<strong>Error:</strong> Invalid 2FA code. Cannot disable 2FA.');
                });
            }
        } elseif (!$is_enabled && !empty($_POST['illu_totp_code']) && isset($_POST['_illu_2fa_wpadmin_nonce']) && wp_verify_nonce($_POST['_illu_2fa_wpadmin_nonce'], 'illu_2fa_wpadmin_action')) {
            
            $code = sanitize_text_field($_POST['illu_totp_code']);
            $secret = get_transient('illu_2fa_setup_' . $user_id);
            
            if ($secret && Illu_Shield_TOTP::verify_totp($secret, $code)) {
                update_user_meta($user_id, 'illu_2fa_secret', $secret);
                update_user_meta($user_id, 'illu_2fa_enabled', 'yes');
                delete_transient('illu_2fa_setup_' . $user_id);
                
                $recovery_codes = Illu_Shield_TOTP::generate_recovery_codes();
                if (!function_exists('wp_hash_password')) {
                    require_once ABSPATH . WPINC . '/pluggable.php';
                }
                $hashed_codes = array_map('wp_hash_password', $recovery_codes);
                update_user_meta($user_id, 'illu_2fa_recovery_codes', $hashed_codes);
                set_transient('illu_2fa_new_recovery_codes_' . $user_id, $recovery_codes, 5 * MINUTE_IN_SECONDS);
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Invalid 2FA Code. Settings not saved.</p></div>';
                });
            }
        }
    }

    public function handle_profile_actions() {
        if (!is_user_logged_in() || !isset($_POST['_illu_2fa_nonce'])) return;
        if (!wp_verify_nonce($_POST['_illu_2fa_nonce'], 'illu_2fa_action')) return;

        $uid = get_current_user_id();

        if (isset($_POST['enable_2fa'])) {
            $code = sanitize_text_field($_POST['totp_code']);
            $secret = get_transient('illu_2fa_setup_' . $uid);
            
            if ($secret && Illu_Shield_TOTP::verify_totp($secret, $code)) {
                update_user_meta($uid, 'illu_2fa_secret', $secret);
                update_user_meta($uid, 'illu_2fa_enabled', 'yes');
                delete_transient('illu_2fa_setup_' . $uid);
                
                // Generate and store recovery codes
                $recovery_codes = Illu_Shield_TOTP::generate_recovery_codes();
                if (!function_exists('wp_hash_password')) {
                    require_once ABSPATH . WPINC . '/pluggable.php';
                }
                $hashed_codes = array_map('wp_hash_password', $recovery_codes);
                update_user_meta($uid, 'illu_2fa_recovery_codes', $hashed_codes);
                set_transient('illu_2fa_new_recovery_codes_' . $uid, $recovery_codes, 5 * MINUTE_IN_SECONDS);
                
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>2FA Berhasil diaktifkan. Harap simpan Recovery Codes Anda di bawah ini!</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Kode TOTP tidak valid atau sesi habis. Silakan muat ulang halaman dan coba lagi.</p></div>';
                });
            }
        } elseif (isset($_POST['disable_2fa'])) {
            $disable_code = sanitize_text_field($_POST['totp_disable_code'] ?? '');
            $current_secret = get_user_meta($uid, 'illu_2fa_secret', true);
            
            if (Illu_Shield_TOTP::verify_totp($current_secret, $disable_code)) {
                delete_user_meta($uid, 'illu_2fa_secret');
                delete_user_meta($uid, 'illu_2fa_enabled');
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>2FA Berhasil dinonaktifkan.</p></div>';
                });
            } else {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-error"><p>Kode TOTP salah. Gagal menonaktifkan 2FA.</p></div>';
                });
            }
        }
    }

    public function render_profile_section($uid) {
        $is_enabled = get_user_meta($uid, 'illu_2fa_enabled', true) === 'yes';
        $user = get_userdata($uid);
        
        echo '<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">';
        echo '<div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50">';
        echo '<div><h3 class="font-bold text-slate-800 flex items-center"><i data-lucide="shield-check" class="w-5 h-5 mr-2 text-green-600"></i> Two-Factor Authentication (2FA)</h3>';
        echo '<p class="text-[13px] text-slate-500 mt-1">Lindungi akun Anda dengan kode otentikasi dari aplikasi seperti Google Authenticator atau Authy.</p></div>';
        
        if ($is_enabled) {
            echo '<span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-semibold rounded-full">Aktif</span>';
        } else {
            echo '<span class="px-3 py-1 bg-slate-200 text-slate-600 text-xs font-semibold rounded-full">Nonaktif</span>';
        }
        echo '</div>'; // close header

        echo '<div class="p-6">';

        if (isset($_GET['illu_2fa_required'])) {
            echo '<div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">';
            echo '<h4 class="font-bold text-red-800 mb-2">Setup 2FA Required</h4>';
            echo '<p class="text-sm text-red-700">Sebagai Administrator, Anda diwajibkan untuk mengaktifkan Two-Factor Authentication (2FA) sebelum dapat mengakses halaman admin lainnya.</p>';
            echo '</div>';
        }

        if ($is_enabled) {
            $new_codes = get_transient('illu_2fa_new_recovery_codes_' . $uid);
            if ($new_codes) {
                echo '<div class="mb-6 p-4 bg-yellow-50 border border-yellow-200 rounded-xl">';
                echo '<h4 class="font-bold text-yellow-800 mb-2">Simpan Recovery Codes Ini Sekarang!</h4>';
                echo '<p class="text-sm text-yellow-700 mb-3">Ini adalah satu-satunya saat kode ini ditampilkan. Jika Anda kehilangan akses ke HP Anda, gunakan kode ini untuk login.</p>';
                echo '<div class="grid grid-cols-2 gap-2">';
                foreach ($new_codes as $rc) {
                    echo '<code class="bg-white px-3 py-2 rounded text-slate-700 font-mono text-center border border-yellow-100">' . esc_html($rc) . '</code>';
                }
                echo '</div></div>';
            }

            echo '<form method="post">';
            wp_nonce_field('illu_2fa_action', '_illu_2fa_nonce');
            echo '<p class="text-sm text-slate-600 mb-4">Two-Factor Authentication saat ini sedang aktif untuk akun Anda.</p>';
            
            // Wajibkan memasukkan kode TOTP atau password sebelum disable 2FA
            echo '<label class="block text-sm font-semibold text-slate-700 mb-1.5">Masukkan Kode TOTP untuk menonaktifkan:</label>';
            echo '<input type="text" name="totp_disable_code" required maxlength="6" pattern="\d{6}" placeholder="000000" class="w-full tracking-widest text-center text-xl font-mono border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all max-w-[200px] mb-4">';
            echo '<br>';

            echo '<button type="submit" name="disable_2fa" class="px-4 py-2 bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 rounded-lg text-sm font-semibold transition-all">Nonaktifkan 2FA</button>';
            echo '</form>';
        } else {
            $secret = Illu_Shield_TOTP::generate_secret();
            $temp_key = 'illu_2fa_setup_' . $uid;
            set_transient($temp_key, $secret, 15 * MINUTE_IN_SECONDS);

            $issuer = urlencode(get_bloginfo('name'));
            $account = urlencode($user->user_email);
            $otpauth_url = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}";
            // Fallback lightweight QR API
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth_url);

            echo '<form method="post" class="space-y-6">';
            wp_nonce_field('illu_2fa_action', '_illu_2fa_nonce');
            
            echo '<div class="flex flex-col md:flex-row gap-6">';
            echo '<div><img src="'.esc_url($qr_url).'" class="w-32 h-32 border border-slate-200 rounded-lg p-2" alt="QR Code"></div>';
            echo '<div class="flex-1 space-y-2">';
            echo '<p class="text-sm text-slate-600">1. Scan QR Code di samping menggunakan aplikasi Google Authenticator, Authy, atau sejenisnya.</p>';
            echo '<p class="text-sm text-slate-600">Atau masukkan kode rahasia ini secara manual: <br>
                <code style="filter: blur(4px); cursor: pointer; display: inline-block; user-select: none;" onclick="this.style.filter=\'none\'; this.style.userSelect=\'auto\';" title="Klik untuk tampilkan" class="bg-slate-100 px-2 py-1 rounded text-slate-700 font-mono tracking-widest mt-2">'.implode(' ', str_split($secret, 4)).'</code>
                <br><small class="text-slate-400">Klik pada area blur di atas untuk menampilkan kode rahasia</small>
            </p>';
            echo '</div></div>';

            echo '<div>';
            echo '<label class="block text-sm font-semibold text-slate-700 mb-1.5">2. Masukkan Kode Verifikasi (6 digit)</label>';
            echo '<input type="text" name="totp_code" required maxlength="6" pattern="\d{6}" placeholder="000000" class="w-full tracking-widest text-center text-xl font-mono border border-slate-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand transition-all max-w-[200px]">';
            echo '</div>';

            echo '<button type="submit" name="enable_2fa" class="bg-brand hover:bg-brand/90 text-white font-semibold py-2.5 px-6 rounded-lg shadow-sm transition-all text-sm">Validasi & Aktifkan</button>';
            
            echo '</form>';
        }

        echo '</div></div>';
    }

    public function intercept_login($user, $username, $password) {
        if (is_wp_error($user) || empty($user->ID)) {
            return $user; // Invalid login, let WP handle
        }

        $settings = get_option('illu_shield_settings', []);
        $require_admin_2fa = ($settings['require_2fa_admin'] ?? 'yes') === 'yes';

        $is_enabled = get_user_meta($user->ID, 'illu_2fa_enabled', true) === 'yes';
        if (!$is_enabled) {
            if ($require_admin_2fa && in_array('administrator', (array)$user->roles)) {
                // Set auth cookie temporarily so they can access profile
                wp_set_auth_cookie($user->ID, isset($_POST['remember']));
                update_user_meta($user->ID, 'illu_last_activity', time());
                do_action('wp_login', $user->user_login, $user);
                wp_safe_redirect(admin_url('profile.php?illu_2fa_required=1'));
                exit;
            }
            return $user; // Proceed normally
        }

        // FITUR-09: Check trusted device
        $trusted_cookie = $_COOKIE['illu_2fa_trust_' . $user->ID] ?? '';
        $trusted_devices = get_user_meta($user->ID, 'illu_2fa_trusted_devices', true) ?: [];
        if (!empty($trusted_cookie) && in_array(wp_hash($trusted_cookie), $trusted_devices)) {
            return $user; // Trusted device, bypass 2FA
        }

        // 2FA is active. Set a transient with user ID to hold the half-login state
        $token = wp_generate_password(32, false);
        set_transient('illu_2fa_token_' . $token, $user->ID, 5 * MINUTE_IN_SECONDS);
        
        // Redirect to 2FA page
        $url = site_url('wp-login.php?action=illu_2fa&token=' . $token);
        
        // If an explicit redirect_to was set, append it
        if (!empty($_REQUEST['redirect_to'])) {
            $url = add_query_arg('redirect_to', urlencode($_REQUEST['redirect_to']), $url);
        }

        wp_redirect($url);
        exit;
    }

    public function login_styles() {
        if (isset($_GET['action']) && $_GET['action'] === 'illu_2fa') {
            echo '<style>
                .illu-2fa-container { text-align: center; }
                .illu-2fa-input { letter-spacing: 0.5em; font-size: 24px!important; text-align: center; font-family: monospace; padding: 12px!important; font-weight: bold!important; }
                .login h1 a { display: none; }
                .illu-padlock { width: 48px; height: 48px; fill: none; stroke: currentColor; stroke-width: 2; margin: 0 auto 16px; color: #0d2870; }
            </style>';
        }
    }

    public function render_2fa_login_form() {
        $token = sanitize_text_field($_GET['token'] ?? '');
        if (empty($token)) {
            wp_safe_redirect(site_url('wp-login.php'));
            exit;
        }

        $uid = get_transient('illu_2fa_token_' . $token);
        if (!$uid) {
            // Token expired or invalid
            wp_redirect(site_url('wp-login.php?illu_2fa=expired'));
            exit;
        }

        $error = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // F4-03: Send Magic Link
            if (isset($_POST['send_magic_link'])) {
                // FIX-02: Magic Link Rate Limit
                $magic_rate_key = 'illu_magic_rate_' . $uid;
                if (get_transient($magic_rate_key)) {
                    $magic_sent_error = true;
                } else {
                    set_transient($magic_rate_key, true, 60); // 1 request per menit per user
                    
                    $magic_token = wp_generate_password(64, false);
                    set_transient('illu_magic_' . $magic_token, $uid, 10 * MINUTE_IN_SECONDS);
                    
                    $user_info = get_userdata($uid);
                    $magic_url = add_query_arg(['illu_magic_login' => $magic_token], wp_login_url());
                    wp_mail($user_info->user_email, '[Illu Shield] Magic Login Link', "Seseorang meminta Magic Link untuk login ke akun Anda. Jika ini Anda, gunakan tautan berikut (Berlaku 10 menit):\n\n" . $magic_url);
                    
                    $magic_sent = true;
                }
            } elseif (isset($_POST['illu_totp_code'])) {
                $attempt_key = 'illu_2fa_attempts_' . $token;
            $attempts = intval(get_transient($attempt_key) ?: 0);
            
            if ($attempts >= 5) {
                delete_transient('illu_2fa_token_' . $token); // Invalidate token
                delete_transient($attempt_key);
                wp_redirect(site_url('wp-login.php?illu_2fa=brute_force'));
                exit;
            }
            
            set_transient($attempt_key, $attempts + 1, 5 * MINUTE_IN_SECONDS);

            $code = sanitize_text_field($_POST['illu_totp_code']);
            $secret = get_user_meta($uid, 'illu_2fa_secret', true);
            $recovery_codes = get_user_meta($uid, 'illu_2fa_recovery_codes', true) ?: [];
            
            $is_valid = false;
            
            if (strlen($code) === 6 && Illu_Shield_TOTP::verify_totp($secret, $code)) {
                $is_valid = true;
            } elseif (strlen($code) > 6) {
                // Check recovery code
                $input_code = strtoupper(trim($code));
                if (!function_exists('wp_check_password')) {
                    require_once ABSPATH . WPINC . '/pluggable.php';
                }
                foreach ($recovery_codes as $key => $hashed_code) {
                    if (wp_check_password($input_code, $hashed_code)) {
                        $is_valid = true;
                        // Remove used recovery code
                        unset($recovery_codes[$key]);
                        update_user_meta($uid, 'illu_2fa_recovery_codes', array_values($recovery_codes));
                        break;
                    }
                }
            }
            
                if ($is_valid) {
                    // Success!
                    delete_transient('illu_2fa_token_' . $token);
                    delete_transient($attempt_key);
                    wp_set_auth_cookie($uid, isset($_POST['remember']));
                    update_user_meta($uid, 'illu_last_activity', time());
                    $user = get_user_by('id', $uid);
                    do_action('wp_login', $user->user_login, $user);
                    
                    // FITUR-09: Save Trusted Device
                    if (isset($_POST['trust_device'])) {
                        $trust_token = wp_generate_password(64, false);
                        setcookie('illu_2fa_trust_' . $uid, $trust_token, [
                            'expires'  => time() + (30 * DAY_IN_SECONDS),
                            'path'     => COOKIEPATH ?: '/',
                            'domain'   => COOKIE_DOMAIN,
                            'secure'   => is_ssl(),
                            'httponly' => true,
                            'samesite' => 'Strict',
                        ]);
                        $trusted_devices = get_user_meta($uid, 'illu_2fa_trusted_devices', true) ?: [];
                        $trusted_devices[] = wp_hash($trust_token);
                        update_user_meta($uid, 'illu_2fa_trusted_devices', array_slice($trusted_devices, -5)); // Keep max 5 devices
                    }
                    
                    $redirect = isset($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : admin_url();
                    wp_safe_redirect($redirect);
                    exit;
                } else {
                    $error = true;
                }
            }
        }

        login_header('Validasi Keamanan 2FA');

        echo '<div class="illu-2fa-container">';
        echo '<svg class="illu-padlock" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>';
        
        if (isset($magic_sent)) {
            echo '<div style="background:#d4edda; color:#155724; padding:15px; margin-bottom:20px; border:1px solid #c3e6cb; border-radius:4px; text-align:center;">Magic link telah dikirim ke email Anda. Silakan cek inbox.</div>';
        } elseif (isset($magic_sent_error)) {
            echo '<div id="login_error" style="background:#f8d7da; color:#721c24; padding:15px; margin-bottom:20px; border:1px solid #f5c6cb; border-radius:4px; text-align:center;">Silakan tunggu sebelum meminta link baru.</div>';
        }
        
        if ($error) {
            echo '<div id="login_error">Kode TOTP tidak valid.</div>';
        }
        
        echo '<form method="post" action="">';
        echo '<p style="margin-bottom:20px; color:#555;">Buka aplikasi Authenticator Anda dan masukkan 6-digit kode verifikasi. Atau gunakan salah satu Recovery Code.</p>';
        echo '<p><input type="text" name="illu_totp_code" class="input illu-2fa-input" autocomplete="one-time-code" autofocus required placeholder="000000 / XXXX-XXXX"></p>';
        
        if (isset($_REQUEST['redirect_to'])) {
            echo '<input type="hidden" name="redirect_to" value="'.esc_attr($_REQUEST['redirect_to']).'">';
        }
        if (isset($_REQUEST['rememberme'])) {
            echo '<input type="hidden" name="remember" value="1">';
        }

        echo '<p class="forgetmenot" style="margin-top:10px;"><label for="trust_device"><input name="trust_device" type="checkbox" id="trust_device" value="1" /> Trust this device for 30 days</label></p>';

        echo '<p class="submit" style="margin-top:20px;">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Verifikasi Login">
              </p>';
        echo '</form>';
        
        echo '<form method="post" action="" style="margin-top:15px; text-align:center;">';
        echo '<button type="submit" name="send_magic_link" value="1" class="button" style="width:100%;">📧 Kirim Magic Link ke Email (Alternatif)</button>';
        echo '</form>';
        
        echo '<p id="nav" style="margin-top:20px;"><a href="'.wp_login_url().'">&larr; Kembali ke halaman Login biasa</a></p>';
        echo '</div>';

        login_footer();
        exit;
    }
}
