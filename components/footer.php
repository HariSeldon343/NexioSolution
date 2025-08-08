        </main>
    </div>

    <!-- Notifiche Toast -->
    <div id="toast-container"></div>

    <!-- Bootstrap JavaScript Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Sistema di conferma cancellazione con cache busting -->
    <?php 
    $confirm_js_version = file_exists(dirname(__DIR__) . '/assets/js/confirm-delete.js') ? 
                          filemtime(dirname(__DIR__) . '/assets/js/confirm-delete.js') : time(); 
    ?>
    <script src="<?php echo APP_PATH; ?>/assets/js/confirm-delete.js?v=<?php echo $confirm_js_version; ?>"></script>

    <script>
    // Sistema notifiche toast
    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
            <button class="toast-close" onclick="closeToast(this)">&times;</button>
        `;
        
        container.appendChild(toast);
        
        // Auto-rimuovi dopo 5 secondi
        setTimeout(() => {
            if (toast.parentNode) {
                closeToast(toast.querySelector('.toast-close'));
            }
        }, 5000);
    }
    
    function closeToast(btn) {
        const toast = btn.parentElement;
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }
    
    // Mostra notifiche di sessione
    <?php if (isset($_SESSION['success'])): ?>
        showToast('<?= addslashes($_SESSION['success']) ?>', 'success');
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        showToast('<?= addslashes($_SESSION['error']) ?>', 'error');
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['info'])): ?>
        showToast('<?= addslashes($_SESSION['info']) ?>', 'info');
        <?php unset($_SESSION['info']); ?>
    <?php endif; ?>
    </script>

    <style>
    #toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .toast {
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 300px;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideIn 0.3s ease forwards;
        font-size: 14px;
        font-weight: 500;
    }
    
    .toast-success {
        background: #c6f6d5;
        color: #22543d;
        border-left: 4px solid #48bb78;
    }
    
    .toast-error {
        background: #fed7d7;
        color: #742a2a;
        border-left: 4px solid #e53e3e;
    }
    
    .toast-info {
        background: #bee3f8;
        color: #2b6cb0;
        border-left: 4px solid #4299e1;
    }
    
    .toast-content {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
    }
    
    .toast-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        opacity: 0.7;
        transition: opacity 0.2s ease;
        margin-left: 15px;
    }
    
    .toast-close:hover {
        opacity: 1;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    @media (max-width: 768px) {
        #toast-container {
            top: 10px;
            right: 10px;
            left: 10px;
        }
        
        .toast {
            min-width: auto;
            width: 100%;
        }
    }
    </style>
</body>
</html>