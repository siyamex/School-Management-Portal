    </div> <!-- End Main Content Container -->
    
    <!-- Footer -->
    <footer class="footer footer-center p-4 bg-base-100 text-base-content mt-8">
        <div>
            <p>Copyright © <?php echo date('Y'); ?> - <?php echo APP_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script>
        const APP_URL = '<?php echo APP_URL; ?>';
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('<?php echo APP_URL; ?>/sw.js')
                    .then(registration => {
                        console.log('Service Worker registered successfully:', registration.scope);
                    })
                    .catch(error => {
                        console.log('Service Worker registration failed:', error);
                    });
            });
        }

        // PWA Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show install button/banner if you want
            const installBanner = document.createElement('div');
            installBanner.className = 'alert alert-info fixed bottom-4 right-4 w-auto shadow-lg z-50';
            installBanner.innerHTML = `
                <div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-current shrink-0 w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <div>
                        <h3 class="font-bold">Install School Portal</h3>
                        <div class="text-xs">Add to home screen for quick access</div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="btn btn-sm" onclick="installPWA()">Install</button>
                    <button class="btn btn-sm btn-ghost" onclick="this.closest('.alert').remove()">Later</button>
                </div>
            `;
            
            // Only show if not already installed
            if (!window.matchMedia('(display-mode: standalone)').matches) {
                document.body.appendChild(installBanner);
            }
        });

        function installPWA() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    }
                    deferredPrompt = null;
                    document.querySelector('.alert')?.remove();
                });
            }
        }

        // Check if app is installed
        window.addEventListener('appinstalled', () => {
            console.log('PWA was installed');
            document.querySelector('.alert')?.remove();
        });
    </script>
    
</body>
</html>
