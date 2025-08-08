        </div>
        <!-- End main-content -->
    </div>
    <!-- End app-container -->
    
    <!-- Script globali -->
    <script>
        // Auto-hide alerts dopo 5 secondi
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => alert.remove(), 500);
                }, 5000);
            });
        });
        
        // Mobile menu toggle
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        if (mobileToggle && window.innerWidth <= 768) {
            mobileToggle.style.display = 'block';
        }
        
        // Responsive handler
        window.addEventListener('resize', function() {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
                if (toggle) toggle.style.display = 'none';
            } else {
                if (toggle) toggle.style.display = 'block';
            }
        });
    </script>
</body>
</html>