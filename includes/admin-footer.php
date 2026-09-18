<?php
// includes/admin-footer.php
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    http_response_code(403);
    exit;
}
?>
    </main>
</div>

<script>
    // Initialize Lucide Icons
    lucide.createIcons();

    // Responsive Mobile Sidebar Drawer Controller
    (function() {
        const toggleBtn = document.getElementById('adminSidebarToggle');
        const overlay = document.getElementById('adminSidebarOverlay');
        const layout = document.querySelector('.admin-layout');
        const sidebar = document.getElementById('adminSidebar');

        if (!toggleBtn || !layout || !sidebar || !overlay) {
            return; // Fail-safe if elements don't exist
        }

        function openSidebar() {
            layout.classList.add('sidebar-open');
            toggleBtn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            layout.classList.remove('sidebar-open');
            toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }

        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (layout.classList.contains('sidebar-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });

        overlay.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && layout.classList.contains('sidebar-open')) {
                closeSidebar();
            }
        });

        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 991) {
                    closeSidebar();
                }
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth > 991) {
                if (layout.classList.contains('sidebar-open')) {
                    closeSidebar();
                }
            }
        });
    })();
</script>

<script src="../assets/js/admin-enterprise.js"></script>
</body>
</html>
