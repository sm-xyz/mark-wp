<?php
/**
 * The template for displaying 404 pages (not found)
 */

get_header(); ?>

<main class="mx-auto max-w-4xl px-4 py-16 md:py-24 text-center" id="main-content">
    <div class="max-w-md mx-auto">
        <h1 class="text-8xl font-black text-slate-200 dark:text-slate-800 mb-4 tracking-tighter">404</h1>
        <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-4">Halaman tidak ditemukan</h2>
        <p class="text-lg text-slate-600 dark:text-slate-400 mb-8">Maaf, halaman yang Anda cari mungkin telah dihapus, diubah namanya, atau tidak tersedia sementara.</p>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-primary-text bg-primary hover:bg-primary-hover transition-colors">
            Kembali ke Beranda
        </a>
    </div>
</main>

<?php get_footer(); ?>
