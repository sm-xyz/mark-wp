<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_options')) exit;

if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_save_admtxt'])) {
    update_option('cl_wa_tpl_workspace', sanitize_textarea_field(wp_unslash($_POST['cl_wa_tpl_workspace'])));
    update_option('cl_email_tpl_workspace', wp_unslash($_POST['cl_email_tpl_workspace']));
    if (isset($_POST['cl_wa_tpl_license'])) update_option('cl_wa_tpl_license', sanitize_textarea_field(wp_unslash($_POST['cl_wa_tpl_license'])));
    if (isset($_POST['cl_email_tpl_license'])) update_option('cl_email_tpl_license', wp_unslash($_POST['cl_email_tpl_license']));
    update_option('cl_wa_tpl_quota', sanitize_textarea_field(wp_unslash($_POST['cl_wa_tpl_quota'])));
    update_option('cl_em_tpl_quota', wp_unslash($_POST['cl_em_tpl_quota']));
    update_option('cl_wa_tpl_quota_admin', sanitize_textarea_field(wp_unslash($_POST['cl_wa_tpl_quota_admin'] ?? '')));
    update_option('cl_wa_tpl_quota_req', sanitize_textarea_field(wp_unslash($_POST['cl_wa_tpl_quota_req'] ?? '')));
    update_option('cl_wa_tpl_reset', sanitize_textarea_field(wp_unslash($_POST['cl_wa_tpl_reset'])));
    update_option('cl_em_tpl_reset', wp_unslash($_POST['cl_em_tpl_reset']));
    echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Template Admin disimpan.'));</script>";
}

$d_wa_ws = "Halo *{buyer_name}*,

Selamat Datang di Sharelink AI! 🚀
Akses Workspace pribadi Anda telah berhasil kami siapkan. Mulai sekarang Anda dapat membuat dan mengelola lisensi keamanan aplikasi dengan mudah dan aman.

*Penting! Berikut Detail Akses Anda:*
🌐 *URL Login:* {login_url}
📧 *Email:* {buyer_email}
🔑 *Password:* {user_pass}

Segera amankan akun Anda dengan mengubah password setelah login pertama kali. Jaga kerahasiaan data ini.
Bila ada kendala teknis, silakan hubungi tim dukungan kami.

Selamat berkreasi dengan aman! ✨";

$d_em_ws = '<!DOCTYPE html>
<html>
<body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #333333; background-color: #f4f6f8; padding: 20px; font-size: 15px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <h2 style="color: #0d2870; margin-top: 0;">Selamat Datang di Workspace Anda, {buyer_name}!</h2>
        <p>Akses workspace Sharelink AI independen Anda sekarang sudah siap digunakan. Anda dapat segera memakainya untuk memproteksi dan meng-generate lisensi keamanan pada aplikasi Anda secara profesional.</p>
        <p>Berikut adalah detail kredensial sistem Anda:</p>
        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #0d2870;">
            <p style="margin:0 0 12px 0;"><strong>URL Dasbor:</strong> <br><a href="{login_url}" style="color:#0ea5e9; text-decoration:none; font-weight:bold;">{login_url}</a></p>
            <p style="margin:0 0 12px 0;"><strong>Username / Email:</strong> <br>{buyer_email}</p>
            <p style="margin:0;"><strong>Kata Sandi Akses:</strong> <br><span style="font-family: monospace; font-size:16px;">{user_pass}</span></p>
        </div>
        <p><strong>Tips Keamanan:</strong> Kami sangat merekomendasikan Anda untuk segera masuk ke menu Dasbor dan mengganti kata sandi sementara Anda di menu Profil demi menjaga tingkat keamanan maksimal akun Anda.</p>
        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
        <p style="font-size: 13px; color: #64748b; margin-bottom: 0;">Pesan ini dikirimkan otomatis oleh gerbang sistem Sharelink AI. Harap tidak saling membagikan akses kepada pihak yang tidak memiliki otorisasi.</p>
    </div>
</body>
</html>';

$d_wa_lic = "Halo *{buyer_name}*,\nSelamat, pesanan akses untuk aplikasi *{app_name}* Anda telah diaktifkan! 🎉\nBerikut adalah informasi detail untuk mengakses canvas kami dengan aman:\n================================\nKunci Lisensi Pribadi: `{license_key}`\nLink URL Akses     : {link_gemini_CANVAS}\nLink Custom    : {custom_link}\n================================\nCatatan Penting:\nSilahkan kunjungi link akses di atas, lalu masukkan kunci lisensi untuk mulai menggunakan fitur lengkap aplikasi ini. Lisensi ini bersifat eksklusif untuk Anda.\n> Jika ada kendala, jangan ragu menghubungi tim seller *{app_name}* di whatsapp: https://wa.me/{workspace_owner_wa}\n\nTerima kasih dan selamat berkarya! ✨";

$d_em_lic = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Aktivasi Lisensi {app_name}</title>
    <style type="text/css">
        /* Client-specific Styles untuk memastikan tampilan konsisten di semua aplikasi email */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f8fafc; }

        /* CSS Responsif untuk Smartphone */
        @media screen and (max-width: 600px) {
            .wrapper { width: 100% !important; max-width: 100% !important; padding: 0 !important; }
            .container { padding: 32px 20px !important; }
            .button-wrapper { width: 100% !important; text-align: center !important; }
            .button { display: block !important; padding: 14px 20px !important; }
            .credential-col { display: block !important; width: 100% !important; padding-right: 0 !important; padding-bottom: 20px !important; }
            .credential-col-last { display: block !important; width: 100% !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

    <!-- Hidden Preheader Text (Meningkatkan Open Rate secara Alami) -->
    <div style="display: none; max-height: 0px; overflow: hidden; font-size: 1px; line-height: 1px; color: #ffffff; opacity: 0;">
        Terima kasih atas pesanan Anda. Akses resmi untuk aplikasi {app_name} kini telah aktif dan siap digunakan.
    </div>

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; table-layout: fixed;">
        <tr>
            <td align="center" style="padding: 40px 10px;">
                
                <!--[if (gte mso 9)|(IE)]>
                <table align="center" border="0" cellspacing="0" cellpadding="0" width="580">
                <tr>
                <td align="center" valign="top" width="580">
                <![endif]-->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="wrapper" style="max-width: 580px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                    
                    <tr>
                        <td class="container" style="padding: 48px 40px; color: #1e293b;">
                            
                            <!-- Header / Salam Pembuka -->
                            <h2 style="font-size: 20px; font-weight: 700; color: #0d2870; margin-top: 0; margin-bottom: 24px; line-height: 1.4; letter-spacing: -0.01em;">
                                Akses Aplikasi Anda Sudah Siap, {buyer_name}!
                            </h2>

                            <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 16px;">
                                Terima kasih banyak atas kepercayaan serta pesanan Anda. Kami menginformasikan bahwa tim kami telah mengaktifkan akses penuh Anda ke dalam aplikasi <strong>{app_name}</strong>.
                            </p>

                            <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 24px;">
                                Berikut adalah rincian kredensial lisensi serta tautan akses resmi yang dapat langsung Anda gunakan:
                            </p>

                            <!-- Kartu Kredensial Akses Minimalis & Terbaca Jelas -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 24px;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <!-- Custom Link -->
                                                <td class="credential-col" valign="top" width="50%" style="padding-right: 16px;">
                                                    <p style="margin: 0 0 6px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">
                                                        Tautan Kustom Anda
                                                    </p>
                                                    <p style="margin: 0; font-size: 14px; color: #0d2870; font-weight: 600; word-break: break-all;">
                                                        <a href="{custom_link}" target="_blank" style="color: #0d2870; text-decoration: underline;">
                                                            {custom_link}
                                                        </a>
                                                    </p>
                                                </td>
                                                <!-- License Key -->
                                                <td class="credential-col-last" valign="top" width="50%">
                                                    <p style="margin: 0 0 8px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 600;">
                                                        Kode Lisensi Eksklusif
                                                    </p>
                                                    <table border="0" cellpadding="0" cellspacing="0">
                                                        <tr>
                                                            <td style="background-color: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px 10px;">
                                                                <code style="font-family: Menlo, Monaco, Consolas, \'Courier New\', monospace; font-size: 14px; font-weight: bold; color: #0f172a;">
                                                                    {license_key}
                                                                </code>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Tombol CTA Bulletproof (Tautan Utama ke Canvas Gemini) -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 32px;">
                                <tr>
                                    <td align="left">
                                        <table border="0" cellspacing="0" cellpadding="0" class="button-wrapper">
                                            <tr>
                                                <td align="center" bgcolor="#0d2870" style="border-radius: 4px;">
                                                    <a href="{link_gemini_CANVAS}" target="_blank" class="button" style="font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; color: #ffffff; text-decoration: none; border-radius: 4px; padding: 12px 28px; border: 1px solid #0d2870; display: inline-block; font-weight: 600; letter-spacing: 0.02em;">
                                                        Akses Aplikasi Anda &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Petunjuk Penggunaan -->
                            <p style="font-size: 14.5px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 24px;">
                                Silakan gunakan <strong>Kode Lisensi Eksklusif</strong> di atas ketika sistem memintanya pada halaman login awal aplikasi Anda. Demi kenyamanan Anda, akses ini bersifat aman dan dibatasi khusus untuk penggunaan personal.
                            </p>

                            <!-- Edukasi Keamanan dengan Nuansa Tenang -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 16px 20px; font-size: 13.5px; line-height: 1.5; color: #166534;">
                                        <strong style="color: #14532d;">Rekomendasi Keamanan:</strong> Harap jaga kerahasiaan Kode Lisensi Anda dan hindari membagikannya ke publik. Sistem kami melakukan monitoring otomatis guna mencegah penyalahgunaan lisensi oleh pihak yang tidak bertanggung jawab.
                                    </td>
                                </tr>
                            </table>

                            <!-- Kontak Bantuan / WhatsApp (Ditampilkan Sangat Rapi & Profesional) -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 32px;">
                                <tr>
                                    <td style="font-size: 14.5px; line-height: 1.6; color: #475569;">
                                        Jika Anda memiliki pertanyaan teknis, jangan ragu untuk menghubungi tim seller resmi <strong>{app_name}</strong> melalui WhatsApp di: 
                                        <a href="https://wa.me/{workspace_owner_wa}" target="_blank" style="color: #0d2870; text-decoration: underline; font-weight: 600;">
                                            wa.me/{workspace_owner_wa}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Separator -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td height="1" style="background-color: #e2e8f0; line-height: 1px; font-size: 1px;">&nbsp;</td>
                                </tr>
                            </table>

                            <!-- Informasi Sistem & Ketentuan Transaksional -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 24px;">
                                <tr>
                                    <td>
                                        <p style="font-size: 13.5px; line-height: 1.6; color: #64748b; margin: 0 0 12px 0;">
                                            Email ini dikirimkan secara otomatis oleh sistem administrasi platform untuk memvalidasi pembelian lisensi {app_name} Anda. Kami sangat menyarankan Anda menyimpan pesan transaksional ini sebagai bukti kepemilikan lisensi yang sah.
                                        </p>
                                        <p style="font-size: 13.5px; line-height: 1.6; color: #64748b; margin: 0;">
                                            Privasi dan keamanan data transaksi Anda sepenuhnya dilindungi secara ketat di bawah kepatuhan kebijakan layanan resmi kami.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Bagian Kaki Email (Footer) -->
                    <tr>
                        <td style="padding: 32px 40px; background-color: #fafafa; border-top: 1px solid #f1f5f9; color: #64748b;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="font-size: 13.5px; line-height: 1.6;">
                                        <strong style="color: #334155; font-size: 15px;">ShareLink AI</strong><br />
                                        Pati, Jawa Tengah, Indonesia<br />
                                        <a href="https://sharelink.web.id" style="color: #0d2870; text-decoration: none; font-weight: 600;">https://sharelink.web.id</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 24px; font-size: 13px; line-height: 1.6; color: #94a3b8;">
                                        Apabila Anda ingin membatasi atau menghentikan penerimaan email komunikasi non-transaksional dari sistem kami, silakan akses halaman <a href="{unsubscribe_url}" style="color: #64748b; text-decoration: underline; font-weight: normal;">berhenti berlangganan</a>.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
                <!--[if (gte mso 9)|(IE)]>
                </td>
                </tr>
                </table>
                <![endif]-->

            </td>
        </tr>
    </table>

</body>
</html>';

$d_wa_quo = "Halo *{buyer_name}*,

Pemberitahuan Sistem 🔔
Top-Up kuota lisensi aplikasi Anda telah disetujui dan ditambahkan. 

*Rincian Penambahan:*
➕ Penambahan: {kuota_tambahan} Lisensi
📊 Total Kuota Lisensi Saat Ini: {total_kuota} Lisensi

Silakan login kembali ke dalam Workspace Anda untuk mulai membuat pesanan lisensi baru ke pembeli aplikasi Anda.
Terima kasih!";

$d_em_quo = '<!DOCTYPE html>
<html>
<body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.6; color: #333333; background-color: #f4f6f8; padding: 20px; font-size: 15px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; padding: 30px; border-radius: 8px; border: 1px solid #e2e8f0;">
        <h2 style="color: #059669; margin-top: 0;">Top-Up Kuota Telah Disetujui</h2>
        <p>Halo <strong>{buyer_name}</strong>,</p>
        <p>Permintaan ekstraksi kuota lisensi penjualan Anda pada sistem Workspace telah berhasil disetujui dan ditambahkan ke dalam profil akun secara otomatis.</p>
        <div style="background: #ecfdf5; padding: 20px; border-radius: 8px; margin: 25px 0; border: 1px solid #a7f3d0;">
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: #065f46; font-weight: bold;">Penambahan Kuota:</td>
                    <td style="padding: 8px 0; color: #059669; text-align:right; font-weight: bold;">+ {kuota_tambahan} Tiket</td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; border-top: 1px solid #d1fae5; color: #065f46; font-weight: bold;">Total Saldo Saat Ini:</td>
                    <td style="padding: 8px 0; border-top: 1px solid #d1fae5; color: #059669; text-align:right; font-weight: bold; font-size: 18px;">{total_kuota} Tiket</td>
                </tr>
            </table>
        </div>
        <p>Anda kini sudah bisa menerima klien atau order aplikasi baru tanpa khawatir batasan kuota awal. Akses ke dasbor kapanpun untuk mengecek sisa kapasitas tersebut.</p>
        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
        <p style="font-size: 13px; color: #64748b; margin-bottom: 0;">Otomasi Tagihan & Kuota Sharelink AI.</p>
    </div>
</body>
</html>';

$d_wa_res = "Peringatan Keamanan ⚠️
Halo *{buyer_name}*,

Kami menerima permintaan pengaturan ulang kata sandi (reset password) untuk akun Workspace Sharelink AI Anda ({user_email}).

Jika ini memang Anda, klik link di bawah ini guna membuat kata sandi yang baru:
🔗 {reset_link}

*Link Reset ini bersifat sementara.* Jika Anda tidak merasa melakukan proses pemulihan (lupa sandi), maka abaikan pesan otomatis ini demi alasan keamanan data.
Terima kasih.";

$d_em_res = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Permintaan Reset Kata Sandi - ShareLink AI</title>
    <style type="text/css">
        /* Client-specific Styles untuk memastikan tampilan konsisten di semua aplikasi email */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f8fafc; }

        /* CSS Responsif untuk Smartphone */
        @media screen and (max-width: 600px) {
            .wrapper { width: 100% !important; max-width: 100% !important; padding: 0 !important; }
            .container { padding: 32px 20px !important; }
            .button-wrapper { width: 100% !important; text-align: center !important; }
            .button { display: block !important; padding: 14px 20px !important; }
            .info-cell { display: block !important; width: 100% !important; padding-right: 0 !important; padding-bottom: 16px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">

    <!-- Hidden Preheader Text (Meningkatkan Open Rate secara Alami) -->
    <div style="display: none; max-height: 0px; overflow: hidden; font-size: 1px; line-height: 1px; color: #ffffff; opacity: 0;">
        Gunakan tautan aman di dalam email ini untuk memperbarui kata sandi akun ShareLink AI Anda.
    </div>

    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; table-layout: fixed;">
        <tr>
            <td align="center" style="padding: 40px 10px;">
                
                <!--[if (gte mso 9)|(IE)]>
                <table align="center" border="0" cellspacing="0" cellpadding="0" width="580">
                <tr>
                <td align="center" valign="top" width="580">
                <![endif]-->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="wrapper" style="max-width: 580px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
                    
                    <!-- Top Accent Border (Warna Amber hangat khusus untuk notifikasi keamanan akun) -->
                    <tr>
                        <td height="6" style="background-color: #d97706; line-height: 6px; font-size: 6px;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td class="container" style="padding: 48px 40px; color: #1e293b;">
                            
                            <!-- Header / Salam Pembuka -->
                            <h2 style="font-size: 20px; font-weight: 700; color: #0d2870; margin-top: 0; margin-bottom: 24px; line-height: 1.4; letter-spacing: -0.01em;">
                                Permintaan Reset Kata Sandi
                            </h2>

                            <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 16px;">
                                Halo <strong>{buyer_name}</strong>,
                            </p>

                            <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 24px;">
                                Kami menerima permintaan pengaturan ulang kata sandi yang dikaitkan dengan alamat email berikut pada sistem autentikasi ShareLink AI:
                            </p>

                            <!-- Kotak Detail Akun yang Ditargetkan -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 28px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="margin: 0 0 4px 0; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; font-weight: 700;">
                                            Identitas Pengguna / Akun Email
                                        </p>
                                        <p style="margin: 0; font-size: 15px; color: #0f172a; font-weight: 600; word-break: break-all;">
                                            {user_email}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-top: 0; margin-bottom: 24px;">
                                Untuk melanjutkan proses pembuatan kata sandi yang baru, silakan gunakan tombol verifikasi aman di bawah ini:
                            </p>

                            <!-- Tombol CTA Bulletproof (Warna Navy Premium untuk konsistensi brand) -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px;">
                                <tr>
                                    <td align="left">
                                        <table border="0" cellspacing="0" cellpadding="0" class="button-wrapper">
                                            <tr>
                                                <td align="center" bgcolor="#0d2870" style="border-radius: 4px;">
                                                    <a href="{reset_link}" target="_blank" class="button" style="font-size: 14px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Arial, sans-serif; color: #ffffff; text-decoration: none; border-radius: 4px; padding: 12px 28px; border: 1px solid #0d2870; display: inline-block; font-weight: 600; letter-spacing: 0.02em;">
                                                        Ganti Kata Sandi Saya &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Alternatif Salin Tautan (Diproteksi agar aman dari deteksi pemblokir phising) -->
                            <p style="margin: 0 0 28px 0; font-size: 13px; line-height: 1.5; color: #64748b;">
                                Atau Anda dapat menyalin dan membuka tautan berikut langsung di peramban Anda:<br />
                                <a href="{reset_link}" target="_blank" style="color: #0d2870; text-decoration: underline; word-break: break-all;">
                                    {reset_link}
                                </a>
                            </p>

                            <!-- Edukasi Keamanan / Penjelasan Tindakan Pengabaian (Warna Abu Lembut Profesional) -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 16px 20px; font-size: 13.5px; line-height: 1.6; color: #475569;">
                                        <strong style="color: #0f172a;">Perlu Diingat:</strong> Apabila Anda tidak merasa melakukan tindakan atau permintaan ini, silakan <strong>abaikan email ini</strong> dengan aman. Perubahan sistem keamanan tidak akan diproses sepenuhnya selama tidak ada aktivitas kunjungan ke alamat konfirmasi di atas.
                                    </td>
                                </tr>
                            </table>

                            <!-- Kontak Bantuan / WhatsApp (Ditampilkan Sangat Rapi & Profesional) -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 32px;">
                                <tr>
                                    <td style="font-size: 14.5px; line-height: 1.6; color: #475569;">
                                        Bila ada kendala teknis, silakan hubungi tim kami di Whatsapp: 
                                        <a href="https://wa.me/solusimarketing" target="_blank" style="color: #0d2870; text-decoration: underline; font-weight: 600;">
                                            wa.me/solusimarketing
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Separator -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td height="1" style="background-color: #e2e8f0; line-height: 1px; font-size: 1px;">&nbsp;</td>
                                </tr>
                            </table>

                            <!-- Informasi Sistem & Ketentuan Keamanan -->
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 24px;">
                                <tr>
                                    <td>
                                        <p style="font-size: 13.5px; line-height: 1.6; color: #64748b; margin: 0 0 12px 0;">
                                            Notifikasi otomatis dikirimkan oleh infrastruktur keamanan ShareLink AI.
                                        </p>
                                        <p style="font-size: 13.5px; line-height: 1.6; color: #64748b; margin: 0 0 12px 0;">
                                            Pengiriman email ini didasarkan pada permintaan pemulihan kata sandi darurat yang masuk melalui antarmuka masuk dasbor aplikasi. Kami menjunjung tinggi privasi data dan keamanan akses setiap pengguna terdaftar.
                                        </p>
                                        <p style="font-size: 13.5px; line-height: 1.6; color: #64748b; margin: 0;">
                                            Demi keamanan ekosistem digital Anda, kami menyarankan untuk selalu memperbarui kata sandi secara berkala serta menggunakan kombinasi unik berupa kombinasi huruf besar, kecil, angka, dan simbol.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Bagian Kaki Email (Footer & Unsubscribe) -->
                    <tr>
                        <td style="padding: 32px 40px; background-color: #fafafa; border-top: 1px solid #f1f5f9; color: #64748b;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="font-size: 13.5px; line-height: 1.6;">
                                        <strong style="color: #334155; font-size: 15px;">ShareLink AI</strong><br />
                                        Pati, Jawa Tengah, Indonesia<br />
                                        <a href="https://sharelink.web.id" style="color: #0d2870; text-decoration: none; font-weight: 600;">https://sharelink.web.id</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top: 24px; font-size: 13px; line-height: 1.6; color: #94a3b8;">
                                        Apabila Anda ingin membatasi atau menghentikan penerimaan email komunikasi non-transaksional dari sistem kami, silakan akses halaman <a href="{unsubscribe_url}" style="color: #64748b; text-decoration: underline; font-weight: normal;">berhenti berlangganan</a>.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
                <!--[if (gte mso 9)|(IE)]>
                </td>
                </tr>
                </table>
                <![endif]-->

            </td>
        </tr>
    </table>

</body>
</html>';

$d_wa_quo_admin = "🚨 *Notifikasi Admin: Request Top-Up Kuota!*
User: {member_name} ({member_email})
Request: +{quota_amount} Lisensi

Harap periksa mutasi sebelum merespons.";

$d_wa_quo_req = "Halo *{member_name}*,

Anda telah meminta penambahan kuota: *+{quota_amount} Lisensi*
Mohon lakukan penyelesaian administrasi sebesar: *{total_harga}*

Balas pesan ini beserta bukti transfer agar kuota Anda segera kami Approve.";

$cl_wa_tpl_workspace = get_option('cl_wa_tpl_workspace', $d_wa_ws);
$cl_email_tpl_workspace = get_option('cl_email_tpl_workspace', $d_em_ws);
$cl_wa_tpl_license = get_option('cl_wa_tpl_license', $d_wa_lic);
$cl_email_tpl_license = get_option('cl_email_tpl_license', $d_em_lic);
$cl_wa_tpl_quota = get_option('cl_wa_tpl_quota', $d_wa_quo);
$cl_em_tpl_quota = get_option('cl_em_tpl_quota', $d_em_quo);
$cl_wa_tpl_quota_admin = get_option('cl_wa_tpl_quota_admin', $d_wa_quo_admin);
$cl_wa_tpl_quota_req = get_option('cl_wa_tpl_quota_req', $d_wa_quo_req);
$cl_wa_tpl_reset = get_option('cl_wa_tpl_reset', $d_wa_res);
$cl_em_tpl_reset = get_option('cl_em_tpl_reset', $d_em_res);
?>

<div class="w-full pb-12 space-y-6">
    <div class="mb-4 p-5 bg-indigo-50 border border-indigo-200 rounded-2xl flex items-start">
        <i data-lucide="info" class="w-6 h-6 text-indigo-500 mr-4 shrink-0"></i>
        <div>
            <h3 class="font-bold text-indigo-700 mb-1">Templating Engine System</h3>
            <p class="text-sm text-indigo-600">Konfigurasi pesan global yang dikirimkan sistem saat event otomatis (Webhook, Approve, dll) berjalan.</p>
        </div>
    </div>
    
    <form method="post" class="space-y-6">
        <?php wp_nonce_field('cl_act', '_clnonce'); ?>
        <input type="hidden" name="cl_save_admtxt" value="1">
        
        <!-- Workspace Creation (Flow 5B) -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3 flex items-center">
                <i data-lucide="users" class="w-5 h-5 mr-2 text-brand"></i> Auto-Generate Workspace (SaaS Subscriber Baru)
            </h3>
            <p class="text-[13px] text-slate-500 mb-4">Vars: <code>{buyer_name}</code>, <code>{login_url}</code>, <code>{buyer_email}</code>, <code>{user_pass}</code></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center"><i data-lucide="message-circle" class="w-4 h-4 mr-2 text-emerald-500"></i> WhatsApp Template</label>
                    <textarea name="cl_wa_tpl_workspace" rows="3" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_wa_tpl_workspace) ?></textarea>
                </div>
                <div>
                     <label class="block text-sm font-semibold text-slate-700 mb-2 flex items-center"><i data-lucide="mail" class="w-4 h-4 mr-2 text-brand"></i> Email Template</label>
                    <textarea name="cl_email_tpl_workspace" rows="6" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_email_tpl_workspace) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Global Template Pengiriman Lisensi -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3 flex items-center">
                <i data-lucide="key" class="w-5 h-5 mr-2 text-brand"></i> Global Template Pengiriman Lisensi (Aplikasi Member)
            </h3>
            <p class="text-[13px] text-slate-500 mb-4">Sebagai teks default saat member men-generate lisensi. Vars: <code>{buyer_name}</code>, <code>{app_name}</code>, <code>{license_key}</code>, <code>{link_gemini_CANVAS}</code>, <code>{custom_link}</code>, <code>{workspace_owner_wa}</code></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">WhatsApp Template</label>
                    <textarea name="cl_wa_tpl_license" rows="5" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_wa_tpl_license) ?></textarea>
                </div>
                <div>
                     <label class="block text-sm font-semibold text-slate-700 mb-2">Email Template</label>
                    <textarea name="cl_email_tpl_license" rows="5" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_email_tpl_license) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Request Top-Up Kuota -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3 flex items-center">
                <i data-lucide="database" class="w-5 h-5 mr-2 text-brand"></i> Request Top-Up Kuota (Member ke Admin)
            </h3>
            <p class="text-[13px] text-slate-500 mb-4">Vars: <code>{member_name}</code>, <code>{member_email}</code>, <code>{quota_amount}</code>, <code>{total_harga}</code></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">WhatsApp ke Admin (Notifikasi Peringatan)</label>
                    <textarea name="cl_wa_tpl_quota_admin" rows="3" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_wa_tpl_quota_admin) ?></textarea>
                </div>
                <div>
                     <label class="block text-sm font-semibold text-slate-700 mb-2">WhatsApp ke Member (Petunjuk Pembayaran)</label>
                    <textarea name="cl_wa_tpl_quota_req" rows="5" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_wa_tpl_quota_req) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Quota Approval -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3 flex items-center">
                <i data-lucide="database" class="w-5 h-5 mr-2 text-brand"></i> Quota Approval (Top-Up Kuota)
            </h3>
            <p class="text-[13px] text-slate-500 mb-4">Vars: <code>{buyer_name}</code>, <code>{kuota_tambahan}</code>, <code>{total_kuota}</code></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">WhatsApp Template</label>
                    <textarea name="cl_wa_tpl_quota" rows="5" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_wa_tpl_quota) ?></textarea>
                </div>
                <div>
                     <label class="block text-sm font-semibold text-slate-700 mb-2">Email Template</label>
                    <textarea name="cl_em_tpl_quota" rows="5" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_em_tpl_quota) ?></textarea>
                </div>
            </div>
        </div>

        <!-- Reset Password Subscriber -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <h3 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3 flex items-center">
                <i data-lucide="shield-alert" class="w-5 h-5 mr-2 text-brand"></i> Reset Password Subscriber
            </h3>
            <p class="text-[13px] text-slate-500 mb-4">Vars: <code>{buyer_name}</code>, <code>{user_email}</code>, <code>{reset_link}</code></p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">WhatsApp Template</label>
                    <textarea name="cl_wa_tpl_reset" rows="5" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_wa_tpl_reset) ?></textarea>
                </div>
                <div>
                     <label class="block text-sm font-semibold text-slate-700 mb-2">Email Template</label>
                    <textarea name="cl_em_tpl_reset" rows="5" class="w-full border border-slate-200 rounded-xl p-3 text-sm font-mono focus:ring-2 focus:ring-brand/20"><?= esc_textarea($cl_em_tpl_reset) ?></textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand hover:bg-[#002b6b] text-white px-8 py-3 rounded-xl font-bold shadow transition-all flex items-center">
                <i data-lucide="save" class="w-5 h-5 mr-2"></i> Simpan Semua Template Admin
            </button>
        </div>
    </form>
</div>
