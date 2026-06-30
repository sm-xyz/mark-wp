<?php
/**
 * Admin Menu & Settings for Illu-Optimize
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Optimize_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Illu-Optimize',
            'Illu-Optimize',
            'manage_options',
            'illu-optimize',
            [$this, 'render_admin_page'],
            'dashicons-performance',
            30
        );
    }

    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_illu-optimize') {
            return;
        }
        // Enqueue some basic styles if needed, or inline
    }

    public function render_admin_page() {
        $redis_status = class_exists('Redis') ? 'Installed' : 'Not Installed';
        $redis_connection = false;
        
        if (class_exists('Redis')) {
            try {
                $redis = new Redis();
                $host = defined('WP_REDIS_HOST') ? WP_REDIS_HOST : '127.0.0.1';
                $port = defined('WP_REDIS_PORT') ? WP_REDIS_PORT : 6379;
                
                if ($redis->connect($host, $port)) {
                    if (defined('WP_REDIS_PASSWORD') && WP_REDIS_PASSWORD) {
                        $redis->auth(WP_REDIS_PASSWORD);
                    }
                    $redis->ping(); // test connection and auth
                    $redis_connection = true;
                }
            } catch (Exception $e) {
                $redis_connection = false;
            }
        }
        
        $redis_connection_text = $redis_connection ? '<span style="color: green;">Connected</span>' : '<span style="color: red;">Disconnected (Check Password/Status)</span>';
        $object_cache_status = file_exists(WP_CONTENT_DIR . '/object-cache.php') ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>';
        
        if (isset($_POST['illu_optimize_submit'])) {
            check_admin_referer('illu_optimize_settings');
            update_option('illu_ad_top', wp_unslash($_POST['illu_ad_top']));
            update_option('illu_ad_middle', wp_unslash($_POST['illu_ad_middle']));
            update_option('illu_ad_bottom', wp_unslash($_POST['illu_ad_bottom']));
            update_option('illu_ad_sticky_link', wp_unslash($_POST['illu_ad_sticky_link']));
            update_option('illu_ad_sticky_image', wp_unslash($_POST['illu_ad_sticky_image']));
            
            update_option('illu_async_worker_enabled', isset($_POST['illu_async_worker_enabled']) ? '1' : '0');
            update_option('illu_micro_cache_enabled', isset($_POST['illu_micro_cache_enabled']) ? '1' : '0');
            update_option('illu_db_optimizer_enabled', isset($_POST['illu_db_optimizer_enabled']) ? '1' : '0');
            
            // Handle Cache Schedule
            $schedule = sanitize_text_field($_POST['illu_auto_purge_schedule']);
            update_option('illu_auto_purge_schedule', $schedule);
            
            wp_clear_scheduled_hook('illu_optimize_auto_purge');
            if ($schedule !== 'never') {
                wp_schedule_event(time(), $schedule, 'illu_optimize_auto_purge');
            }
            
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        
        ?>
        <div class="wrap">
            <h1>Illu-Optimize Dashboard</h1>
            <p>Server-Level Cache & Performance Manager</p>
            
            <div style="display:flex; gap:20px; margin-top:20px; flex-wrap:wrap;">
                <!-- System Status -->
                <div style="flex:1; min-width:300px; background:#fff; padding:20px; border:1px solid #ccc; border-radius:5px;">
                    <h3>System Status</h3>
                    <ul>
                        <li><strong>PHP Redis Extension:</strong> <?php echo $redis_status; ?></li>
                        <li><strong>Redis Connection:</strong> <?php echo $redis_connection_text; ?></li>
                        <li><strong>Object Cache (Drop-in):</strong> <?php echo $object_cache_status; ?></li>
                        <li><strong>ImageMagick (WebP/AVIF):</strong> <?php echo extension_loaded('imagick') ? '<span style="color:green">Installed</span>' : '<span style="color:red">Not Installed</span>'; ?></li>
                    </ul>
                </div>
                
                <!-- Features Enabled -->
                <div style="flex:1; min-width:300px; background:#fff; padding:20px; border:1px solid #ccc; border-radius:5px;">
                    <h3>Features Running</h3>
                    <ul>
                        <?php $async_en = get_option('illu_async_worker_enabled', '1'); ?>
                        <li><span class="dashicons <?php echo $async_en ? 'dashicons-yes' : 'dashicons-no'; ?>" style="color:<?php echo $async_en ? 'green' : 'red'; ?>;"></span> Async Background Worker</li>
                        <?php $mc_en = get_option('illu_micro_cache_enabled', '0'); ?>
                        <li><span class="dashicons <?php echo $mc_en ? 'dashicons-yes' : 'dashicons-no'; ?>" style="color:<?php echo $mc_en ? 'green' : 'red'; ?>;"></span> REST API Micro-Cache</li>
                        <?php $dbopt_en = get_option('illu_db_optimizer_enabled', '0'); ?>
                        <li><span class="dashicons <?php echo $dbopt_en ? 'dashicons-yes' : 'dashicons-no'; ?>" style="color:<?php echo $dbopt_en ? 'green' : 'red'; ?>;"></span> Database Query Optimizer</li>
                        <li><span class="dashicons dashicons-yes" style="color:green;"></span> HTML & Inline CSS Minifier</li>
                        <li><span class="dashicons dashicons-yes" style="color:green;"></span> No-Conflict Assets Delivery</li>
                        <li><span class="dashicons dashicons-yes" style="color:green;"></span> Header Optimization</li>
                        <li><span class="dashicons dashicons-yes" style="color:green;"></span> Image Converter (WebP/AVIF)</li>
                        <li><span class="dashicons dashicons-yes" style="color:green;"></span> Responsive Images</li>
                        <li><span class="dashicons dashicons-yes" style="color:green;"></span> Dynamic SEO & Sitemap</li>
                        <li><span class="dashicons dashicons-yes" style="color:green;"></span> Ads Injector Manager</li>
                    </ul>
                </div>
            </div>
            
            <div style="background:#fff; padding:20px; border:1px solid #ccc; border-radius:5px; margin-top:20px;">
                <h3>Worker Mode & Performance Settings</h3>
                <form method="post" action="">
                    <?php wp_nonce_field('illu_optimize_settings'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="illu_async_worker_enabled">Async Background Worker</label><br><small>Offloads heavy tasks (like emails, webhook processing) to a background queue for instant HTTP responses.</small></th>
                            <td>
                                <input type="checkbox" name="illu_async_worker_enabled" id="illu_async_worker_enabled" value="1" <?php checked(get_option('illu_async_worker_enabled', '1'), '1'); ?>>
                                <label for="illu_async_worker_enabled">Enable Async Engine</label>
                                <?php 
                                $queue_count = count(get_option('illu_optimize_queue', []));
                                if ($queue_count > 0) {
                                    echo '<p style="color:#d63638; font-weight:bold;">' . $queue_count . ' jobs currently in queue.</p>';
                                } else {
                                    echo '<p style="color:#00a32a;">Queue is empty.</p>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="illu_micro_cache_enabled">REST API Micro-Cache</label><br><small>Bypasses deep database queries for specific REST endpoints by caching responses directly.</small></th>
                            <td>
                                <input type="checkbox" name="illu_micro_cache_enabled" id="illu_micro_cache_enabled" value="1" <?php checked(get_option('illu_micro_cache_enabled', '0'), '1'); ?>>
                                <label for="illu_micro_cache_enabled">Enable Micro-Cache</label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="illu_db_optimizer_enabled">Database Query Optimizer</label><br><small>Automatically cleans orphaned/expired transients and optimizes SQL tables during auto-purge.</small></th>
                            <td>
                                <input type="checkbox" name="illu_db_optimizer_enabled" id="illu_db_optimizer_enabled" value="1" <?php checked(get_option('illu_db_optimizer_enabled', '0'), '1'); ?>>
                                <label for="illu_db_optimizer_enabled">Enable DB Optimizer</label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="illu_auto_purge_schedule">Auto Purge All Caches</label><br><small>Automatically clear Smart Cache, Redis, and Cloudflare at this interval.</small></th>
                            <td>
                                <?php $current_schedule = get_option('illu_auto_purge_schedule', 'twicedaily'); ?>
                                <select name="illu_auto_purge_schedule" id="illu_auto_purge_schedule">
                                    <option value="hourly" <?php selected($current_schedule, 'hourly'); ?>>Every Hour</option>
                                    <option value="twicedaily" <?php selected($current_schedule, 'twicedaily'); ?>>Twice a Day (Every 12 Hours)</option>
                                    <option value="daily" <?php selected($current_schedule, 'daily'); ?>>Once a Day</option>
                                    <option value="never" <?php selected($current_schedule, 'never'); ?>>Never</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="illu_ad_top">Top Ad Script</label><br><small>Displayed below title/thumbnail</small></th>
                            <td><textarea name="illu_ad_top" id="illu_ad_top" rows="5" class="large-text code"><?php echo esc_textarea(get_option('illu_ad_top')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="illu_ad_middle">Middle Ad Script</label><br><small>Displayed in the middle of article</small></th>
                            <td><textarea name="illu_ad_middle" id="illu_ad_middle" rows="5" class="large-text code"><?php echo esc_textarea(get_option('illu_ad_middle')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="illu_ad_bottom">Bottom Ad Script</label><br><small>Displayed at the end of article</small></th>
                            <td><textarea name="illu_ad_bottom" id="illu_ad_bottom" rows="5" class="large-text code"><?php echo esc_textarea(get_option('illu_ad_bottom')); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="illu_ad_sticky_link">Sticky Right Ad Link</label><br><small>Direct link for the vertical sticky ad (e.g. https://...)</small></th>
                            <td><input type="url" name="illu_ad_sticky_link" id="illu_ad_sticky_link" class="large-text" value="<?php echo esc_attr(get_option('illu_ad_sticky_link')); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="illu_ad_sticky_image">Sticky Right Image URL</label><br><small>Custom PNG/JPG for the anchor image</small></th>
                            <td><input type="url" name="illu_ad_sticky_image" id="illu_ad_sticky_image" class="large-text" value="<?php echo esc_attr(get_option('illu_ad_sticky_image')); ?>"></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <input type="submit" name="illu_optimize_submit" id="submit" class="button button-primary" value="Save Changes">
                    </p>
                </form>
            </div>
            
            <div style="background:#fff; padding:20px; border:1px solid #ccc; border-radius:5px; margin-top:20px;">
                <h3>Redis Setup</h3>
                <p>If Redis requires a password (NOAUTH error), you need to add it to your <code>wp-config.php</code> file manually using aaPanel, because WordPress cannot write to wp-config.php securely by default.</p>
                <p>Add this line to your <code>wp-config.php</code>:</p>
                <pre style="background:#f1f1f1; padding:10px;">define( 'WP_REDIS_PASSWORD', 'your_redis_password_here' );</pre>
            </div>
        </div>
        <?php
    }
}
