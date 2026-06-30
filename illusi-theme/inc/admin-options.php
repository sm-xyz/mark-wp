<?php
/**
 * Modular Control Panel (WP Customizer)
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('customize_register', 'illusi_customize_register');
function illusi_customize_register($wp_customize) {
    // Panel: Illusi Settings
    $wp_customize->add_panel('illusi_theme_options', array(
        'title'       => __('Illusi Theme Options', 'illusi-theme'),
        'description' => __('Configure modular features to keep your site ultra-fast.', 'illusi-theme'),
        'priority'    => 160,
    ));

    // Section: Layout Components
    $wp_customize->add_section('illusi_layout_components', array(
        'title' => __('Layout Components', 'illusi-theme'),
        'panel' => 'illusi_theme_options',
    ));

    // Setting: Header Search
    $wp_customize->add_setting('illusi_enable_header_search', array(
        'default'           => false,
        'sanitize_callback' => 'illusi_sanitize_checkbox',
    ));
    $wp_customize->add_control('illusi_enable_header_search', array(
        'type'        => 'checkbox',
        'label'       => __('Enable Header Search Icon', 'illusi-theme'),
        'description' => __('Display search icon in header/navigation.', 'illusi-theme'),
        'section'     => 'illusi_layout_components',
    ));

    // Setting: Breadcrumbs
    $wp_customize->add_setting('illusi_enable_breadcrumbs', array(
        'default'           => true,
        'sanitize_callback' => 'illusi_sanitize_checkbox',
    ));
    $wp_customize->add_control('illusi_enable_breadcrumbs', array(
        'type'        => 'checkbox',
        'label'       => __('Enable Breadcrumbs', 'illusi-theme'),
        'description' => __('Shows native built-in breadcrumbs for better SEO.', 'illusi-theme'),
        'section'     => 'illusi_layout_components',
    ));

    // Setting: Blog Layout
    $wp_customize->add_setting('illusi_blog_layout', array(
        'default'           => 'list',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_control('illusi_blog_layout', array(
        'type'        => 'select',
        'label'       => __('Blog/Archive Layout', 'illusi-theme'),
        'description' => __('Choose the layout for blog, category, tag, and search result pages.', 'illusi-theme'),
        'section'     => 'illusi_layout_components',
        'choices' => array(
            'list' => 'List (Default)',
            'grid_2' => 'Grid (2 Columns)',
            'grid_3' => 'Grid (3 Columns)',
            'masonry' => 'Masonry Style',
        ),
    ));

    // Setting: Table of Contents (ToC)
    $wp_customize->add_setting('illusi_enable_toc', array(
        'default'           => false,
        'sanitize_callback' => 'illusi_sanitize_checkbox',
    ));
    $wp_customize->add_control('illusi_enable_toc', array(
        'type'        => 'checkbox',
        'label'       => __('Enable Table of Contents (ToC)', 'illusi-theme'),
        'description' => __('Auto-generates ToC inside single posts from heading H2s.', 'illusi-theme'),
        'section'     => 'illusi_layout_components',
    ));

    // Setting: Author Box
    $wp_customize->add_setting('illusi_enable_author_box', array(
        'default'           => false,
        'sanitize_callback' => 'illusi_sanitize_checkbox',
    ));
    $wp_customize->add_control('illusi_enable_author_box', array(
        'type'        => 'checkbox',
        'label'       => __('Enable Author Box', 'illusi-theme'),
        'description' => __('Display author bio at the bottom of the article.', 'illusi-theme'),
        'section'     => 'illusi_layout_components',
    ));
    
    // Setting: Footer Text
    $wp_customize->add_setting('illusi_footer_text', array(
        'default'           => 'Aesthetic & Speed by Illusi Theme.',
        'sanitize_callback' => 'wp_kses_post',
    ));
    $wp_customize->add_control('illusi_footer_text', array(
        'type'        => 'textarea',
        'label'       => __('Footer Text', 'illusi-theme'),
        'section'     => 'illusi_layout_components',
    ));

    // Setting: Related Posts Count
    $wp_customize->add_setting('illusi_related_posts_count', array(
        'default'           => 3,
        'sanitize_callback' => 'absint',
    ));
    $wp_customize->add_control('illusi_related_posts_count', array(
        'type'        => 'number',
        'label'       => __('Related Posts Count', 'illusi-theme'),
        'description' => __('Number of related posts to display at the bottom.', 'illusi-theme'),
        'section'     => 'illusi_layout_components',
        'input_attrs' => array(
            'min' => 0,
            'max' => 12,
        ),
    ));

    // Setting: Related Posts Layout
    $wp_customize->add_setting('illusi_related_posts_layout', array(
        'default'           => 'image_title',
        'sanitize_callback' => 'sanitize_key',
    ));
    $wp_customize->add_control('illusi_related_posts_layout', array(
        'type'        => 'select',
        'label'       => __('Related Posts Layout', 'illusi-theme'),
        'section'     => 'illusi_layout_components',
        'choices' => array(
            'image_title' => 'Image + Title',
            'title_only' => 'Title Only (Minimal)',
        ),
    ));

    // Section: Typography
    $wp_customize->add_section('illusi_typography', array(
        'title' => __('Typography', 'illusi-theme'),
        'panel' => 'illusi_theme_options',
        'priority' => 20,
    ));

    // Typography Preset
    $wp_customize->add_setting('illusi_font_preset', array(
        'default'           => 'system',
        'sanitize_callback' => 'sanitize_key',
    ));
    $wp_customize->add_control('illusi_font_preset', array(
        'type'        => 'select',
        'label'       => __('Font Pairing Preset', 'illusi-theme'),
        'description' => __('Select a lightweight font pairing. System fonts are fastest.', 'illusi-theme'),
        'section'     => 'illusi_typography',
        'choices' => array(
            'system' => 'System UI (Fastest, sans-serif)',
            'plus_jakarta' => 'Plus Jakarta Sans (Modern & Crisp, Recommended)',
            'serif' => 'Modern Serif (Georgia / Merriweather)',
            'playfair' => 'Playfair Display (Elegant Serif)',
            'lora' => 'Lora (Classic & Readable Serif)',
            'crimson' => 'Crimson Text (Traditional Serif)',
            'mono' => 'Tech Monospace',
        ),
    ));

    // Section: Colors
    $wp_customize->add_section('illusi_colors', array(
        'title' => __('Colors & Visuals', 'illusi-theme'),
        'panel' => 'illusi_theme_options',
    ));

    // Primary Color
    $wp_customize->add_setting('illusi_color_primary', array(
        'default'           => '#2563eb',
        'sanitize_callback' => 'sanitize_hex_color',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'illusi_color_primary', array(
        'label'    => __('Primary Color', 'illusi-theme'),
        'description' => __('Used for buttons, links, etc.', 'illusi-theme'),
        'section'  => 'illusi_colors',
    )));

    // Header Background
    $wp_customize->add_setting('illusi_color_header_bg', array(
        'default'           => '#ffffff',
        'sanitize_callback' => 'sanitize_hex_color',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'illusi_color_header_bg', array(
        'label'    => __('Header Background Color', 'illusi-theme'),
        'section'  => 'illusi_colors',
    )));

    // Footer Background
    $wp_customize->add_setting('illusi_color_footer_bg', array(
        'default'           => '#ffffff',
        'sanitize_callback' => 'sanitize_hex_color',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'illusi_color_footer_bg', array(
        'label'    => __('Footer Background Color', 'illusi-theme'),
        'section'  => 'illusi_colors',
    )));

    // Header Text
    $wp_customize->add_setting('illusi_color_header_text', array(
        'default'           => '#334155',
        'sanitize_callback' => 'sanitize_hex_color',
    ));
    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'illusi_color_header_text', array(
        'label'    => __('Header Text/Menu Color', 'illusi-theme'),
        'section'  => 'illusi_colors',
    )));
}

// Inject CSS Variables into wp_head
add_action('wp_head', 'illusi_custom_colors_css');
function illusi_custom_colors_css() {
    $primary = get_theme_mod('illusi_color_primary', '#2563eb');
    $header_bg = get_theme_mod('illusi_color_header_bg', '#ffffff');
    $footer_bg = get_theme_mod('illusi_color_footer_bg', '#ffffff');
    $header_text = get_theme_mod('illusi_color_header_text', '#334155');
    $font_preset = get_theme_mod('illusi_font_preset', 'system');
    
    $font_family = 'ui-sans-serif, system-ui, sans-serif';
    $font_family_heading = 'ui-sans-serif, system-ui, sans-serif';
    $google_font_url = '';
    
    if ($font_preset === 'serif') {
        $font_family = 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif';
        $font_family_heading = 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif';
    } elseif ($font_preset === 'mono') {
        $font_family = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace';
        $font_family_heading = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace';
    } elseif ($font_preset === 'plus_jakarta') {
        $font_family = '"Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif';
        $font_family_heading = '"Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif';
        $google_font_url = 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&display=swap';
    } elseif ($font_preset === 'playfair') {
        $font_family = '"Lora", ui-serif, Georgia, serif';
        $font_family_heading = '"Playfair Display", ui-serif, Georgia, serif';
        $google_font_url = 'https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap';
    } elseif ($font_preset === 'lora') {
        $font_family = '"Lora", ui-serif, Georgia, serif';
        $font_family_heading = '"Lora", ui-serif, Georgia, serif';
        $google_font_url = 'https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600;1,700&display=swap';
    } elseif ($font_preset === 'crimson') {
        $font_family = '"Crimson Text", ui-serif, Georgia, serif';
        $font_family_heading = '"Crimson Text", ui-serif, Georgia, serif';
        $google_font_url = 'https://fonts.googleapis.com/css2?family=Crimson+Text:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&display=swap';
    }

    if (!empty($google_font_url)) {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link href="' . esc_url($google_font_url) . '" rel="stylesheet">' . "\n";
    }

    // Simple darkening logic for hover (not perfect but works)
    echo "<style>
        :root {
            --color-primary: {$primary};
            --color-header-bg: {$header_bg};
            --color-footer-bg: {$footer_bg};
            --color-header-text: {$header_text};
            --color-header-hover: {$primary};
            --font-main: {$font_family};
            --font-heading: {$font_family_heading};
        }
        body { font-family: var(--font-main); }
        h1, h2, h3, h4, h5, h6 { font-family: var(--font-heading); }
    </style>";
}

// Checkbox sanitization callback
function illusi_sanitize_checkbox($checked) {
    return (isset($checked) && true == $checked) ? true : false;
}

// Helper functionalities

/**
 * Display native Breadcrumbs
 */
function illusi_breadcrumbs() {
    if (!get_theme_mod('illusi_enable_breadcrumbs', true)) return;
    
    if (!is_home() && !is_front_page()) {
        echo '<nav aria-label="Breadcrumb" class="mb-6 text-sm text-slate-500 dark:text-slate-400 font-medium flex flex-wrap items-center gap-2">';
        echo '<a href="' . esc_url(home_url('/')) . '" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">Home</a>';
        
        if (is_category() || is_single()) {
            echo '<svg class="w-4 h-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
            if (is_category()) {
                single_cat_title();
            } else {
                $categories = get_the_category();
                if (!empty($categories)) {
                    echo '<a href="' . esc_url(get_category_link($categories[0]->term_id)) . '" class="hover:text-blue-600 dark:hover:text-blue-400 transition-colors">' . esc_html($categories[0]->name) . '</a>';
                }
                echo '<svg class="w-4 h-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
                echo '<span class="text-slate-800 dark:text-slate-200" aria-current="page">' . get_the_title() . '</span>';
            }
        } elseif (is_page()) {
            echo '<svg class="w-4 h-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';
            echo '<span class="text-slate-800 dark:text-slate-200" aria-current="page">' . get_the_title() . '</span>';
        }
        
        echo '</nav>';
    }
}

/**
 * Simple Auto Table of Contents generator filter
 */
add_filter('the_content', 'illusi_auto_toc');
function illusi_auto_toc($content) {
    if (!is_single() || !get_theme_mod('illusi_enable_toc', false)) {
        return $content;
    }
    
    // Quick & dirty ToC generator from H2 tags
    preg_match_all('/<h2.*?>(.*?)<\/h2>/is', $content, $matches);
    if (!empty($matches[1]) && count($matches[1]) > 1) {
        $toc = '<div class="my-8 p-6 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl">';
        $toc .= '<div class="text-lg font-bold mb-4 text-slate-800 dark:text-slate-100">Daftar Isi</div>';
        $toc .= '<ul class="space-y-2 list-none p-0 m-0">';
        
        foreach ($matches[1] as $index => $heading_text) {
            $slug = sanitize_title($heading_text);
            $clean_heading = strip_tags($heading_text);
            
            // Add ID to original content's H2
            $content = preg_replace('/(<h2.*?>)(' . preg_quote($heading_text, '/') . ')(<\/h2>)/is', '$1<a id="' . $slug . '" class="scroll-mt-24"></a>$2$3', $content, 1);
            
            $toc .= '<li><a href="#' . $slug . '" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">' . $clean_heading . '</a></li>';
        }
        $toc .= '</ul></div>';
        
        // Insert after first paragraph or at the beginning
        $content = $toc . $content;
    }
    
    return $content;
}
