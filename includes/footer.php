<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Common Footer
 * =========================================================
 */

defined('DFMS_EXEC') or die('Access Denied');
?>
    </div> <!-- End content-wrapper -->
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-left">
                <p>&copy; <?php echo date('Y'); ?> Dairy Farm Management System. All rights reserved.</p>
            </div>
            <div class="footer-right">
                <p>Version 1.0 | Developed for Farm Management</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="<?php echo SITE_URL; ?>assets/js/script.js"></script>
    
    <!-- Custom Page JS (if exists) -->
    <?php if (isset($custom_js)): ?>
        <script><?php echo $custom_js; ?></script>
    <?php endif; ?>
    
    <script>
        // Dropdown toggle
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const parent = this.parentElement;
                
                // Close other dropdowns
                document.querySelectorAll('.dropdown').forEach(dropdown => {
                    if (dropdown !== parent) {
                        dropdown.classList.remove('active');
                    }
                });
                
                // Toggle current dropdown
                parent.classList.toggle('active');
            });
        });
        
        // User dropdown toggle
        document.querySelector('.user-btn')?.addEventListener('click', function(e) {
            e.preventDefault();
            this.parentElement.classList.toggle('active');
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.dropdown') && !e.target.closest('.user-dropdown')) {
                document.querySelectorAll('.dropdown, .user-dropdown').forEach(el => {
                    el.classList.remove('active');
                });
            }
        });
    </script>
</body>
</html>