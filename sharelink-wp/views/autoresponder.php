<?php
if (!defined('ABSPATH')) exit;

$uid = get_current_user_id();

if (isset($_POST['_clnonce']) && wp_verify_nonce($_POST['_clnonce'], 'cl_act') && isset($_POST['cl_save_ar'])) {
    update_user_meta($uid, 'cl_ar_wa', sanitize_textarea_field($_POST['ar_wa']));
    update_user_meta($uid, 'cl_ar_email', stripslashes($_POST['ar_email']));
    cl_insert_history($uid, "Template autoresponder (WhatsApp & Email) telah berhasil diperbarui.");
    echo "<script>document.addEventListener('DOMContentLoaded', ()=>showToast('Template autoresponder disimpan.'));</script>";
}

$def_wa = "Halo *{buyer_name}*,\nSelamat, pesanan akses untuk aplikasi *{app_name}* Anda telah diaktifkan! 🎉\nBerikut adalah informasi detail untuk mengakses canvas kami dengan aman:\n================================\nKunci Lisensi Pribadi: `{license_key}`\nLink URL Akses     : {link_gemini_CANVAS}\nLink Custom    : {custom_link}\n================================\nCatatan Penting:\nSilahkan kunjungi link akses di atas, lalu masukkan kunci lisensi untuk mulai menggunakan fitur lengkap aplikasi ini. Lisensi ini bersifat eksklusif untuk Anda.\n> Jika ada kendala, jangan ragu menghubungi tim seller *{app_name}* di whatsapp: https://wa.me/{workspace_owner_wa}\n\nTerima kasih dan selamat berkarya! ✨";

$def_email = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
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
                                        <strong style="color: #14532d;">Rekomendasi Keamanan:</strong> Harap jaga kerahasiaan Kode Lisensi Anda dan hindari membagikannya ke publik. Sistem kami melakukan monitoring otomatis guna mencegah penyalahgunaan lisensi oleh pihak yang tidak bertanggung bertanggung jawab.
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

$wa_val = get_user_meta($uid, 'cl_ar_wa', true) ?: $def_wa;
$em_val = get_user_meta($uid, 'cl_ar_email', true) ?: $def_email;
?>

<div class="w-full pb-12">
    <div class="mb-8 p-5 bg-blue-50 border border-blue-200 rounded-2xl flex items-start">
        <i data-lucide="info" class="w-6 h-6 text-blue-500 mr-4 shrink-0"></i>
        <div>
            <h3 class="font-bold text-blue-700 mb-2">Variabel Template</h3>
            <p class="text-sm text-blue-600 mb-2">Gunakan var ini dalam pesan Anda: <code>{buyer_name}</code>, <code>{app_name}</code>, <code>{license_key}</code>, <code>{link_gemini_CANVAS}</code>, <code>{custom_link}</code>, <code>{workspace_owner_wa}</code>.</p>
            <p class="text-[13px] text-blue-500">Pesan ini dikirim kepada pembeli ketika Anda menekan tombol Assign/Kirim Akses di menu Lisensi.</p>
        </div>
    </div>
    
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
            <h2 class="font-bold text-slate-800 flex items-center">
                <i data-lucide="message-square-dashed" class="w-5 h-5 mr-3 text-brand"></i>
                Setting Autoresponder Aplikasi (Assign License)
            </h2>
        </div>
        
        <form method="post" class="p-6">
            <?php wp_nonce_field('cl_act', '_clnonce'); ?>
            <input type="hidden" name="cl_save_ar" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-3 flex items-center">
                        <i data-lucide="message-circle" class="w-4 h-4 mr-2 text-emerald-500"></i> Template WhatsApp
                    </label>
                    <textarea name="ar_wa" rows="5" class="w-full border border-slate-200 rounded-xl p-4 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 font-mono"><?= esc_textarea($wa_val) ?></textarea>
                </div>
                
                <div>
                     <label class="block text-sm font-semibold text-slate-700 mb-3 flex items-center">
                        <i data-lucide="mail" class="w-4 h-4 mr-2 text-brand"></i> Template Email (HTML)
                    </label>
                    <textarea name="ar_email" rows="14" class="w-full border border-slate-200 rounded-xl p-4 text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand font-mono"><?= esc_textarea($em_val) ?></textarea>
                </div>
            </div>
            
            <div class="border-t border-slate-100 pt-6">
                <button type="submit" class="bg-brand hover:bg-[#002b6b] text-white px-8 py-3 rounded-xl font-bold shadow-sm transition-all flex items-center ms-auto">
                    <i data-lucide="save" class="w-4 h-4 mr-2"></i> Simpan Template
                </button>
            </div>
        </form>
    </div>
</div>
