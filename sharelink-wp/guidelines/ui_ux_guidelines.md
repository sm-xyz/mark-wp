# Frontend UI/UX Structure & DOM Logic

## 1. Frame Application Window Layout
```html
<!-- DOM Tree Structure: Full Screen Native Interaction Standard -->
<html style="overflow: hidden; margin: 0; padding: 0;">
  <body style="overflow: hidden; margin: 0; padding: 0;">
    
    <!-- Application Wrapper (Zero internal body scrollbar logic) -->
    <div class="fixed inset-0 flex bg-slate-50 relative z-[99999]" id="app-shell">
      
      <!-- Component: Mobile Overlay -->
      <div id="mobile-overlay" onClick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>
      
      <!-- Component: Sidebar Navigation -->
      <div id="sidebar" class="fixed inset-y-0 left-0 w-64 bg-brand text-white flex flex-col z-50 transform -translate-x-full md:translate-x-0 md:relative overflow-y-auto">
         <header id="sidebar-header" class="h-16 shrink-0 [properties...]">...</header>
         <nav class="flex-1 overflow-y-auto pt-4 pb-4">...</nav>
         <footer id="sidebar-footer" class="shrink-0 p-4">...</footer>
      </div>
      
      <!-- Component: Main Interactive Content Area -->
      <div id="main-content-wrapper" class="flex-1 flex flex-col min-w-0 overflow-hidden">
        
        <!-- Component: Header / Status Bar -->
        <header class="h-16 shrink-0 flex justify-between px-4 md:px-8 border-b">...</header>
        
        <!-- Component: View Space Container -->
        <!-- Important: min-h-full pb-16 handles trailing rendering bugs on iOS Safari bounds -->
        <main class="flex-1 overflow-auto p-4 md:p-6 bg-slate-50 relative">
           <div class="w-full mx-auto min-h-full pb-16 2xl:px-8">
               <!-- View Injection -->
           </div>
           <!-- Float Component -->
           <div id="toast-container" class="fixed bottom-6 right-6 z-50"></div>
        </main>
        
      </div>
    </div>
    
  </body>
</html>
```


## 2. Component Logic: Responsive Table Schema
```css
/* Table Core Standard Constraints */
.table-container {
  overflow-x: auto; /* Required for Mobile Viewport Isolation */
}
.table {
  width: 100%;
  border-collapse: collapse;
  min-width: 700px; /* Safe bounds to prevent column overlapping on micro-displays */
}
```

```html
<!-- Table Injection Element -->
<div class="table-card bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mt-8">
  <div class="toolbar p-4 border-b border-slate-100 flex justify-between">...</div>
  
  <!-- Wrapper required for independent X-Axis scrolling -->
  <div class="overflow-x-auto">
    <table class="w-full text-left border-collapse min-w-[700px]">
      <thead class="bg-slate-50 border-b border-slate-200">
        <!-- Generic standard alignment patterns -->
        <tr>
          <th class="p-4 pl-6 w-12 text-center whitespace-nowrap">ID</th>
          <th class="p-4 whitespace-nowrap">Column Title</th>
          <th class="p-4 pr-6 text-right w-24">Actions</th>
        </tr>
      </thead>
      <tbody>
         <!-- Data loop injection -->
      </tbody>
    </table>
  </div>
</div>
```


## 3. Interaction Logic: System Toast
```javascript
// Generic Notification Spawner Context
function showToast(msg, type = 'success') {
  // 1. Fetch persistent DOM container
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  
  // 2. Define payload and tailwind atomic base states (hidden context: opacity-0, translate-y-10)
  toast.className = `transform transition-all duration-300 translate-y-10 opacity-0 flex items-center px-5 py-4 rounded-xl shadow-xl text-sm font-medium border ${type === 'success' ? 'bg-brand text-white border-brand' : 'bg-red-50 text-red-600 border-red-200'}`;
  toast.innerHTML = `<i data-lucide="..."></i><span>${msg}</span>`;
  
  container.appendChild(toast);
  lucide.createIcons();
  
  // 3. Initiate animation via DOM Reflow pipeline
  requestAnimationFrame(() => {
    setTimeout(() => {
       toast.classList.remove('translate-y-10', 'opacity-0');
    }, 10);
  });
  
  // 4. Garbage Collection Lifecycle (MS Timeout = 3000ms duration + 300ms transition buffer)
  setTimeout(() => {
    toast.classList.add('translate-y-10', 'opacity-0');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}
```
