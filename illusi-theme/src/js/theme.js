// Illusi Theme - Main JS

document.addEventListener('DOMContentLoaded', () => {
    initDarkMode();
    initSpaPrefetch();
});

function initDarkMode() {
    const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
    const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');
    const themeToggleBtn = document.getElementById('theme-toggle');

    if (!themeToggleBtn) return;

    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        themeToggleLightIcon?.classList.remove('hidden');
    } else {
        themeToggleDarkIcon?.classList.remove('hidden');
    }

    themeToggleBtn.addEventListener('click', function() {
        themeToggleDarkIcon?.classList.toggle('hidden');
        themeToggleLightIcon?.classList.toggle('hidden');

        if (localStorage.getItem('theme') === 'light' || (!('theme' in localStorage) && !window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }
    });
}

function initSpaPrefetch() {
    let prefetchTimeout;
    const prefetchedUrls = new Set();
    
    document.addEventListener('mouseover', (e) => {
        const link = e.target.closest('a');
        if (!link) return;
        
        const url = link.href;
        if (!url.startsWith(window.location.origin) || url.includes('wp-admin') || url.includes('wp-login')) return;
        if (prefetchedUrls.has(url)) return;
        
        prefetchTimeout = setTimeout(() => {
            const prefetchLink = document.createElement('link');
            prefetchLink.rel = 'prefetch';
            prefetchLink.href = url;
            document.head.appendChild(prefetchLink);
            prefetchedUrls.add(url);
        }, 50); // 50ms hover threshold
    });
    
    document.addEventListener('mouseout', () => {
        if (prefetchTimeout) clearTimeout(prefetchTimeout);
    });
}
