/**
 * Nexio Redesign JavaScript
 * Gestisce le animazioni e interazioni del nuovo design
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ========================================
    // BOTTONI - Effetti ripple e animazioni
    // ========================================
    
    // Aggiungi effetto ripple a tutti i bottoni
    document.querySelectorAll('.btn').forEach(button => {
        button.addEventListener('click', function(e) {
            const ripple = document.createElement('span');
            ripple.classList.add('ripple-effect');
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            
            this.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
    
    // ========================================
    // CARDS - Animazioni hover 3D
    // ========================================
    
    document.querySelectorAll('.card, .stat-card').forEach(card => {
        card.addEventListener('mousemove', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = (y - centerY) / 10;
            const rotateY = (centerX - x) / 10;
            
            this.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateZ(10px)`;
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) translateZ(0)';
        });
    });
    
    // ========================================
    // FORM INPUTS - Float label animation
    // ========================================
    
    document.querySelectorAll('.form-control').forEach(input => {
        // Check if input has value on load
        if (input.value) {
            input.classList.add('has-value');
        }
        
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('focused');
            if (this.value) {
                this.classList.add('has-value');
            } else {
                this.classList.remove('has-value');
            }
        });
    });
    
    // ========================================
    // SIDEBAR - Minify/Expand animation
    // ========================================
    
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.createElement('button');
    toggleBtn.className = 'sidebar-toggle';
    toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
    
    if (sidebar) {
        sidebar.appendChild(toggleBtn);
        
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('minified');
            
            // Save state
            localStorage.setItem('sidebar-minified', sidebar.classList.contains('minified'));
            
            // Animate icon
            this.querySelector('i').style.transform = 'rotate(180deg)';
            setTimeout(() => {
                this.querySelector('i').style.transform = 'rotate(0)';
            }, 300);
        });
        
        // Restore saved state
        if (localStorage.getItem('sidebar-minified') === 'true') {
            sidebar.classList.add('minified');
        }
    }
    
    // ========================================
    // SEARCH BAR - Expand animation
    // ========================================
    
    document.querySelectorAll('.search-bar').forEach(searchBar => {
        const button = searchBar.querySelector('button');
        const input = searchBar.querySelector('input');
        
        button.addEventListener('click', function(e) {
            if (!searchBar.classList.contains('expanded')) {
                e.preventDefault();
                searchBar.classList.add('expanded');
                input.focus();
            }
        });
        
        input.addEventListener('blur', function() {
            if (!this.value) {
                searchBar.classList.remove('expanded');
            }
        });
    });
    
    // ========================================
    // TAB NAVIGATION - Sliding indicator
    // ========================================
    
    document.querySelectorAll('.tab-nav').forEach(tabNav => {
        const tabs = tabNav.querySelectorAll('.tab-item');
        const activeTab = tabNav.querySelector('.tab-item.active');
        
        function updateIndicator(tab) {
            if (tab) {
                const rect = tab.getBoundingClientRect();
                const navRect = tabNav.getBoundingClientRect();
                
                tabNav.style.setProperty('--tab-indicator-left', `${rect.left - navRect.left}px`);
                tabNav.style.setProperty('--tab-indicator-width', `${rect.width}px`);
            }
        }
        
        // Set initial position
        updateIndicator(activeTab);
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active from all
                tabs.forEach(t => t.classList.remove('active'));
                
                // Add active to clicked
                this.classList.add('active');
                
                // Update indicator
                updateIndicator(this);
            });
        });
    });
    
    // ========================================
    // DROPDOWN - Smooth animation
    // ========================================
    
    document.querySelectorAll('.dropdown').forEach(dropdown => {
        const trigger = dropdown.querySelector('[data-toggle="dropdown"]');
        
        if (trigger) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Close other dropdowns
                document.querySelectorAll('.dropdown.active').forEach(d => {
                    if (d !== dropdown) d.classList.remove('active');
                });
                
                dropdown.classList.toggle('active');
            });
        }
    });
    
    // Close dropdowns on outside click
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown.active').forEach(d => {
            d.classList.remove('active');
        });
    });
    
    // ========================================
    // MODAL - Glass morphism effect
    // ========================================
    
    document.querySelectorAll('[data-toggle="modal"]').forEach(trigger => {
        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            const modalId = this.getAttribute('data-target');
            const modal = document.querySelector(modalId);
            
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Animate modal content
                setTimeout(() => {
                    const content = modal.querySelector('.modal-content');
                    if (content) {
                        content.style.transform = 'scale(1) translateY(0)';
                    }
                }, 10);
            }
        });
    });
    
    document.querySelectorAll('.modal-close, .modal').forEach(element => {
        element.addEventListener('click', function(e) {
            if (e.target === this || this.classList.contains('modal-close')) {
                const modal = this.closest('.modal') || this;
                const content = modal.querySelector('.modal-content');
                
                if (content) {
                    content.style.transform = 'scale(0.9) translateY(20px)';
                }
                
                setTimeout(() => {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }, 300);
            }
        });
    });
    
    // ========================================
    // FILE UPLOAD - Drag and drop animation
    // ========================================
    
    document.querySelectorAll('.file-upload-zone').forEach(zone => {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            zone.classList.add('drag-over');
        }
        
        function unhighlight(e) {
            zone.classList.remove('drag-over');
        }
        
        zone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            // Handle files
            handleFiles(files);
        }
        
        function handleFiles(files) {
            ([...files]).forEach(uploadFile);
        }
        
        function uploadFile(file) {
            // Trigger file upload
            const event = new CustomEvent('fileselected', { detail: file });
            zone.dispatchEvent(event);
        }
    });
    
    // ========================================
    // PROGRESS BARS - Auto animate on visible
    // ========================================
    
    const observerOptions = {
        threshold: 0.5,
        rootMargin: '0px'
    };
    
    const progressObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const progressBar = entry.target.querySelector('.progress-bar');
                if (progressBar) {
                    const targetWidth = progressBar.getAttribute('data-width') || '70%';
                    progressBar.style.width = targetWidth;
                }
                progressObserver.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.progress').forEach(progress => {
        progressObserver.observe(progress);
    });
    
    // ========================================
    // TOOLTIPS - Custom implementation
    // ========================================
    
    document.querySelectorAll('[data-tooltip]').forEach(element => {
        const tooltipText = element.getAttribute('data-tooltip');
        
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip-content';
        tooltip.textContent = tooltipText;
        
        element.classList.add('tooltip');
        element.appendChild(tooltip);
    });
    
    // ========================================
    // SMOOTH SCROLL - For anchor links
    // ========================================
    
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // ========================================
    // SKELETON LOADING - Auto remove
    // ========================================
    
    setTimeout(() => {
        document.querySelectorAll('.skeleton').forEach(skeleton => {
            skeleton.classList.add('loaded');
            setTimeout(() => {
                skeleton.classList.remove('skeleton', 'loaded');
            }, 500);
        });
    }, 1500);
    
    // ========================================
    // NOTIFICATION ANIMATIONS
    // ========================================
    
    // Auto-hide notifications after 5 seconds
    document.querySelectorAll('.notification-toast').forEach(toast => {
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease-out forwards';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    });
    
    // ========================================
    // TABLE ROW SELECTION
    // ========================================
    
    document.querySelectorAll('.table tbody tr').forEach(row => {
        row.addEventListener('click', function(e) {
            // Skip if clicking on a button or link
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
            
            // Toggle selection
            this.classList.toggle('selected');
            
            // Add visual feedback
            const feedback = document.createElement('div');
            feedback.className = 'selection-feedback';
            feedback.style.left = e.pageX + 'px';
            feedback.style.top = e.pageY + 'px';
            document.body.appendChild(feedback);
            
            setTimeout(() => feedback.remove(), 500);
        });
    });
    
    // ========================================
    // ANIMATE ON SCROLL
    // ========================================
    
    const fadeObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                fadeObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('.animate-on-scroll').forEach(element => {
        fadeObserver.observe(element);
    });
});

// ========================================
// UTILITY FUNCTIONS
// ========================================

// Show loading state on buttons
function setButtonLoading(button, loading = true) {
    if (loading) {
        button.classList.add('btn-loading');
        button.disabled = true;
    } else {
        button.classList.remove('btn-loading');
        button.disabled = false;
    }
}

// Create toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `notification-toast toast-${type} fade-in`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
            <button class="toast-close">&times;</button>
        </div>
    `;
    
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    container.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        toast.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
    
    // Manual close
    toast.querySelector('.toast-close').addEventListener('click', () => {
        toast.style.animation = 'fadeOut 0.3s ease-out forwards';
        setTimeout(() => toast.remove(), 300);
    });
}

// Export functions for global use
window.NexioRedesign = {
    setButtonLoading,
    showToast
};