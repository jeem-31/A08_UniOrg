/**
 * Instant Navigation System
 * Provides faster navigation with progress indicator
 */
if (typeof InstantNavigation === 'undefined') {
    class InstantNavigation {
        constructor() {
            this.progressBar = null;
            this.init();
        }

        init() {
            this.createProgressBar();
            this.attachEventListeners();
        }

        createProgressBar() {
            // Create thin loading bar at top (YouTube-style)
            const bar = document.createElement('div');
            bar.id = 'instant-nav-progress';
            bar.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                height: 3px;
                background: linear-gradient(90deg, #4CAF50, #45a049);
                width: 0;
                transition: width 0.3s ease;
                z-index: 99999;
                box-shadow: 0 0 10px rgba(76, 175, 80, 0.5);
            `;
            document.body.appendChild(bar);
            this.progressBar = bar;
        }

        attachEventListeners() {
            // Intercept clicks on links with data-instant attribute
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a[data-instant]');
                if (link && !link.hasAttribute('data-no-instant')) {
                    e.preventDefault();
                    const url = link.getAttribute('href');
                    if (url && url !== '#' && url !== 'javascript:void(0)') {
                        this.navigate(url);
                    }
                }
            });
        }

        navigate(url) {
            // Show progress bar
            this.showProgress();
            
            // Navigate to URL (browser will handle the rest)
            window.location.href = url;
        }

        showProgress() {
            this.progressBar.style.width = '0%';
            this.progressBar.style.opacity = '1';
            
            // Animate to 90%
            setTimeout(() => {
                this.progressBar.style.width = '90%';
            }, 10);
            
            // Complete when page starts loading
            window.addEventListener('beforeunload', () => {
                this.progressBar.style.width = '100%';
            }, { once: true });
        }
    }

    // Make it globally available
    window.InstantNavigation = InstantNavigation;
}

// Initialize when DOM is ready (only if not already initialized)
if (!window.instantNav) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.instantNav = new InstantNavigation();
        });
    } else {
        window.instantNav = new InstantNavigation();
    }
}
