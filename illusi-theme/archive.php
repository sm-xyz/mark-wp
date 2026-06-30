<?php
/**
 * The template for displaying archive pages
 */

get_header(); ?>

<main class="mx-auto max-w-4xl px-4 py-8 md:py-12" id="main-content">
    <header class="mb-10 text-center sm:text-left">
        <?php
            the_archive_title('<h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">', '</h1>');
            the_archive_description('<div class="mt-3 text-lg text-slate-500 dark:text-slate-400">', '</div>');
        ?>
    </header>

    <?php get_template_part('template-parts/content-loop'); ?>
</main>

<?php get_footer(); ?>
