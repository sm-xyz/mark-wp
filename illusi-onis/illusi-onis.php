<?php
/**
 * Plugin Name: Illusionis
 * Plugin URI: https://solusimarketing.xyz
 * Description: Create custom page/post with AI HTML (Tailwind) seketika. Memiliki advanced schema extractor dan UI langsung dari WP-Admin.
 * Version: 2.0.0
 * Author: Solusi Marketing
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'illusionis_admin_menu');
function illusionis_admin_menu() {
    add_menu_page(
        'Illusionis Importer',
        'Illusionis',
        'manage_options',
        'illusionis-importer',
        'illusionis_admin_page',
        'dashicons-art', // Icon for the menu
        30 // Position
    );
}

function illusionis_admin_page() {
    // Handle Settings Save (Gemini API & Regenerate Key)
    if (isset($_POST['illusi_action']) && current_user_can('manage_options')) {
        check_admin_referer('illusionis_settings_action', 'illusionis_settings_nonce');
        
        if ($_POST['illusi_action'] === 'save_gemini') {
            update_option('illusi_gemini_api_key', sanitize_text_field($_POST['gemini_api_key']));
            echo '<div class="notice notice-success is-dismissible"><p>Gemini API Key berhasil disimpan!</p></div>';
        }
        
        if ($_POST['illusi_action'] === 'regenerate_key') {
            $new_key = wp_generate_password(32, false);
            update_option('illusi_canvas_secret_key', $new_key);
            echo '<div class="notice notice-success is-dismissible"><p>Secret Key berhasil diperbarui!</p></div>';
        }
    }

    $secret_key = get_option('illusi_canvas_secret_key');
    if (empty($secret_key)) {
        $secret_key = wp_generate_password(32, false);
        update_option('illusi_canvas_secret_key', $secret_key);
    }
    
    $gemini_api_key = get_option('illusi_gemini_api_key', '');

    // UI Importer Admin
    ?>
    <!-- Tailwind untuk UI Dashboard -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            corePlugins: {
                preflight: false,
            },
            theme: {
                extend: {
                    colors: { primary: '#2563eb' }
                }
            }
        }
    </script>

    <div class="wrap" id="illusionis-admin-app" x-data="illusionisApp()">
        <div class="max-w-5xl mx-auto mt-6 bg-slate-50 p-6 rounded-2xl shadow-sm text-slate-800" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
            
            <!-- Header -->
            <header class="mb-8 text-center">
                <h1 class="text-3xl font-black tracking-tight text-slate-900 mb-2">Illusionis <span class="text-primary">V2</span></h1>
                <p class="text-slate-500 font-medium">Create Custom Page / Post with AI Canvas in WordPress</p>
            </header>

            <!-- Tabs -->
            <div class="flex flex-wrap justify-center gap-4 mb-8">
                <button @click="tab = 'publish'" :class="tab === 'publish' ? 'bg-primary text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'" class="px-6 py-2.5 rounded-full font-bold transition-all flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-rocket"></i> Publish Content
                </button>
                <button @click="tab = 'settings'" :class="tab === 'settings' ? 'bg-slate-800 text-white shadow-md' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'" class="px-6 py-2.5 rounded-full font-bold transition-all flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-gear"></i> Settings & API
                </button>
            </div>

            <!-- Tab: Settings -->
            <div x-show="tab === 'settings'" class="bg-white rounded-xl p-8 shadow-sm border border-slate-200" style="display: none;">
                <h2 class="text-2xl font-bold mb-6 text-slate-900"><i class="fa-solid fa-plug text-slate-400 mr-2"></i> Settings & Remote API</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Gemini API Key -->
                    <div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
                        <h3 class="font-bold text-lg mb-4 text-slate-800">Gemini API Configuration</h3>
                        <form method="post" action="">
                            <?php wp_nonce_field('illusionis_settings_action', 'illusionis_settings_nonce'); ?>
                            <input type="hidden" name="illusi_action" value="save_gemini">
                            <div class="mb-4">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Gemini API Key</label>
                                <input type="password" name="gemini_api_key" value="<?php echo esc_attr($gemini_api_key); ?>" placeholder="AIzaSy..." class="w-full bg-white border border-slate-300 px-4 py-2 rounded-lg focus:ring-2 focus:ring-primary focus:outline-none">
                                <p class="text-xs text-slate-500 mt-2">Disimpan di database untuk integrasi AI di masa depan.</p>
                            </div>
                            <button type="submit" class="bg-primary hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition-all cursor-pointer">
                                Simpan Gemini Key
                            </button>
                        </form>
                    </div>

                    <!-- Remote Publish Config -->
                    <div class="bg-slate-50 p-6 rounded-xl border border-slate-200">
                        <h3 class="font-bold text-lg mb-4 text-slate-800">Remote API Configuration</h3>
                        <p class="text-sm text-slate-600 mb-4">Gunakan kredensial ini jika Anda ingin melakukan remote publish dari luar WordPress (misalnya dari aplikasi Canvas mandiri).</p>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">API Endpoint</label>
                                <code class="block bg-white border border-slate-300 px-3 py-2 rounded text-sm"><?php echo esc_url(rest_url('illusionis/v1/publish')); ?></code>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-1">Secret Key</label>
                                <code class="block bg-white border border-slate-300 px-3 py-2 rounded text-sm"><?php echo esc_html($secret_key); ?></code>
                            </div>
                            
                            <form method="post" action="" onsubmit="return confirm('Anda yakin? Key lama tidak akan bisa digunakan lagi.');">
                                <?php wp_nonce_field('illusionis_settings_action', 'illusionis_settings_nonce'); ?>
                                <input type="hidden" name="illusi_action" value="regenerate_key">
                                <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white font-bold py-2 px-4 rounded-lg transition-all text-sm mt-2 cursor-pointer">
                                    <i class="fa-solid fa-rotate-right mr-1"></i> Regenerate Secret Key
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Publish -->
            <div x-show="tab === 'publish'" class="space-y-6">
                <div class="bg-white rounded-xl p-6 sm:p-8 shadow-sm border border-slate-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Judul (Title)</label>
                            <input type="text" x-model="post.title" placeholder="Contoh: Landing Page Produk Baju" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Custom Slug (Opsional)</label>
                            <input type="text" x-model="post.slug" placeholder="produk-baju" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:outline-none transition-all">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Publish Sebagai</label>
                        <select x-model="post.post_type" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:outline-none transition-all">
                            <option value="page">Page (Ditetapkan menjadi Template Edge-to-Edge otomatis)</option>
                            <option value="post">Post (Format artikel standar)</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Schema (AEO/SEO Structure)</label>
                        <select x-model="post.schema_type" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:outline-none transition-all">
                            <optgroup label="General & Content">
                                <option value="Article">Article (Default)</option>
                                <option value="BlogPosting">Blog Posting</option>
                                <option value="NewsArticle">News Article</option>
                                <option value="WebPage">WebPage</option>
                                <option value="AboutPage">AboutPage</option>
                                <option value="ProfilePage">ProfilePage</option>
                                <option value="CollectionPage">CollectionPage</option>
                                <option value="FAQPage">FAQPage</option>
                                <option value="QAPage">QAPage</option>
                                <option value="Question">Question</option>
                                <option value="Answer">Answer</option>
                                <option value="HowTo">HowTo</option>
                                <option value="Review">Review</option>
                                <option value="Recipe">Recipe</option>
                                <option value="TechArticle">TechArticle</option>
                                <option value="ScholarlyArticle">ScholarlyArticle</option>
                            </optgroup>
                            <optgroup label="Business & E-Commerce">
                                <option value="Organization">Organization</option>
                                <option value="LocalBusiness">LocalBusiness</option>
                                <option value="Product">Product</option>
                                <option value="Offer">Offer</option>
                                <option value="Service">Service</option>
                                <option value="AggregateRating">AggregateRating</option>
                                <option value="JobPosting">JobPosting</option>
                            </optgroup>
                            <optgroup label="Structure & Media">
                                <option value="WebSite">WebSite</option>
                                <option value="BreadcrumbList">BreadcrumbList</option>
                                <option value="ItemList">ItemList</option>
                                <option value="Person">Person</option>
                                <option value="ImageObject">ImageObject</option>
                                <option value="VideoObject">VideoObject</option>
                                <option value="Event">Event</option>
                                <option value="DefinedTerm">DefinedTerm</option>
                                <option value="DefinedTermSet">DefinedTermSet</option>
                                <option value="Speakable">Speakable</option>
                                <option value="ClaimReview">ClaimReview</option>
                                <option value="Dataset">Dataset</option>
                                <option value="Report">Report</option>
                            </optgroup>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">SEO Title & Meta Description akan diekstrak otomatis dari Raw HTML.</p>
                    </div>

                    <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-6" x-show="post.post_type === 'post'" style="display: none;">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Category (Kategori)</label>
                            <input type="text" x-model="post.category" placeholder="Contoh: Teknologi, Tutorial" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Tags (Pisahkan dengan koma)</label>
                            <input type="text" x-model="post.tags" placeholder="Contoh: ai, web design, seo" class="w-full bg-slate-50 border border-slate-200 px-4 py-3 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:outline-none transition-all">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-700 mb-2 flex items-center">
                            Raw HTML / Full Canvas Script
                            <span class="text-xs font-normal text-slate-500 ml-2">(Sistem akan otomatis mengekstrak Body & Scripts)</span>
                        </label>
                        <textarea x-model="post.html_content" rows="12" placeholder="<!DOCTYPE html>... Paste seluruh output HTML AI disini" class="w-full bg-slate-900 text-emerald-400 font-mono text-sm border-2 border-slate-800 p-4 rounded-xl focus:ring-2 focus:ring-primary focus:border-primary focus:outline-none transition-all"></textarea>
                    </div>

                    <button @click="publishContent" :disabled="loading" class="w-full bg-primary hover:bg-blue-700 text-white font-black text-lg py-4 px-6 rounded-2xl flex items-center justify-center gap-3 shadow-[0_8px_20px_rgba(37,99,235,0.3)] disabled:opacity-70 disabled:cursor-not-allowed transition-all cursor-pointer">
                        <span x-show="!loading"><i class="fa-solid fa-rocket"></i> PUBLISH NOW</span>
                        <span x-show="loading" style="display:none;"><i class="fa-solid fa-circle-notch fa-spin"></i> Processing & Publishing...</span>
                    </button>

                    <!-- Result Notice -->
                    <div x-show="result.show" class="mt-8 p-6 rounded-2xl border transition-all" :class="result.isError ? 'bg-red-50 border-red-200' : 'bg-emerald-50 border-emerald-200'" style="display: none;">
                        <div class="flex items-start gap-4">
                            <div class="text-2xl" :class="result.isError ? 'text-red-500' : 'text-emerald-500'">
                                <i class="fa-solid" :class="result.isError ? 'fa-circle-xmark' : 'fa-circle-check'"></i>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-bold text-slate-900 text-lg mb-1" x-text="result.isError ? 'Publish Gagal!' : 'Publish Berhasil!'"></h4>
                                <p class="text-sm text-slate-600 mb-4" x-text="result.message"></p>
                                
                                <template x-if="!result.isError && result.url">
                                    <div class="flex gap-3">
                                        <a :href="result.url" target="_blank" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-bold transition-all no-underline inline-flex items-center"><i class="fa-solid fa-eye mr-2"></i> Buka Halaman</a>
                                        <a :href="result.edit_url" target="_blank" class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm font-bold transition-all no-underline inline-flex items-center"><i class="fa-solid fa-pen mr-2"></i> Edit Mode</a>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            
        </div>
    </div>
    
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('illusionisApp', () => ({
                tab: 'publish',
                post: {
                    title: '',
                    slug: '',
                    post_type: 'page',
                    schema_type: 'Article',
                    category: '',
                    tags: '',
                    html_content: ''
                },
                loading: false,
                result: {
                    show: false,
                    isError: false,
                    message: '',
                    url: '',
                    edit_url: ''
                },
                
                async publishContent() {
                    if (!this.post.title || !this.post.html_content) {
                        alert('Judul dan Kode HTML tidak boleh kosong!');
                        return;
                    }

                    this.loading = true;
                    this.result.show = false;

                    try {
                        let cleanHtml = this.post.html_content;
                        
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(this.post.html_content, 'text/html');
                        
                        let seoTitle = '';
                        let seoDesc = '';

                        if (doc.title) {
                            seoTitle = doc.title;
                        }
                        const metaDesc = doc.querySelector('meta[name="description"]');
                        if (metaDesc) {
                            seoDesc = metaDesc.getAttribute('content') || '';
                        }
                        
                        if (doc.body && doc.body.innerHTML.trim() !== '') {
                            let headElementsHtml = '';
                            if (doc.head) {
                                const elements = doc.head.querySelectorAll('style, script, link[rel="stylesheet"]');
                                elements.forEach(el => {
                                    headElementsHtml += el.outerHTML + '\n';
                                });
                            }
                            
                            const wrapper = doc.createElement('div');
                            Array.from(doc.body.attributes).forEach(attr => {
                                wrapper.setAttribute(attr.name, attr.value);
                            });
                            wrapper.innerHTML = doc.body.innerHTML;
                            
                            cleanHtml = headElementsHtml + wrapper.outerHTML;
                        }

                        const endpoint = '<?php echo esc_url(rest_url('illusionis/v1/publish')); ?>';
                        
                        const response = await fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
                            },
                            body: JSON.stringify({
                                title: this.post.title,
                                slug: this.post.slug,
                                post_type: this.post.post_type,
                                schema_type: this.post.schema_type,
                                category: this.post.category,
                                tags: this.post.tags,
                                seo_title: seoTitle,
                                seo_desc: seoDesc,
                                html_content: cleanHtml
                            })
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Terjadi kesalahan pada koneksi API WP.');
                        }

                        this.result.isError = false;
                        this.result.message = 'Konten berhasil diamankan dan dipublikasikan langsung ke WordPress!';
                        this.result.url = data.url;
                        this.result.edit_url = data.edit_url;
                        this.result.show = true;
                        
                        this.post.title = '';
                        this.post.slug = '';
                        this.post.html_content = '';

                    } catch (error) {
                        this.result.isError = true;
                        this.result.message = error.message;
                        this.result.show = true;
                    } finally {
                        this.loading = false;
                    }
                }
            }));
        });
    </script>
    <?php
}

// REST API for Illusionis App (Local & Remote)
add_action('rest_api_init', function () {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Illusi-Key');
        return $value;
    });

    register_rest_route('illusionis/v1', '/publish', array(
        'methods' => 'POST, OPTIONS',
        'callback' => 'illusionis_api_publish',
        'permission_callback' => '__return_true'
    ));
});

function illusionis_api_publish($request) {
    if ($request->get_method() === 'OPTIONS') {
        return rest_ensure_response(array('status' => 'OK'));
    }

    $params = $request->get_json_params() ?: $request->get_params(); 

    // Check Auth: Logged in (Nonce handled by WP implicitly if we set X-WP-Nonce, but we check user) OR Remote Secret Key
    $is_authorized = false;
    
    // 1. Check if logged in admin
    if (is_user_logged_in() && current_user_can('manage_options')) {
        $is_authorized = true;
    } 
    // 2. Check Remote Key
    else {
        $provided_key = isset($_SERVER['HTTP_X_ILLUSI_KEY']) ? sanitize_text_field($_SERVER['HTTP_X_ILLUSI_KEY']) : '';
        if (empty($provided_key) && isset($params['secret_key'])) {
            $provided_key = sanitize_text_field($params['secret_key']);
        }
        
        $secret_key = get_option('illusi_canvas_secret_key');
        if (!empty($provided_key) && $provided_key === $secret_key) {
            $is_authorized = true;
        }
    }

    if (!$is_authorized) {
        return new WP_Error('rest_forbidden', 'API Key Invalid or Not Authorized.', array('status' => 401));
    }
    
    $title = sanitize_text_field($params['title'] ?? 'Untitled Page');
    $slug = sanitize_title($params['slug'] ?? '');
    $post_type = isset($params['post_type']) && $params['post_type'] === 'post' ? 'post' : 'page';
    $raw_html = $params['html_content'] ?? '';
    $schema_type = sanitize_text_field($params['schema_type'] ?? '');
    $seo_title = sanitize_text_field($params['seo_title'] ?? '');
    $seo_desc = sanitize_textarea_field($params['seo_desc'] ?? '');
    
    if (empty($raw_html)) {
         return new WP_Error('rest_invalid_param', 'HTML Content is required.', array('status' => 400));
    }

    kses_remove_filters();
    
    $post_data = array(
        'post_title'    => wp_slash($title),
        'post_name'     => wp_slash($slug),
        'post_content'  => wp_slash($raw_html),
        'post_status'   => 'publish',
        'post_type'     => wp_slash($post_type),
    );
    
    $post_id = wp_insert_post($post_data);
    kses_init_filters();
    
    if (is_wp_error($post_id) || $post_id == 0) {
        return new WP_Error('insert_failed', 'Failed to publish post/page.', array('status' => 500));
    }
    
    // Save SEO Meta if provided
    if (!empty($seo_title)) update_post_meta($post_id, '_illu_seo_title', $seo_title);
    if (!empty($seo_desc)) update_post_meta($post_id, '_illu_seo_desc', $seo_desc);
    if (!empty($schema_type)) update_post_meta($post_id, '_illu_seo_schema_type', $schema_type);
    
    // Handle Category
    $category_name = sanitize_text_field($params['category'] ?? '');
    if ($post_type === 'post' && !empty($category_name)) {
        if (!term_exists($category_name, 'category')) {
            $cat_id = wp_insert_term($category_name, 'category');
            if (!is_wp_error($cat_id)) {
                $cat_id = $cat_id['term_id'];
            }
        } else {
            $cat_term = get_term_by('name', $category_name, 'category');
            if ($cat_term) {
                $cat_id = $cat_term->term_id;
            }
        }
        if (isset($cat_id) && !is_wp_error($cat_id)) {
            wp_set_post_categories($post_id, [$cat_id]);
        }
    }
    
    // Handle Tags
    $tags_input = sanitize_text_field($params['tags'] ?? '');
    if ($post_type === 'post' && !empty($tags_input)) {
        wp_set_post_tags($post_id, $tags_input, true);
    }
    
    if ($post_type === 'page') {
        update_post_meta($post_id, '_wp_page_template', 'template-canvas.php');
    }
    
    return array(
        'success' => true,
        'post_id' => $post_id,
        'url' => get_permalink($post_id),
        'edit_url' => get_edit_post_link($post_id, 'raw')
    );
}
