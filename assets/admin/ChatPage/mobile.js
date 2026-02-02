/**
 * Mobile Hamburger Menu functionality for Plugitify Chat
 */
(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const chatApp = document.querySelector('.plugitify-chat-app');
        const sidebar = document.querySelector('.sidebar');

        if (!hamburgerBtn || !chatApp) {
            return;
        }

        // Toggle sidebar on hamburger click
        hamburgerBtn.addEventListener('click', function() {
            chatApp.classList.toggle('sidebar-open');
            
            // Update aria attributes for accessibility
            const isOpen = chatApp.classList.contains('sidebar-open');
            hamburgerBtn.setAttribute('aria-expanded', isOpen);
            if (sidebar) {
                sidebar.setAttribute('aria-hidden', !isOpen);
            }
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 720 && chatApp.classList.contains('sidebar-open')) {
                if (!sidebar.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                    chatApp.classList.remove('sidebar-open');
                    hamburgerBtn.setAttribute('aria-expanded', 'false');
                    if (sidebar) {
                        sidebar.setAttribute('aria-hidden', 'true');
                    }
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 720) {
                chatApp.classList.remove('sidebar-open');
                hamburgerBtn.setAttribute('aria-expanded', 'false');
                if (sidebar) {
                    sidebar.setAttribute('aria-hidden', 'false');
                }
            }
        });
    });
})();