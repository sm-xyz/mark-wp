<?php
/**
 * Meta Tag & OpenGraph Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_SEO_Meta {
    
    public function __construct() {
        // Remove theme's default SEO if it exists to avoid conflicts
        add_action('init', [$this, 'remove_theme_seo']);
        
        // Add Meta Boxes
        add_action('add_meta_boxes', [$this, 'add_seo_meta_box']);
        add_action('save_post', [$this, 'save_seo_meta']);
        
        // Output Meta Tags
        add_action('wp_head', [$this, 'render_meta_tags'], 1);
        add_action('wp_footer', [$this, 'render_json_ld'], 100);
        
        // Clear sitemap cache on post save
        add_action('save_post', [$this, 'clear_sitemap_cache']);
    }

    public function remove_theme_seo() {
        remove_action('wp_head', 'illusi_seo_meta_tags', 1);
        remove_action('wp_footer', 'illusi_seo_json_ld', 100);
    }

    public function clear_sitemap_cache() {
        wp_cache_delete('illu_sitemap_xml', 'illu_seo');
    }

    public function add_seo_meta_box() {
        $screens = ['post', 'page'];
        foreach ($screens as $screen) {
            add_meta_box(
                'illu_seo_meta_box',
                'Illu-SEO Settings',
                [$this, 'render_meta_box_html'],
                $screen,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box_html($post) {
        wp_nonce_field('illu_seo_save_meta', 'illu_seo_meta_nonce');
        
        $title = get_post_meta($post->ID, '_illu_seo_title', true);
        $desc = get_post_meta($post->ID, '_illu_seo_desc', true);
        $schema_type = get_post_meta($post->ID, '_illu_seo_schema_type', true);
        if (!$schema_type) $schema_type = 'Article';
        $noindex = get_post_meta($post->ID, '_illu_seo_noindex', true);
        
        ?>
        <div style="margin-top: 10px;">
            <label for="illu_seo_title" style="display:block;font-weight:bold;margin-bottom:5px;">SEO Title</label>
            <input type="text" id="illu_seo_title" name="illu_seo_title" value="<?php echo esc_attr($title); ?>" style="width:100%;" placeholder="Leave empty to use post title" />
        </div>
        <div style="margin-top: 10px;">
            <label for="illu_seo_desc" style="display:block;font-weight:bold;margin-bottom:5px;">SEO Meta Description</label>
            <textarea id="illu_seo_desc" name="illu_seo_desc" style="width:100%;height:80px;" placeholder="Leave empty to use auto-generated excerpt"><?php echo esc_textarea($desc); ?></textarea>
        </div>
        <div style="margin-top: 10px;">
            <label for="illu_seo_schema_type" style="display:block;font-weight:bold;margin-bottom:5px;">Schema (AEO/SEO Structure)</label>
            <select id="illu_seo_schema_type" name="illu_seo_schema_type" style="width:100%;">
                <option value="Article" <?php selected($schema_type, 'Article'); ?>>Article (Default)</option>
                <option value="BlogPosting" <?php selected($schema_type, 'BlogPosting'); ?>>Blog Posting</option>
                <option value="NewsArticle" <?php selected($schema_type, 'NewsArticle'); ?>>News Article</option>
                <option value="JobPosting" <?php selected($schema_type, 'JobPosting'); ?>>Job Posting</option>
                <option value="Review" <?php selected($schema_type, 'Review'); ?>>Review</option>
                <option value="Recipe" <?php selected($schema_type, 'Recipe'); ?>>Recipe</option>
            </select>
            <p style="font-size:12px; color:#666; margin-top:4px;">Helps AI engines (AEO) and Search Engines (SEO) understand the content structure.</p>
        </div>
        <div style="margin-top: 10px;">
            <label>
                <input type="checkbox" name="illu_seo_noindex" value="yes" <?php checked($noindex, 'yes'); ?> />
                Prevent search engines from indexing this page (NoIndex)
            </label>
        </div>
        <?php
    }

    public function save_seo_meta($post_id) {
        if (!isset($_POST['illu_seo_meta_nonce']) || !wp_verify_nonce($_POST['illu_seo_meta_nonce'], 'illu_seo_save_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['illu_seo_title'])) {
            update_post_meta($post_id, '_illu_seo_title', sanitize_text_field($_POST['illu_seo_title']));
        }
        if (isset($_POST['illu_seo_desc'])) {
            update_post_meta($post_id, '_illu_seo_desc', sanitize_textarea_field($_POST['illu_seo_desc']));
        }
        if (isset($_POST['illu_seo_schema_type'])) {
            update_post_meta($post_id, '_illu_seo_schema_type', sanitize_text_field($_POST['illu_seo_schema_type']));
        }
        
        $noindex = isset($_POST['illu_seo_noindex']) ? 'yes' : 'no';
        update_post_meta($post_id, '_illu_seo_noindex', $noindex);
    }

    public function render_meta_tags() {
        $site_name = get_bloginfo('name');
        
        if (is_singular()) {
            global $post;
            
            $custom_title = get_post_meta($post->ID, '_illu_seo_title', true);
            $title = $custom_title ? $custom_title : get_the_title();
            
            $custom_desc = get_post_meta($post->ID, '_illu_seo_desc', true);
            if ($custom_desc) {
                $excerpt = $custom_desc;
            } else {
                $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words($post->post_content, 25, '...');
            }
            
            $excerpt = strip_tags(strip_shortcodes($excerpt));
            $excerpt = esc_attr($excerpt);
            
            $url = get_permalink();
            
            // Featured image
            $image = has_post_thumbnail() ? get_the_post_thumbnail_url($post->ID, 'large') : '';
            
            $noindex = get_post_meta($post->ID, '_illu_seo_noindex', true);
            if ($noindex === 'yes') {
                echo '<meta name="robots" content="noindex, nofollow" />' . "\n";
            } else {
                echo '<meta name="robots" content="index, follow" />' . "\n";
            }
            
            echo '<title>' . esc_html($title . ' - ' . $site_name) . '</title>' . "\n";
            echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
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
            
            echo '<title>' . esc_html($title . ' - ' . $desc) . '</title>' . "\n";
            echo '<link rel="canonical" href="' . esc_url($url) . '" />' . "\n";
            echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($title) . '" />' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($desc) . '" />' . "\n";
            echo '<meta property="og:type" content="website" />' . "\n";
            echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        }
    }

    public function render_json_ld() {
        if (is_singular('post')) {
            global $post;
            
            $image = has_post_thumbnail() ? get_the_post_thumbnail_url($post->ID, 'large') : '';
            $author_name = get_the_author_meta('display_name', $post->post_author);
            $schema_type = get_post_meta($post->ID, '_illu_seo_schema_type', true);
            if (!$schema_type) $schema_type = 'BlogPosting'; // Fallback to BlogPosting for posts
            
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => $schema_type,
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
}
