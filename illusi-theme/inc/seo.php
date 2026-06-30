<?php
/**
 * Built-In SEO & Schema Architecture
 * Removes the need for Yoast or RankMath for basic implementations
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure the title tag is supported (already in functions.php, but good measure)
add_action('after_setup_theme', function() {
    add_theme_support('title-tag');
});

// Add native Meta and Open Graph tags
add_action('wp_head', 'illusi_seo_meta_tags', 1);
function illusi_seo_meta_tags() {
    if (is_singular()) {
        global $post;
        
        $title = get_the_title();
        $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words($post->post_content, 25, '...');
        $excerpt = strip_tags(strip_shortcodes($excerpt));
        $excerpt = esc_attr($excerpt);
        $url = get_permalink();
        $site_name = get_bloginfo('name');
        
        // Featured image
        $image = has_post_thumbnail() ? get_the_post_thumbnail_url($post->ID, 'large') : '';

        // Canonical
        echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
        
        // Meta Description
        echo '<meta name="description" content="' . $excerpt . '" />' . "\n";
        
        // Open Graph
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . $excerpt . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        if ($image) {
            echo '<meta property="og:image" content="' . esc_url($image) . '" />' . "\n";
        }
        
        // Twitter Cards
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . $excerpt . '" />' . "\n";
        if ($image) {
            echo '<meta name="twitter:image" content="' . esc_url($image) . '" />' . "\n";
        }
        
    } elseif (is_front_page() || is_home()) {
        $title = get_bloginfo('name');
        $desc = get_bloginfo('description');
        $url = home_url('/');
        
        echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
        echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
        echo '<meta property="og:type" content="website" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
    }
}

// Generate JSON-LD Schema
add_action('wp_footer', 'illusi_seo_json_ld', 100);
function illusi_seo_json_ld() {
    if (is_singular('post')) {
        global $post;
        
        $image = has_post_thumbnail() ? get_the_post_thumbnail_url($post->ID, 'large') : '';
        $author_name = get_the_author_meta('display_name', $post->post_author);
        
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => get_permalink()
            ],
            'headline' => get_the_title(),
            'datePublished' => get_the_date('c'),
            'dateModified' => get_the_modified_date('c'),
            'author' => [
                '@type' => 'Person',
                'name' => $author_name
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url() ?: ''
                ]
            ]
        ];
        
        if ($image) {
            $schema['image'] = [$image];
        }
        
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}
