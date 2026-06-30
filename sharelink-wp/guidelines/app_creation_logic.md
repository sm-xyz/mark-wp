# Logic Flow & Pseudocode: App Creation

## 1. Frontend: Payload Encoding (Form Submit)
```javascript
// views/apps.php (#cl-app-form)
function onFormSubmit(payload) {
  let encodedPayload = "";
  if (!isBase64(payload)) {
    // Encode raw HTML to Base64 to bypass server limit & WAF filters
    encodedPayload = btoa(encodeURIComponent(payload));
  } else {
    encodedPayload = payload;
  }
  return encodedPayload; // Send to backend processing
}

function isBase64(str) {
  return /^[a-zA-Z0-9\+\/\=\n\r]+$/.test(str);
}
```

## 2. Backend: Database Storage
```sql
-- PHP processing: payload securely written as literal Base64
-- Data Type: LONGTEXT
-- Security: Unfiltered DOM string, no stripping required.

UPDATE cl_apps
SET payload = $encodedPayload
WHERE id = $app_id AND user_id = $uid;
```

## 3. Frontend: Display Payload in Editor
```php
// views/apps.php (Render Form Edit)
function getDisplayPayload($payloadFromDB) {
  // Validate if string is pure Base64
  if (preg_match('/^[a-zA-Z0-9\+\/\=\n\r]+$/', $payloadFromDB)) {
    $decoded = base64_decode(trim($payloadFromDB), true);
    if ($decoded !== false) {
      $urldecoded = urldecode($decoded);
      if ($urldecoded !== '') {
        return $urldecoded; // Output to <textarea>
      }
    }
  }
  return $payloadFromDB;
}
```

## 4. Duplicate App Logic
```php
// Core copying implementation (e.g., when clicking clone)
function duplicateApp($original_app_id) {
  // Query original properties
  $original = db_query("SELECT app_name, canvas_link, payload, gk_config FROM cl_apps WHERE id = $original_app_id");
  
  // Clone key data, exclude login_script
  db_insert('cl_apps', [
    'app_name' => $original->app_name . ' (Copy)',
    'canvas_link' => $original->canvas_link,
    'payload' => $original->payload,
    'gk_config' => $original->gk_config,
    'login_script' => null // Must be re-generated explicitly
  ]);
}
```
