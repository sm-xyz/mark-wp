<?php
/**
 * Image / Media Performance Architecture
 */

if (!defined('ABSPATH')) {
    exit;
}

// Add fetchpriority="high" to the post thumbnail (Featured Image) to boost LCP
add_filter('wp_get_attachment_image_attributes', 'illusi_boost_image_lcp', 10, 3);
function illusi_boost_image_lcp($attr, $attachment, $size) {
    if (is_singular() && in_the_loop() && is_main_query()) {
        global $post;
        // If the current image is the post thumbnail, boost it.
        if (get_post_thumbnail_id($post->ID) === $attachment->ID) {
            $attr['fetchpriority'] = 'high';
            // Remove lazy loading for the LCP image
            if (isset($attr['loading'])) {
                unset($attr['loading']);
            }
        }
    }
    
    // Ensure all images have decoding async to not block main thread
    if (!isset($attr['decoding'])) {
        $attr['decoding'] = 'async';
    }
    
    return $attr;
}
