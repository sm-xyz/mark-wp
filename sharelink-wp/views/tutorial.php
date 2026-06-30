<?php
if (!defined('ABSPATH')) exit;
?>
<div class="space-y-6">
    <!-- Header Page -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 md:p-8 text-white shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6 relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-white/[0.05] [mask-image:linear-gradient(0deg,white,rgba(255,255,255,0.6))] pointer-events-none"></div>
        <div class="relative z-10 space-y-2">
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight">Panduan Tutorial Penggunaan</h1>
            <p class="text-indigo-200 text-sm max-w-2xl leading-relaxed">
                Pelajari langkah demi langkah cara memproteksi aplikasi, mengelola lisensi pelanggan, konfigurasi autoresponder, hingga proses penyerahan/assign lisensi secara profesional.
            </p>
        </div>
        <div class="relative z-10 shrink-0 bg-white/15 p-3 rounded-2xl border border-white/20 shadow-inner flex items-center justify-center">
            <i data-lucide="help-circle" class="w-10 h-10 text-indigo-300"></i>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-sm flex flex-col sm:flex-row gap-1.5 scrollbar-thin overflow-x-auto">
        <button onclick="switchTutorialTab('tab-app-script')" id="btn-tab-app-script" class="tutorial-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-bold text-center transition-all bg-brand text-white shadow-sm flex items-center justify-center gap-2">
            <i data-lucide="code" class="w-4 h-4"></i> Membuat App+Login Script
        </button>
        <button onclick="switchTutorialTab('tab-license')" id="btn-tab-license" class="tutorial-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
            <i data-lucide="key" class="w-4 h-4"></i> Membuat Lisensi
        </button>
        <button onclick="switchTutorialTab('tab-autoresponder')" id="btn-tab-autoresponder" class="tutorial-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
            <i data-lucide="message-square" class="w-4 h-4"></i> Setting Autoresponder
        </button>
        <button onclick="switchTutorialTab('tab-assign')" id="btn-tab-assign" class="tutorial-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-medium text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Assign Lisensi ke Member
        </button>
    </div>

    <!-- Tab Contents Container -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden">
        
        <!-- Tab 1: Membuat App+Login Script -->
        <div id="tab-app-script" class="tutorial-tab-content p-6 md:p-8 space-y-6">
            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                    <i data-lucide="code" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Membuat App + Login Script</h2>
                    <p class="text-xs text-slate-400">Cara mendaftarkan aplikasi Gemini Canvas Anda dan memasang sistem login gembok keamanan.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Langkah-Langkah:</h3>
                    <div class="relative pl-8 space-y-6 before:absolute before:left-3 before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">1</div>
                            <h4 class="font-bold text-slate-800 text-sm">Siapkan File HTML Aplikasi</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Pastikan Anda telah memiliki file kode HTML tunggal (single-file HTML) dari aplikasi Anda (cth: hasil build, project generator, dsb) tanpa sistem login gembok.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">2</div>
                            <h4 class="font-bold text-slate-800 text-sm">Buka Menu "Canvas Apps"</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Masuk ke menu <strong>Canvas Apps</strong> di bilah navigasi kiri, kemudian isi detail seperti Nama Aplikasi, Deskripsi, dan salin seluruh script HTML ke textarea <strong>Full Script (html, css, js)</strong>.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">3</div>
                            <h4 class="font-bold text-slate-800 text-sm">Kustomisasi Tampilan Login Gate</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Sesuaikan skema warna background, warna tombol, teks placeholder, logo, pesan jika tidak memiliki lisensi, serta tautan redirect pembelian pada panel konfigurasi di bagian bawah formulir.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">4</div>
                            <h4 class="font-bold text-slate-800 text-sm">Generate Script & Copy</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Klik tombol <strong>Generate Script</strong> untuk mengombinasikan login gate dengan aplikasi Anda. Setelah berhasil, klik tombol <strong>Copy Script</strong> untuk menyalin kode tersebut dan klik **Simpan App & Login**.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200/60 space-y-4">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-widest flex items-center gap-1.5 border-b border-slate-200/50 pb-2">
                        <i data-lucide="info" class="w-4 h-4 text-indigo-500"></i> Tips Tambahan
                    </h3>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Anda dapat menguji login gembok secara langsung menggunakan panel <strong>Live Preview Sandbox</strong> di kolom sebelah kanan sebelum menaruhnya di hosting publik. 
                    </p>
                    <div class="p-3 bg-indigo-50 border border-indigo-150 rounded-xl text-[11px] text-indigo-800 leading-relaxed">
                        <strong>💡 Sandboxing Aman:</strong> Sistem menduplikasi file HTML Anda ke dalam memori sandbox real-time, memungkinkan Anda bereksperimen dengan input warna, tinggi logo, atau CTA pembelian tanpa memengaruhi file rilis production Anda.
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Membuat Lisensi -->
        <div id="tab-license" class="tutorial-tab-content p-6 md:p-8 space-y-6 hidden">
            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                    <i data-lucide="key" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Membuat Kunci Lisensi</h2>
                    <p class="text-xs text-slate-400">Langkah untuk menerbitkan token serial unik guna membuka proteksi login aplikasi Anda.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Langkah-Langkah:</h3>
                    <div class="relative pl-8 space-y-6 before:absolute before:left-3 before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">1</div>
                            <h4 class="font-bold text-slate-800 text-sm">Masuk ke Halaman "Lisensi"</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Pilih tab menu <strong>Lisensi</strong> pada sidebar navigasi di sebelah kiri dashboard Anda.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">2</div>
                            <h4 class="font-bold text-slate-800 text-sm">Klik "Buat Lisensi Baru"</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Tekan tombol aksi yang tersedia untuk membuka popup formulir pembuatan lisensi di dalam workspace Anda.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">3</div>
                            <h4 class="font-bold text-slate-800 text-sm">Atur Relasi & Kuota Maksimal Perangkat</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Tautkan lisensi ke salah satu Canvas App milik Anda. Tentukan batas limitasi (kuota perangkat) maksimal yang diperbolehkan untuk menggunakan lisensi secara bersamaan.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">4</div>
                            <h4 class="font-bold text-slate-800 text-sm">Salin Serial Key</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Gunakan atau klik ikon clipboard untuk menyalin lisensi yang ter-generate secara otomatis (misal: `CL-XXXX-XXXX-XXXX`) agar siap dikirimkan kepada pelanggan.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-indigo-50/50 rounded-2xl p-5 border border-indigo-100 space-y-4">
                    <h3 class="text-xs font-bold text-indigo-950 uppercase tracking-widest flex items-center gap-1.5 border-b border-indigo-100 pb-2">
                        <i data-lucide="shield" class="w-4 h-4 text-indigo-600"></i> Sistem Verifikasi Device
                    </h3>
                    <p class="text-xs text-indigo-900 leading-relaxed">
                        Sistem keamanan kami melacak sidik jari perangkat digital pengguna (browser fingerprint) saat melakukan login pertama kali. 
                    </p>
                    <div class="p-3 bg-white rounded-xl text-[11px] text-slate-600 border border-indigo-100 leading-relaxed">
                        <strong>📌 Status Reset Perangkat:</strong> Sebagai Admin atau Anggota Utama Workspace, Anda dapat me-reset sidik jari perangkat login dari member/pelanggan Anda apabila mereka berganti handphone atau komputer baru, langsung melalui panel list lisensi.
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 3: Setting Autoresponder -->
        <div id="tab-autoresponder" class="tutorial-tab-content p-6 md:p-8 space-y-6 hidden">
            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                    <i data-lucide="message-square" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Setting Autoresponder Automatis</h2>
                    <p class="text-xs text-slate-400">Konfigurasi pesan penyerahan lisensi otomatis melalui WhatsApp (Fonnte) dan Email (Mailketing).</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Langkah-Langkah:</h3>
                    <div class="relative pl-8 space-y-6 before:absolute before:left-3 before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">1</div>
                            <h4 class="font-bold text-slate-800 text-sm">Pastikan Token Global Aktif</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Pastikan API Token Fonnte (untuk WA) dan Mailketing Token (untuk Email) telah dikonfigurasi dengan benar oleh Global Admin.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">2</div>
                            <h4 class="font-bold text-slate-800 text-sm">Masuk ke Menu "Autoresponder"</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Klik tab menu <strong>Autoresponder</strong> untuk me-manage قالب (template) penulisan pesan otomatis Anda.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">3</div>
                            <h4 class="font-bold text-slate-800 text-sm">Tulis Template Pesan (Kustom)</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Ketik format teks pengiriman WA atau Email. Terdapat tombol preview untuk memvisualisasikan bagaimana hasil akhir pesan tersebut.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">4</div>
                            <h4 class="font-bold text-slate-800 text-sm">Gunakan Tag Dinamis</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Sisipkan tag dinamis seperti <code>{member_name}</code>, <code>{license_key}</code>, <code>{total_device}</code>, dan <code>{app_name}</code> di dalam konten teks pesan Anda.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="bg-amber-50 rounded-2xl p-5 border border-amber-200/60 space-y-3">
                        <h3 class="text-xs font-bold text-amber-900 uppercase tracking-widest flex items-center gap-1.5">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-amber-600"></i> Contoh Struktur Pesan WA
                        </h3>
                        <pre class="bg-white p-3 rounded-xl text-[11px] font-mono border border-amber-100 text-slate-700 leading-relaxed break-words whitespace-pre-wrap">
Halo {member_name}! Terimakasih telah berlangganan.

Berikut adalah detail kuota akses aplikasi Anda:
Aplikasi: {app_name}
Lisensi: {license_key}
Limitasi: {total_device} Perangkat

Akses URL Aplikasi: {app_slug_url}
Silakan masukkan lisensi Anda di atas untuk membuka aplikasi.</pre>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 4: Assign Lisensi ke member -->
        <div id="tab-assign" class="tutorial-tab-content p-6 md:p-8 space-y-6 hidden">
            <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Menyerahkan (Assign) Lisensi ke Pengguna</h2>
                    <p class="text-xs text-slate-400">Metode manual dan metode otomatisasi webhook Lynk.id untuk menyebarkan lisensi secara mandiri.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Metode Manual:</h3>
                    <div class="relative pl-8 space-y-6 before:absolute before:left-3 before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">1</div>
                            <h4 class="font-bold text-slate-800 text-sm">Buka List Lisensi</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Cari lisensi kosong yang belum memiliki pemilik (berstatus Belum Digunakan).</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">2</div>
                            <h4 class="font-bold text-slate-800 text-sm">Klik Aksi "Assign Member"</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Masukkan info data pembeli seperti Nama, Email, dan No WA WhatsApp.</p>
                        </div>
                        <div class="relative">
                            <div class="absolute -left-8 top-0.5 w-6 h-6 rounded-full bg-slate-900 text-white font-bold text-xs flex items-center justify-center shadow-sm">3</div>
                            <h4 class="font-bold text-slate-800 text-sm">Pesan Auto-Respon Terkirim</h4>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">Tombol simpan otomatis menugaskan lisensi ke pembeli dan memicu bot pengirim/gateway mengirimkan pesan serial kustom ke nomor WhatsApp / Email pembeli.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Metode Otomatisasi (Webhook Lynk.id):</h3>
                    <div class="bg-emerald-50 rounded-2xl p-5 border border-emerald-200/60 space-y-3">
                        <h4 class="font-bold text-emerald-950 text-sm flex items-center gap-2">
                            <i data-lucide="zap" class="w-4 h-4 text-emerald-600 animate-bounce"></i> Integrasi Otomatis
                        </h4>
                        <p class="text-xs text-emerald-900 leading-relaxed">
                            Anda dapat mengintegrasikan penjualan landing page Lynk.id secara langsung dengan menyalin link Webhook unik yang ada di halaman <strong>Edit Profile</strong> Anda.
                        </p>
                        <div class="p-3 bg-white rounded-xl text-[11px] text-slate-600 border border-emerald-150 leading-relaxed">
                            <strong>⚡ Cara Pasang:</strong> Tempelkan payload URL Webhook ke bagian Integrasi Pembayaran Lynk.id. Setiap Checkout "Lunas" (Paid) otomatis memicu generator lisensi, membuat lisensi baru untuk aplikasi terkait, mencatat info pembeli ke daftar kontak, dan mengirimkan pesan WA di detik yang sama!
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function switchTutorialTab(tabId) {
    // Hide all contents
    document.querySelectorAll('.tutorial-tab-content').forEach(el => el.classList.add('hidden'));
    
    // Deactivate all tab buttons
    document.querySelectorAll('.tutorial-tab-btn').forEach(btn => {
        btn.className = "tutorial-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-medium text-slate-600 hover:text-slate-950 hover:bg-slate-50 transition-all flex items-center justify-center gap-2";
    });
    
    // Show chosen tab
    const chosenEl = document.getElementById(tabId);
    if(chosenEl) chosenEl.classList.remove('hidden');
    
    // Activate clicked button
    const activeBtn = document.getElementById('btn-' + tabId);
    if(activeBtn) {
        activeBtn.className = "tutorial-tab-btn flex-1 py-3 px-4 rounded-xl text-sm font-bold text-center transition-all bg-brand text-white shadow-sm flex items-center justify-center gap-2";
    }
}
</script>
