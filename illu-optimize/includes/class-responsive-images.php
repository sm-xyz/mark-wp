<?php
/**
 * Responsive Srcset, Sizes, and Native Lazy Loading Injector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Media_Responsive_Images {
    
    public function __construct() {
        add_filter('the_content', [$this, 'optimize_content_images'], 99);
        add_filter('post_thumbnail_html', [$this, 'optimize_thumbnail_images'], 99, 5);
    }

    public function optimize_content_images($content) {
        if (empty($content)) {
            return $content;
        }

        // Use DOMDocument to parse HTML and manipulate image tags safely
        $dom = new DOMDocument();
        // Suppress warnings due to malformed HTML in content
        libxml_use_internal_errors(true);
        // Ensure UTF-8 is handled properly
        if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return $content;
        }
        libxml_clear_errors();

        $images = $dom->getElementsByTagName('img');
        $has_changes = false;

        foreach ($images as $img) {
            $has_changes = $this->process_image_node($img, $dom) || $has_changes;
        }

        if ($has_changes) {
            // Remove the XML declaration we prepended
            $html = $dom->saveHTML();
            return str_replace('<?xml encoding="utf-8" ?>', '', $html);
        }

        return $content;
    }

    public function optimize_thumbnail_images($html, $post_id, $post_thumbnail_id, $size, $attr) {
        if (empty($html)) {
            return $html;
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return $html;
        }
        libxml_clear_errors();

        $images = $dom->getElementsByTagName('img');
        $has_changes = false;

        foreach ($images as $img) {
            $has_changes = $this->process_image_node($img, $dom) || $has_changes;
        }

        if ($has_changes) {
            $new_html = $dom->saveHTML();
            return str_replace('<?xml encoding="utf-8" ?>', '', $new_html);
        }

        return $html;
    }

    private function process_image_node($img, $dom) {
        $changed = false;

        // 1. Native Lazy Loading & Decoding
        if (!$img->hasAttribute('loading')) {
            $img->setAttribute('loading', 'lazy');
            $changed = true;
        }
        if (!$img->hasAttribute('decoding')) {
            $img->setAttribute('decoding', 'async');
            $changed = true;
        }

        $src = $img->getAttribute('src');

        // Auto Width/Height Injector to prevent CLS
        if (!empty($src) && (!$img->hasAttribute('width') || !$img->hasAttribute('height'))) {
            $attachment_id = attachment_url_to_postid($src);
            if ($attachment_id) {
                $image_src = wp_get_attachment_image_src($attachment_id, 'full');
                if ($image_src) {
                    if (!$img->hasAttribute('width')) {
                        $img->setAttribute('width', $image_src[1]);
                        $changed = true;
                    }
                    if (!$img->hasAttribute('height')) {
                        $img->setAttribute('height', $image_src[2]);
                        $changed = true;
                    }
                }
            }
        }

        // 2. Wrap in <picture> for AVIF / WEBP support
        if (!empty($src) && preg_match('/\.(jpg|jpeg|png)$/i', $src)) {
            $parent = $img->parentNode;
            
            // Only wrap if it's not already in a picture tag
            if ($parent && strtolower($parent->nodeName) !== 'picture') {
                $picture = $dom->createElement('picture');
                
                $avif_src = preg_replace('/\.(jpg|jpeg|png)$/i', '.avif', $src);
                $source_avif = $dom->createElement('source');
                $source_avif->setAttribute('srcset', $avif_src);
                $source_avif->setAttribute('type', 'image/avif');
                $picture->appendChild($source_avif);

                $webp_src = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $src);
                $source_webp = $dom->createElement('source');
                $source_webp->setAttribute('srcset', $webp_src);
                $source_webp->setAttribute('type', 'image/webp');
                $picture->appendChild($source_webp);

                // Replace img with picture, then append img inside picture
                $parent->replaceChild($picture, $img);
                $picture->appendChild($img);
                $changed = true;
            }
        }

        return $changed;
    }
}
