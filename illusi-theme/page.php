<?php
/**
 * Page template
 */

get_header(); ?>

<main class="mx-auto max-w-3xl px-4 py-8 md:py-12" id="main-content">
    <?php
    if (have_posts()) :
        while (have_posts()) : the_post();
            
            illusi_breadcrumbs();
            ?>
            <article class="prose prose-slate dark:prose-invert max-w-none prose-a:text-primary hover:prose-a:text-primary-hover dark:prose-a:text-blue-400 prose-headings:font-bold">
                <header class="mb-8 not-prose">
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-slate-900 dark:text-white mb-4 leading-tight">
                        <?php the_title(); ?>
                    </h1>
                </header>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="mb-8 rounded-xl overflow-hidden shadow-sm border border-slate-200 dark:border-slate-700 not-prose">
                        <?php the_post_thumbnail('large', ['class' => 'w-full h-auto object-cover max-h-[500px]']); ?>
                    </div>
                <?php endif; ?>

                <div class="content">
                    <?php the_content(); ?>
                </div>
            </article>

            <?php
        endwhile;
    endif;
    ?>
</main>

<?php get_footer(); ?>
