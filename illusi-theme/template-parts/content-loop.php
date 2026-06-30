<?php
$layout = get_theme_mod('illusi_blog_layout', 'list');

$container_classes = 'space-y-6';
if ($layout === 'grid_2') {
    $container_classes = 'grid grid-cols-1 md:grid-cols-2 gap-6';
} elseif ($layout === 'grid_3') {
    $container_classes = 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6';
} elseif ($layout === 'masonry') {
    $container_classes = 'columns-1 md:columns-2 lg:columns-3 gap-6 space-y-6';
}

if (have_posts()) :
    echo '<div class="' . esc_attr($container_classes) . '">';
    while (have_posts()) : the_post();
        
        $article_classes = 'p-6 bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 transition-all hover:shadow-md hover:border-primary dark:hover:border-primary flex flex-col h-full overflow-hidden';
        if ($layout === 'masonry') {
            $article_classes .= ' break-inside-avoid mb-6';
        }
        
        ?>
        <article class="<?php echo esc_attr($article_classes); ?>">
            <?php if (has_post_thumbnail()) : ?>
                <a href="<?php the_permalink(); ?>" class="block -mt-6 -mx-6 mb-4 overflow-hidden relative pb-[56%]">
                    <?php the_post_thumbnail('medium_large', ['class' => 'absolute inset-0 w-full h-full object-cover transform hover:scale-105 transition-transform duration-500']); ?>
                </a>
            <?php endif; ?>
            <h2 class="text-xl font-semibold mb-2 leading-tight">
                <a href="<?php the_permalink(); ?>" class="text-slate-900 dark:text-slate-100 hover:text-primary dark:hover:text-primary-hover transition-colors line-clamp-3">
                    <?php the_title(); ?>
                </a>
            </h2>
            <div class="text-xs text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-2">
                <span><?php echo get_the_date(); ?></span>
                <span>&middot;</span>
                <span class="truncate"><?php the_author(); ?></span>
            </div>
            <div class="text-slate-600 dark:text-slate-300 leading-relaxed text-sm flex-grow line-clamp-3">
                <?php the_excerpt(); ?>
            </div>
            <div class="mt-4 pt-4 border-t border-slate-100 dark:border-slate-700">
                <a href="<?php the_permalink(); ?>" class="text-primary dark:text-primary-hover text-sm font-medium hover:underline inline-flex items-center">
                    Read more
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </a>
            </div>
        </article>
        <?php
    endwhile;
    echo '</div>';
    
    echo '<div class="mt-10">';
    the_posts_navigation(array(
        'prev_text' => '&larr; Older posts',
        'next_text' => 'Newer posts &rarr;',
    ));
    echo '</div>';
else :
    echo '<p class="text-slate-500 dark:text-slate-400">No posts found.</p>';
endif;
?>
