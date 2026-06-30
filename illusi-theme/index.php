<?php
/**
 * Main template file
 */

get_header(); ?>

<main class="mx-auto max-w-4xl px-4 py-8 md:py-12" id="main-content">
    <div class="mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
            <?php if (is_home() && !is_front_page()) : ?>
                <?php single_post_title(); ?>
            <?php else: ?>
                Illusi Theme
            <?php endif; ?>
        </h1>
        <p class="mt-3 text-lg text-slate-500 dark:text-slate-400">A ultra-lightweight, SPA-like WordPress theme experience.</p>
    </div>
    
    <?php get_template_part('template-parts/content-loop'); ?>
</main>

<?php get_footer(); ?>
