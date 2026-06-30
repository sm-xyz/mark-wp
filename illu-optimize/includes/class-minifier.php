<?php
/**
 * HTML and Inline CSS Minifier
 */

if (!defined('ABSPATH')) {
    exit;
}

class Illu_Optimize_Minifier {
    
    public function __construct() {
        if (!is_admin() && !wp_is_json_request()) {
            add_action('template_redirect', [$this, 'start_buffer'], 9999);
        }
    }

    public function start_buffer() {
        ob_start([$this, 'minify_html']);
    }

    public function minify_html($buffer) {
        // Only minify if the response is HTML
        if (strpos($buffer, '<html') === false) {
            return $buffer;
        }

        // Exclude tags from minification to avoid breaking JS/CSS/formatting
        $exclude_tags = ['script', 'style', 'pre', 'textarea'];
        $placeholders = [];
        
        foreach ($exclude_tags as $tag) {
            if (preg_match_all('/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is', $buffer, $matches)) {
                foreach ($matches[0] as $i => $match) {
                    $placeholder = '<!--ILLU_MINIFY_PLACEHOLDER_' . strtoupper($tag) . '_' . $i . '-->';
                    $placeholders[$placeholder] = $match;
                    $buffer = str_replace($match, $placeholder, $buffer);
                }
            }
        }

        // Remove HTML comments (but preserve IE conditionals and our placeholders)
        $buffer = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>|ILLU_MINIFY))(?:(?!-->).)*-->/s', '', $buffer);
        
        // Minify white space around block-level elements
        $search = [
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/(\s)+/s',         // shorten multiple whitespace sequences
        ];
        
        $replace = [
            '>',
            '<',
            '\\1',
        ];

        $buffer = preg_replace($search, $replace, $buffer);

        // Restore excluded tags
        if (!empty($placeholders)) {
            $buffer = str_replace(array_keys($placeholders), array_values($placeholders), $buffer);
        }

        return $buffer;
    }
}
