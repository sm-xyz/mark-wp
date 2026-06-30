<?php
/**
 * The template for displaying search results pages
 */

get_header(); ?>

<main class="mx-auto max-w-4xl px-4 py-8 md:py-12" id="main-content">
    <header class="mb-10 text-center sm:text-left">
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
            <?php printf(esc_html__('Search Results for: %s', 'illusi-theme'), '<span class="text-primary">' . get_search_query() . '</span>'); ?>
        </h1>
    </header>

    <?php get_template_part('template-parts/content-loop'); ?>
</main>

<?php get_footer(); ?>
