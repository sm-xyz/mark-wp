<?php
if (!defined('ABSPATH')) exit;

/**
 * Lightweight TOTP & Base32 implementation.
 */
class Illu_Shield_TOTP {
    
    private static $base32_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generate_secret($length = 16) {
        $secret = '';
        try {
            $bytes = random_bytes($length);
        } catch (Exception $e) {
            // Fallback if random_bytes fails (very rare in PHP 7+)
            $bytes = openssl_random_pseudo_bytes($length);
        }
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32_chars[ord($bytes[$i]) % 32];
        }
        return $secret;
    }

    public static function generate_recovery_codes($count = 8) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            try {
                $bytes = random_bytes(4);
            } catch (Exception $e) {
                $bytes = openssl_random_pseudo_bytes(4);
            }
            $codes[] = strtoupper(implode('-', str_split(bin2hex($bytes), 4)));
        }
        return $codes;
    }

    public static function get_totp($secret) {
        $key = self::base32_decode($secret);
        $time = floor(time() / 30);
        
        $time_bytes = pack('N', 0) . pack('N', $time);
        $hash = hash_hmac('sha1', $time_bytes, $key, true);
        
        $offset = ord(substr($hash, -1)) & 0x0F;
        $hashpart = substr($hash, $offset, 4);
        
        $value = unpack('N', $hashpart);
        $value = $value[1];
        
        $value = $value & 0x7FFFFFFF;
        $code = $value % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    public static function verify_totp($secret, $code, $discrepancy = 1) {
        $current_time = floor(time() / 30);
        $key = self::base32_decode($secret);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $time = $current_time + $i;
            $time_bytes = pack('N', 0) . pack('N', $time);
            $hash = hash_hmac('sha1', $time_bytes, $key, true);
            
            $offset = ord(substr($hash, -1)) & 0x0F;
            $hashpart = substr($hash, $offset, 4);
            
            $value = unpack('N', $hashpart);
            $value = $value[1];
            
            $value = $value & 0x7FFFFFFF;
            $calculated_code = $value % 1000000;
            
            if (hash_equals(str_pad($calculated_code, 6, '0', STR_PAD_LEFT), str_pad($code, 6, '0', STR_PAD_LEFT))) {
                // FIX-01: TOTP Replay Protection
                $used_key = 'illu_2fa_used_' . md5($secret . $code);
                if (get_transient($used_key)) {
                    return false; // Replay attack terdeteksi
                }
                set_transient($used_key, true, 90); // Expire setelah 90 detik (3 window)
                return true;
            }
        }
        return false;
    }

    private static function base32_decode($secret) {
        if (empty($secret)) return '';
        $secret = strtoupper($secret);
        $key = '';
        $buffer = 0;
        $buffer_size = 0;
        
        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            $val = strpos(self::$base32_chars, $char);
            if ($val === false) continue;
            
            $buffer = ($buffer << 5) | $val;
            $buffer_size += 5;
            
            if ($buffer_size >= 8) {
                $buffer_size -= 8;
                $key .= chr(($buffer >> $buffer_size) & 0xFF);
            }
        }
        return $key;
    }
}
