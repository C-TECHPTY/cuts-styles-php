(function () {
    const baseUrl = window.CUTS_PWA_BASE_URL || '/';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(baseUrl + 'sw.js', { scope: baseUrl }).catch(function () {
                // Fallback silencioso: la app sigue funcionando como web normal.
            });
        });
    }

    let deferredPrompt = null;

    function createInstallButton() {
        if (document.getElementById('pwa-install-btn')) {
            return document.getElementById('pwa-install-btn');
        }

        const button = document.createElement('button');
        button.id = 'pwa-install-btn';
        button.type = 'button';
        button.textContent = 'Instalar app';
        button.style.position = 'fixed';
        button.style.right = '16px';
        button.style.bottom = '16px';
        button.style.zIndex = '9999';
        button.style.padding = '12px 16px';
        button.style.border = 'none';
        button.style.borderRadius = '999px';
        button.style.background = '#1a1a2e';
        button.style.color = '#fff';
        button.style.fontWeight = '700';
        button.style.boxShadow = '0 10px 24px rgba(0,0,0,0.18)';
        button.style.cursor = 'pointer';
        button.style.display = 'none';
        document.body.appendChild(button);

        button.addEventListener('click', async function () {
            if (!deferredPrompt) {
                return;
            }
            deferredPrompt.prompt();
            try {
                await deferredPrompt.userChoice;
            } finally {
                deferredPrompt = null;
                button.style.display = 'none';
            }
        });

        return button;
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        const button = createInstallButton();
        button.style.display = 'inline-flex';
        button.style.alignItems = 'center';
        button.style.justifyContent = 'center';
    });

    window.addEventListener('appinstalled', function () {
        const button = document.getElementById('pwa-install-btn');
        if (button) {
            button.style.display = 'none';
        }
        deferredPrompt = null;
    });
})();
