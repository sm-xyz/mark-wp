<?php if ( ! is_page_template( 'template-canvas.php' ) ) : ?>
<footer class="border-t border-slate-200 mt-12 py-8 dark:border-slate-700" style="background-color: var(--color-footer-bg);">
    <div class="max-w-4xl mx-auto px-4 text-center text-sm text-slate-600 dark:text-slate-300">
        <nav class="flex justify-center space-x-6 mb-6 font-medium">
            <?php
            if (has_nav_menu('footer')) {
                wp_nav_menu(array(
                    'theme_location' => 'footer',
                    'container'      => false,
                    'items_wrap'     => '%3$s',
                    'fallback_cb'    => false,
                ));
            }
            ?>
        </nav>
        <p>&copy; <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
        <?php if (get_theme_mod('illusi_footer_text', 'Aesthetic & Speed by Illusi Theme.')): ?>
            <p class="mt-2 text-xs opacity-75"><?php echo wp_kses_post(get_theme_mod('illusi_footer_text', 'Aesthetic & Speed by Illusi Theme.')); ?></p>
        <?php endif; ?>
    </div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
