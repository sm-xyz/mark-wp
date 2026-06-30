<?php

class Illu_Shield_Cloudflare {
    private $email;
    private $api_key;
    private $zone_id;
    private $is_active = false;

    public function __construct() {
        $settings = get_option('illu_shield_settings', []);
        
        $this->email = $settings['cloudflare_email'] ?? '';
        $this->api_key = illu_decrypt_secret($settings['cloudflare_api_key'] ?? '');
        $this->zone_id = $settings['cloudflare_zone_id'] ?? '';
        
        if (!empty($this->email) && !empty($this->api_key) && !empty($this->zone_id)) {
            $this->is_active = true;
            add_action('illu_shield_ip_blacklisted', [$this, 'sync_blacklisted_ip']);
            add_action('illu_shield_purge_cloudflare', [$this, 'purge_cache']);
        }
    }

    public function purge_cache() {
        if (!$this->is_active) return;

        $endpoint = "https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/purge_cache";
        
        $body = wp_json_encode([
            'purge_everything' => true
        ]);

        $args = [
            'method' => 'POST',
            'headers' => [
                'X-Auth-Email' => $this->email,
                'X-Auth-Key' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => $body,
            'timeout' => 15
        ];

        wp_remote_request($endpoint, $args);
    }

    public function sync_blacklisted_ip($ip) {
        if (!$this->is_active || !filter_var($ip, FILTER_VALIDATE_IP)) return;

        $endpoint = "https://api.cloudflare.com/client/v4/zones/{$this->zone_id}/firewall/access_rules/rules";
        
        $body = wp_json_encode([
            'mode' => 'block',
            'configuration' => [
                'target' => 'ip',
                'value' => $ip
            ],
            'notes' => 'Blocked by Illu Shield (Zero-Conflict WAF Sync)'
        ]);

        $args = [
            'method' => 'POST',
            'headers' => [
                'X-Auth-Email' => $this->email,
                'X-Auth-Key' => $this->api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => $body,
            'timeout' => 15 // Don't block WP for too long
        ];

        $response = wp_remote_request($endpoint, $args);
        
        if (is_wp_error($response)) {
            Illu_Shield_DB::log('Cloudflare Sync Error', 'Gagal sync IP ke Cloudflare: ' . $response->get_error_message());
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 || $code === 201) {
            Illu_Shield_DB::log('Cloudflare Sync', "Berhasil block IP {$ip} di Cloudflare WAF.");
        } else {
            $err_msg = isset($body['errors'][0]['message']) ? $body['errors'][0]['message'] : 'Unknown Error';
            Illu_Shield_DB::log('Cloudflare Sync Failed', "Gagal block IP {$ip} di Cloudflare: " . $err_msg);
        }
    }
}
