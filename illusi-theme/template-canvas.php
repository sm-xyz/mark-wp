<?php
/**
 * Template Name: AI Canvas (Blank Edge-to-Edge)
 * Template Post Type: post, page
 * 
 * Template ini didesain khusus untuk menerima output HTML utuh dari AI (seperti Gemini Canvas).
 * Template akan mempertahankan <head> untuk keperluan SEO (Schema, Canonical, Meta Tags),
 * tapi menghilangkan constrain container, prose, dan padding bawaan theme sehingga 
 * HTML Canvas bebas menguasai layout dari ujung ke ujung.
 */

get_header(); ?>

<main id="main-content" class="w-full">
    <?php
    if (have_posts()) :
        while (have_posts()) : the_post();
            
            // Render the raw content without the 'prose' (typography) constraints
            // Memungkinkan class Tailwind bawaan HTML dari AI berfungsi tanpa konflik
            ?>
            <div class="ai-canvas-wrapper w-full">
                <?php 
                // Render pure HTML tanpa filter formatting WP bawaan (seperti wpautop, wptexturize)
                // karena filter tersebut akan merusak script JS, inline style, dan tag <br> Tailwind
                $content = get_the_content();
                // Tetap jalankan shortcode jika suatu saat user butuh embed form WP
                $content = do_shortcode($content);
                echo $content;
                ?>
            </div>
            <?php
            
        endwhile;
    endif;
    ?>
</main>

<?php get_footer(); ?>
