# Konsep Plugin Pendukung: Illu Shield v1.0.1 (Keamanan & Optimasi)

Dokumen ini berisi rancangan detail plugin **Illu Shield v1.0.1** untuk ekosistem Sharelink AI. Plugin ini difokuskan pada keamanan tinggi (Security) dan kecepatan (Caching), dengan prioritas utama: **Sama sekali tidak boleh mengganggu fungsi utama plugin `sharelink-wp`**, khususnya integrasi lisensi dinamis untuk aplikasi Gemini Canvas sandboxed.

Pembaruan utama pada v1.0.1 adalah tersedianya **Admin Dashboard Dedicated** untuk monitoring dan konfigurasi.

---

## 1. UI & Admin Dashboard (Fitur Baru v1.0.1)

Illu Shield akan memiliki menu mandiri di Sidebar Utama WordPress Admin (sejajar dengan Dashboard, Posts, Sharelink AI, dll). 
Dashboard manajemen akan dibuat apik (menggunakan UI layout modern khas Illusi) yang mencakup:

*   **Menu "Settings / Pengaturan":**
    *   Toggles (On/Off switch) untuk setiap fitur (Enable 2FA, Micro-Firewall, XML-RPC Disable, Login Protection, Smart Cache).
    *   Pengaturan Rules 2FA (Misal: Paksa pengguna level Administrator untuk wajib 2FA).
*   **Menu "Analytics & Logs":**
    *   **Security Logs:** Tabel aktivitas yang menampilkan riwayat percobaan login gagal, IP yang sedang diblokir sementara, dan ancaman injeksi yang berhasil difilter oleh Micro-Firewall.
    *   **Cache Status:** Informasi ukuran cache statis, jumlah halaman yang di-cache, dan tombol pintas "Clear All Cache".

## 2. Fitur Keamanan (Security Core)

Fitur keamanan dibuat *lightweight* agar tidak membebani server, sekaligus menambal celah umum pada WordPress.

*   **Two-Factor Authentication (2FA) (Fitur Baru & Esensial):**
    *   Dukungan penuh untuk aplikasi TOTP standar (Google Authenticator, Authy, Microsoft Authenticator).
    *   Halaman profil (*Member Area*) akan memiliki menu untuk scan QR code dan mengaktifkan 2FA.
    *   Admin dapat membuat aturan apakah 2FA diwajibkan untuk *Role* tertentu (misalnya wajib untuk Administrator, opsional untuk Subscriber).
    *   *Fallback:* Fitur kode *recovery* jika *device* authenticator pengguna hilang.
*   **Login Protection & Rate Limiting:**
    *   Pembatasan percobaan login gagal pada halaman *login* untuk mencegah serangan *Brute-force*.
    *   Jika threshold terlewati (misal 5x gagal dalam 5 menit), IP penyerang diblokir sementara.
*   **Anti-Injection & XSS Protection (Micro-Firewall):**
    *   Filter cerdas pada protokol HTTP (`$_GET`, `$_POST` dll) untuk memblokir pola SQL Injection atau XSS tanpa mengganggu *payload* teks yang panjang/kompleks (yang mungkin digunakan oleh autoresponder atau pengaturan theme).
*   **Upload Folder Lockdown:**
    *   Mencegah eksekusi script `.php` di direktori `wp-content/uploads/` (tempat hacker sering menaruh eksekusi *backdoor*).
*   **Disable XML-RPC (Mitigasi DDoS):**
    *   Memblokir jalur usang `xmlrpc.php` yang sudah tidak relevan tapi sering dieksploitasi botnet, tanpa mematikan fitur REST API.

## 3. Fitur Optimasi & Kecepatan (Smart Caching)

*   **Fragment & Session-Aware Caching:**
    *   Halaman statis dan rutinitas biasa di-cache untuk mempercepat muatan web (*Time to First Byte*).
    *   **Auto-Bypass:** Cache akan *otomatis dinonaktifkan* jika sistem mendeteksi user tersebut sedang login (*authenticated user*) atau jika URL tersebut merujuk pada `Sharelink Member Area` atau proses Checkout. Dashboard user akan selalu 100% *real-time/live*.
*   **Heartbeat Control:**
    *   Mengatur interval *WordPress Heartbeat* (fitur autosave & check session bawaan WP) yang sering memakan CPU tinggi ketika puluhan *user subscriber* login bersamaan.

## 4. Logika "Zero-Conflict" Khusus Sharelink-WP (CRUCIAL MECHANISM)

Mengingat inti dari bisnis web ini adalah fungsionalitas `sharelink-wp` (jualan lisensi Gemini Canvas), Illu Shield dibangun di atas fondasi **anti-blokir** untuk ekosistem tersebut:

1.  **Open CORS & Verifikasi Dinamis Tetap Terjaga:**
    *   Halaman verifikasi Gemini Canvas dari Google bersifat *sandboxed* (menggunakan URL dan IP dinamis seperti `*googleusercontent.com` dsb).
    *   Oleh karena itu, endpoint `/wp-json/sharelink/v1/...` (misal endpoint `/verify` untuk cek lisensi) **akan dikeluarkan dari aturan Rate Limiting ketat dan pembatasan REST API**. Asumsi bahwa CORS selalu `*` pada Sharelink dipertahankan sepenuhnya.
2.  **Sanitasi Input, Bukan Pemblokiran Origin:**
    *   Pada verifikasi API eksternal (Webhook & Canvas), Illu Shield akan mengamankan prosesnya murni dengan *Sanitasi Payload/Input* dan pencocokan *License Key*, **bukan** dengan memblokir IP/Origin perantara (karena IP tersebut milik Google Sandbox / pihak ketiga yang sah).
3.  **Strict Path Whitelisting:**
    *   Path routing utama aplikasi (seperti `/ai/{custom_slug}/`, `/webhook`, dsb) di-set ke mode **Pass-Through**. Illu Shield tidak akan pernah menyimpan cache statis atau merusak redirect pada halaman-halaman ajaib ini.

## 5. Rencana Tahap Pengembangan (Roadmap Eksekusi v1.0.1)

*   **Fase 1 (Sistem Inti, 2FA & WP Admin UI):** 
    Menulis struktur dasar plugin **Illu Shield v1.0.1**. Membangun menu di Sidebar WP Admin (Settings & Analytics), dan integrasi UI/Logika untuk sistem 2FA di profil.
*   **Fase 2 (Micro-Firewall & Lockdown):**
    Mengimplementasikan proteksi *Anti-Injection*, pembatasan brute-force (dengan logging ke dashboard admin), dan *Upload folder lockdown*.
*   **Fase 3 (Optimasi & Zero-Conflict Bypass):**
    Mengimplementasikan *Smart Cache* (beserta tombol clear cache di dashboard), *Heartbeat Control*, serta menuliskan logika *Whitelist* absolut untuk semua endpoint `sharelink-wp`.

*(Silakan jadikan dokumen ini sebagai acuan tetap. Eksekusi script hanya akan dilakukan apabila semua poin konfigurasi teknis di atas telah Anda setujui).*
