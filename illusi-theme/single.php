<?php
/**
 * Single post template
 */

get_header(); ?>

<main class="mx-auto max-w-3xl px-4 py-8 md:py-12" id="main-content">
    <?php
    if (have_posts()) :
        while (have_posts()) : the_post();
            
            // Breadcrumbs Hook
            illusi_breadcrumbs();
            ?>
            <article class="prose prose-slate dark:prose-invert max-w-none prose-a:text-primary hover:prose-a:text-primary-hover dark:prose-a:text-blue-400 prose-headings:font-bold">
                <header class="mb-8 not-prose">
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold tracking-tight text-slate-900 dark:text-white mb-4 leading-tight">
                        <?php the_title(); ?>
                    </h1>
                    <div class="flex items-center text-sm text-slate-500 dark:text-slate-400 gap-3">
                        <time datetime="<?php echo get_the_date('c'); ?>"><?php echo get_the_date(); ?></time>
                        <span>&middot;</span>
                        <span class="font-medium text-slate-700 dark:text-slate-300"><?php the_author(); ?></span>
                    </div>
                </header>

                <?php if (has_post_thumbnail()) : ?>
                    <div class="mb-8 rounded-xl overflow-hidden shadow-sm border border-slate-200 dark:border-slate-700 not-prose">
                        <?php the_post_thumbnail('large', ['class' => 'w-full h-auto object-cover max-h-[500px]']); ?>
                    </div>
                <?php endif; ?>

                <?php do_action('illusi_before_content'); ?>

                <div class="content">
                    <?php the_content(); ?>
                </div>
            </article>

            <?php
            // Author Box feature
            if (get_theme_mod('illusi_enable_author_box', false)) :
            ?>
                <div class="mt-12 p-6 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row gap-6 items-center sm:items-start">
                    <div class="flex-shrink-0">
                        <?php echo get_avatar(get_the_author_meta('ID'), 80, '', '', ['class' => 'rounded-full scale-100 transition-transform duration-300 border-2 border-white dark:border-slate-800 shadow-sm']); ?>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold text-slate-900 dark:text-white mb-1">Ditulis oleh <?php the_author(); ?></h4>
                        <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                            <?php echo get_the_author_meta('description') ? get_the_author_meta('description') : 'Penulis belum menambahkan biodata.'; ?>
                        </p>
                    </div>
                </div>
            <?php
            endif;
            // End Author Box
            
            // Related Posts Feature
            $related_count = get_theme_mod('illusi_related_posts_count', 3);
            if ($related_count > 0) {
                $categories = get_the_category();
                if ($categories) {
                    $category_ids = [];
                    foreach($categories as $individual_category) $category_ids[] = $individual_category->term_id;
                    
                    $args = [
                        'category__in' => $category_ids,
                        'post__not_in' => [$post->ID],
                        'posts_per_page'=> $related_count,
                        'ignore_sticky_posts'=> 1
                    ];
                    
                    $related_query = new WP_Query($args);
                    $related_layout = get_theme_mod('illusi_related_posts_layout', 'image_title');
                    
                    if ($related_query->have_posts()) {
                        ?>
                        <section class="mt-12 pt-8 border-t border-slate-200 dark:border-slate-700">
                            <h3 class="text-2xl font-bold text-slate-900 dark:text-white mb-6">Artikel Terkait</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                                <?php
                                while ($related_query->have_posts()) {
                                    $related_query->the_post();
                                    ?>
                                    <a href="<?php the_permalink(); ?>" class="group block <?php echo $related_layout === 'title_only' ? 'p-4 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors' : ''; ?>">
                                        <?php if ($related_layout === 'image_title' && has_post_thumbnail()) : ?>
                                            <div class="aspect-video w-full overflow-hidden rounded-lg mb-3 shadow-sm border border-slate-200 dark:border-slate-700">
                                                <?php the_post_thumbnail('medium', ['class' => 'w-full h-full object-cover group-hover:scale-105 transition-transform duration-300']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <h4 class="font-semibold text-slate-800 dark:text-slate-200 group-hover:text-primary transition-colors line-clamp-2"><?php the_title(); ?></h4>
                                    </a>
                                    <?php
                                }
                                ?>
                            </div>
                        </section>
                        <?php
                    }
                    wp_reset_postdata();
                }
            }
            // End Related Posts

        endwhile;
    endif;
    ?>
</main>

<?php get_footer(); ?>
