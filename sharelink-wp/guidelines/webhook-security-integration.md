# Webhook Security & Integration Logic

This document defines the strict, code-level logic for handling incoming webhooks (Lynk.id, Mayar, Scalev) and the Web Application Firewall (WAF) bypass logic required to prevent false positives and blockages.

**CRITICAL:** Any modifications to the security plugins (`illu-shield`) or webhook receivers (`sharelink-wp`) MUST adhere to these exact code signatures to ensure stability.

## 1. WAF Whitelist Logic (`illu-shield`)

To ensure webhooks from external services (which may originate from foreign IPs, lack standard user agents, or trigger rate limits) are not blocked, the following bypass logic MUST be present at the **very beginning** of these security methods in `illu-shield/includes/class-security.php`:

*   `rate_limit_rest_api()`
*   `check_country_block()`
*   `block_bad_bots()`
*   `block_blacklisted_ips()`
*   `micro_firewall()`

### Implementation Standard (PHP)
```php
$raw_uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($raw_uri, '/wp-json/sharelink/') !== false || 
    strpos($raw_uri, 'rest_route=/sharelink/') !== false || 
    strpos($raw_uri, '/wp-json/canvas-app/') !== false || 
    strpos($raw_uri, 'rest_route=/canvas-app/') !== false ||
    strpos($raw_uri, '/webhook') !== false || 
    strpos($raw_uri, '/wp-json/webhook/') !== false) {
    return; // Fast return to bypass security checks for webhook endpoints
}
```

For `restrict_rest_api_global()`, the logic allows unauthenticated access:
```php
if (strpos($path, '/wp-json/sharelink/') !== false || 
    strpos($path, 'rest_route=/sharelink/') !== false ||
    strpos($path, '/wp-json/webhook/') !== false || 
    strpos($path, '/wp-json/canvas-app/') !== false || 
    strpos($path, 'rest_route=/canvas-app/') !== false) {
    return $result; // Allow unauthenticated access
}
```

---

## 2. Webhook Parsing Logic (`sharelink-wp`)

The webhook receiver in `sharelink-wp/includes/class-api.php` handles three distinct payload structures. Modifying this logic requires retaining these specific condition checks.

### Endpoint Registration
```php
register_rest_route('canvas-app/v1', '/webhook', [
    'methods' => ['POST', 'OPTIONS'],
    'callback' => 'cl_rest_webhook_payment',
    'permission_callback' => '__return_true' // Must remain true, authenticated via signature/payload
]);
```

### Parsing Conditions
The `cl_rest_webhook_payment` function must branch exactly as follows to identify the webhook source:

```php
// 1. Scalev Integration
if (isset($p['type']) && $p['type'] === 'scalev') {
    $source_name = 'scalev';
    $payment_status = $p['payment_status'] ?? '';
    if ($payment_status !== 'paid') {
        // Return 200 to prevent retries
        return new WP_REST_Response(['valid'=>true, 'message'=>'Status not paid'], 200); 
    }
    // Field mapping:
    // $buyer_name = $p['customer_name'];
    // $buyer_email = $p['customer_email'];
    // $buyer_phone = cl_normalize_wa($p['customer_phone']);
} 
// 2. Mayar.id Integration
elseif ($is_mayar && isset($p['event']) && $p['event'] === 'payment.received') {
    $source_name = 'mayar.id';
    $status = $p['data']['status'] ?? '';
    if ($status !== 'SUCCESS') {
        return new WP_REST_Response(['valid'=>true, 'message'=>'Status not SUCCESS'], 200);
    }
    // Field mapping:
    // $buyer_name = $p['data']['customerName'];
    // $buyer_email = $p['data']['customerEmail'];
    // $buyer_phone = cl_normalize_wa($p['data']['customerMobile']);
} 
// 3. Lynk.id Integration
elseif (isset($p['data']['message_action']) || $req->get_header('x_lynk_signature')) {
    $source_name = 'lynk.id';
    $signature = $req->get_header('x_lynk_signature');
    // Field mapping:
    // $buyer_name = $p['data']['message_data']['customer']['name'];
    // $buyer_email = $p['data']['message_data']['customer']['email'];
    // $buyer_phone = cl_normalize_wa($p['data']['message_data']['customer']['phone']);
}
```

---

## 3. Test Mode Standards

When sending test payloads from the admin dashboard (e.g., `sharelink-wp/views/webhook_admin.php` and `sharelink-wp/views/webhook.php`), the mock customer data MUST use standard safe values to prevent sender reputation drops or blacklist triggers.

**Required Mock Data:**
```json
{
  "customer_email": "test@solusimarketing.xyz",
  "customer_phone": "6285156234820"
}
```
*Do NOT use `test@example.com` or `081234567890`.*

---

## Rollback & Recovery

If webhook functionality fails after a plugin update:
1.  **Check `illu-shield` bypass:** Ensure the exact bypass code block exists at the top of the security firewall methods. Webhooks typically fail because foreign server IPs trigger `check_country_block` or `block_bad_bots` due to missing user-agent headers.
2.  **Check Condition Overlap:** Ensure the `if...elseif` ladder in `sharelink-wp/includes/class-api.php` does not incorrectly intercept payloads intended for a different service.
3.  **Check Return Codes:** Ensure failed validations (e.g., unpaid status) return a `200 OK` (with an ignore message) rather than a `400/500` error, to prevent the external webhook provider from continuously retrying the request.
