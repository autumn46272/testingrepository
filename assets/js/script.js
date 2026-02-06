// Encapsulate to avoid global pollution
(function () {
    function initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const toggleBtn = document.getElementById('sidebarToggle');
        const mobileToggleBtns = document.querySelectorAll('.menu-toggle-mobile');

        if (!sidebar) {
            console.error("Sidebar element not found!");
            return;
        }

        // 1. Initialize State (Desktop Only)
        if (window.innerWidth > 768) {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) setCollapsedState(true);
        }

        // 2. Main Sidebar Toggle (Desktop/Internal)
        if (toggleBtn) {
            toggleBtn.addEventListener('click', (e) => {
                e.preventDefault(); // Prevent default behavior

                if (window.innerWidth <= 768) {
                    // Mobile: Close
                    sidebar.classList.remove('active');
                } else {
                    // Desktop: Toggle Collapse
                    const collapsed = sidebar.classList.contains('collapsed');
                    setCollapsedState(!collapsed);
                }
            });
        } else {
            console.warn("Sidebar toggle button not found");
        }

        // 3. Mobile Header Toggles
        mobileToggleBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                sidebar.classList.toggle('active');
            });
        });

        // 4. Click Outside (Mobile)
        document.addEventListener('click', (event) => {
            if (window.innerWidth > 768) return;

            const isClickInside = sidebar.contains(event.target);
            let isClickOnToggle = false;

            mobileToggleBtns.forEach(btn => {
                if (btn.contains(event.target)) isClickOnToggle = true;
            });

            if (!isClickInside && !isClickOnToggle && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
            }
        });

        // 5. Resize Handler
        window.addEventListener('resize', () => {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('collapsed');
                if (mainContent) mainContent.classList.remove('collapsed');
            } else {
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) sidebar.classList.add('collapsed');
            }
        });

        // Helper
        function setCollapsedState(collapsed) {
            if (collapsed) {
                sidebar.classList.add('collapsed');
                if (mainContent) mainContent.classList.add('collapsed');
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                sidebar.classList.remove('collapsed');
                if (mainContent) mainContent.classList.remove('collapsed');
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }
    }

    // Run Init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();
