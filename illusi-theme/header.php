<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        // Anti-FOUC Dark Mode script
        (function() {
            const theme = localStorage.getItem('theme');
            if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <?php wp_head(); ?>
</head>
<body <?php body_class('bg-slate-50 text-slate-900 min-h-screen antialiased dark:bg-slate-900 dark:text-slate-100 transition-colors duration-200'); ?>>
<?php wp_body_open(); ?>

<?php if ( ! is_page_template( 'template-canvas.php' ) ) : ?>
<header class="bg-header-bg border-b border-slate-200 dark:bg-slate-800 dark:border-slate-700 sticky top-0 z-50">
    <div class="max-w-4xl mx-auto px-4 h-16 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <?php
            if (has_custom_logo()) {
                $custom_logo_id = get_theme_mod('custom_logo');
                $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
                echo '<a href="' . esc_url(home_url('/')) . '"><img src="' . esc_url($logo[0]) . '" alt="' . esc_attr(get_bloginfo('name')) . '" width="' . esc_attr($logo[1]) . '" height="' . esc_attr($logo[2]) . '" class="h-8 w-auto"></a>';
            } else {
                echo '<a href="' . esc_url(home_url('/')) . '" class="font-bold text-xl tracking-tight text-primary dark:text-blue-400">' . get_bloginfo('name') . '</a>';
            }
            ?>
        </div>
        
        <nav class="hidden md:flex space-x-6 text-sm font-medium">
            <?php
            if (has_nav_menu('primary')) {
                wp_nav_menu(array(
                    'theme_location' => 'primary',
                    'container'      => false,
                    'items_wrap'     => '%3$s',
                    'fallback_cb'    => false,
                ));
            } else {
                echo '<a href="#" class="text-header-text hover:text-header-hover dark:text-slate-300 dark:hover:text-blue-400 transition-colors">Setup Menu</a>';
            }
            ?>
        </nav>
        
        <div class="flex items-center gap-1">
            <?php if (get_theme_mod('illusi_enable_header_search', false)) : ?>
            <button id="search-toggle" type="button" class="p-2 text-slate-500 rounded-lg hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-slate-400 dark:hover:bg-slate-700 transition-colors" aria-label="Search">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            </button>
            <?php endif; ?>
            
            <button id="theme-toggle" type="button" class="p-2 text-slate-500 rounded-lg hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-slate-400 dark:hover:bg-slate-700 transition-colors" title="Toggle Dark/Light Mode" aria-label="Toggle Dark/Light Mode">
                <svg id="theme-toggle-dark-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                <svg id="theme-toggle-light-icon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4.22 3.22a1 1 0 011.415 0l.707.707a1 1 0 01-1.414 1.414l-.707-.707a1 1 0 010-1.414zM18 10a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM15.636 14.22a1 1 0 010 1.415l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 0zM10 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm-4.22-3.22a1 1 0 01-1.415 0l-.707-.707a1 1 0 011.414-1.414l.707.707a1 1 0 010 1.414zM4 10a1 1 0 11-2 0 1 1 0 012 0zm1.78-4.22a1 1 0 010-1.415l.707-.707a1 1 0 011.414 1.414l-.707.707a1 1 0 01-1.414 0zM10 14a4 4 0 100-8 4 4 0 000 8z"></path></svg>
            </button>
        </div>
    </div>
</header>

<?php if (get_theme_mod('illusi_enable_header_search', false)) : ?>
<!-- Search Modal -->
<div id="search-modal" class="fixed inset-0 z-[100] hidden bg-slate-900/80 backdrop-blur-sm transition-opacity opacity-0 flex items-start justify-center pt-20 px-4">
    <div class="bg-white dark:bg-slate-800 w-full max-w-2xl rounded-2xl shadow-2xl transform scale-95 transition-transform overflow-hidden" id="search-modal-content">
        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="relative flex items-center">
            <svg class="absolute left-4 w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="search" name="s" id="search-input" class="w-full bg-transparent border-0 outline-none py-5 pl-12 pr-14 text-lg !text-slate-900 dark:!text-white opacity-100 placeholder-slate-400 focus:ring-0 focus:outline-none" placeholder="Search..." autocomplete="off" autofocus>
            <button type="button" id="search-close" class="absolute right-4 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </form>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchToggle = document.getElementById('search-toggle');
    const searchModal = document.getElementById('search-modal');
    const searchModalContent = document.getElementById('search-modal-content');
    const searchClose = document.getElementById('search-close');
    const searchInput = document.getElementById('search-input');

    function openSearch() {
        searchModal.classList.remove('hidden');
        // trigger reflow
        void searchModal.offsetWidth;
        searchModal.classList.remove('opacity-0');
        searchModalContent.classList.remove('scale-95');
        setTimeout(() => searchInput.focus(), 100);
    }

    function closeSearch() {
        searchModal.classList.add('opacity-0');
        searchModalContent.classList.add('scale-95');
        setTimeout(() => searchModal.classList.add('hidden'), 200);
    }

    if (searchToggle) searchToggle.addEventListener('click', openSearch);
    if (searchClose) searchClose.addEventListener('click', closeSearch);
    if (searchModal) {
        searchModal.addEventListener('click', function(e) {
            if (e.target === searchModal) closeSearch();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !searchModal.classList.contains('hidden')) closeSearch();
    });
});
</script>
<?php endif; ?>
<?php endif; ?>
