# Core Architecture Logic: ShareLink AI App Lock

## 1. Single-File Payload Management
```json
{
  "Entity": "App Canvas",
  "Properties": {
    "payload": "Base64 encoded string containing full HTML/CSS/JS user app",
    "login_script": "Empty shell HTML containing verification logic"
  },
  "Condition": "Payload is NEVER injected raw into the generated login_script out-of-the-box"
}
```

## 2. Bootstrapping & Fast-hide (Client-Side)
```javascript
// Target: Generated login script
// Run on script boot context
function bootstrapLockScript(app_id) {
  let cachedPayload = null;
  const storageKeys = [
    'window.name',
    'localStorage', 
    'sessionStorage', 
    'history.state', 
    'URL parameters'
  ];
  
  // Attempt to fetch decrypted payload cache
  for (let source of storageKeys) {
     cachedPayload = checkStorage(source, `cl_dec_key_${app_id}`);
     if (cachedPayload) break; 
  }
  
  if (cachedPayload) {
    // Action: Prevent UI 'flash' by overriding paint layout
    injectStyle('cl-fast-hide', '#licenseModal, #license-loader, #messageModal { display: none !important; }');
  } else {
    // Action: Require user authentication
    showLicenseModal();
  }
}
```

## 3. Verification & API Validation
```javascript
async function validateLicense(licenseKey, app_id) {
  // Generate volatile device signature
  const fingerprint = btoa([navigator.userAgent, navigator.language, screen.width, screen.height].join('|')).slice(0, 32);
  
  // Payload API request
  const response = await fetch('/wp-json/canvas-app/v1/verify', {
    method: 'POST',
    body: JSON.stringify({ license: licenseKey, app_id: app_id, fingerprint: fingerprint })
  });
  
  if (response.valid && response.payload) {
    // Validation successful, propagate payload via persistence mediums
    storeAllMediums(`cl_dec_key_${app_id}`, response.payload);
    // Execute DOM injector
    rn(response.payload); 
  } else {
    // Clear persistence mediums & display trace
    clearAllMediums(`cl_dec_key_${app_id}`);
    showError(response.message);
  }
}
```

## 4. DOM Re-Rendering Engine (rn())
```javascript
// Target logic for DOM repainting
async function rn(payload) {
  // Step 1: Hide UI transitions
  document.documentElement.style.opacity = '0';
  document.head.innerHTML = '';
  document.body.innerHTML = '';
  
  // Step 2: DOM AST parsing
  const doc = new DOMParser().parseFromString(payload, 'text/html');
  
  // Step 3: Insert generic elements
  doc.head.childNodes.forEach(node => {
    if (node.tagName !== 'SCRIPT') document.head.appendChild(node.cloneNode(true));
  });
  document.body.innerHTML = doc.body.innerHTML;
  
  // Step 4: Sequential Promise-based script execution
  // Important: Eliminates load race conditions commonly found in Webpack bundles
  const scripts = doc.querySelectorAll('script');
  for (let oldScript of scripts) {
    await new Promise(resolve => {
      let newScript = document.createElement('script');
      copyAttributesFrom(oldScript, newScript);
      newScript.text = oldScript.innerHTML;
      
      // Step 4.5: Dynamic Environment variables regex replacement
      if (typeof geminiApiKey !== 'undefined' && geminiApiKey) {
        newScript.text = newScript.text.replace(/const\s+apiKey\s*=\s*(['"]).*?\1\s*;/gi, `const apiKey = "${geminiApiKey}";`);
      }
      
      if (newScript.src) {
        newScript.onload = resolve;
        newScript.onerror = resolve;
      }
      
      document.body.appendChild(newScript);
      if (!newScript.src) resolve();
    });
  }
  
  // Step 5: Boot trigger simulation
  window.dispatchEvent(new Event('DOMContentLoaded'));
  document.dispatchEvent(new Event('DOMContentLoaded'));
  window.dispatchEvent(new Event('load'));
  
  // Step 6: Reveal Layout
  setTimeout(() => { document.documentElement.style.opacity = '1'; }, 50);
}
```
