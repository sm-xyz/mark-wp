<?php
/**
 * On-the-Fly WebP/AVIF Converter
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Media_Image_Converter {
    
    public function __construct() {
        add_filter('wp_generate_attachment_metadata', [$this, 'convert_images'], 10, 2);
    }

    public function convert_images($metadata, $attachment_id) {
        if (!extension_loaded('imagick')) {
            return $metadata;
        }

        $upload_dir = wp_upload_dir();
        
        if (!isset($metadata['file'])) {
            return $metadata;
        }
        
        $original_file = trailingslashit($upload_dir['basedir']) . $metadata['file'];
        
        // Convert the original image
        $this->process_conversion($original_file);
        
        // Convert all generated sizes
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            $base_path = dirname($original_file);
            foreach ($metadata['sizes'] as $size => $size_info) {
                $size_file = trailingslashit($base_path) . $size_info['file'];
                $this->process_conversion($size_file);
            }
        }
        
        return $metadata;
    }

    private function process_conversion($file_path) {
        if (!file_exists($file_path)) return;

        $mime_type = mime_content_type($file_path);
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return;
        }

        try {
            $image = new Imagick($file_path);
            
            // Generate AVIF
            if (in_array('AVIF', Imagick::queryFormats())) {
                $avif_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.avif', $file_path);
                if (!file_exists($avif_path)) {
                    $avif_image = clone $image;
                    $avif_image->setImageFormat('avif');
                    $avif_image->setImageCompressionQuality(70);
                    $avif_image->writeImage($avif_path);
                    $avif_image->clear();
                    $avif_image->destroy();
                }
            }

            // Generate WebP
            if (in_array('WEBP', Imagick::queryFormats())) {
                $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $file_path);
                if (!file_exists($webp_path)) {
                    $webp_image = clone $image;
                    $webp_image->setImageFormat('webp');
                    $webp_image->setImageCompressionQuality(75);
                    $webp_image->writeImage($webp_path);
                    $webp_image->clear();
                    $webp_image->destroy();
                }
            }
            
            $image->clear();
            $image->destroy();
            
        } catch (Exception $e) {
            // Silently fail if conversion error occurs, keeping the original image
        }
    }
}
