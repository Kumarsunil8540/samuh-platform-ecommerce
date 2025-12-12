// member_dashboard.js - Mobile Optimized

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 500);
        }, 5000);
    });

    // Add touch feedback to action cards
    const actionCards = document.querySelectorAll('.action-card');
    actionCards.forEach(card => {
        card.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        
        card.addEventListener('touchend', function() {
            this.style.transform = '';
        });
    });

    // Handle navigation item clicks
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Add active state
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Add loading state to action cards
    actionCards.forEach(card => {
        card.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
            }
            
            // Show loading state
            const originalHTML = this.innerHTML;
            this.innerHTML = `
                <div class="card-icon">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <h3>
                    <span class="hindi">लोड हो रहा है...</span>
                    <span class="english">Loading...</span>
                </h3>
            `;
            this.style.pointerEvents = 'none';
            
            // Restore after 2 seconds
            setTimeout(() => {
                this.innerHTML = originalHTML;
                this.style.pointerEvents = 'auto';
            }, 2000);
        });
    });

    // Handle scroll to top for better mobile UX
    let lastScrollTop = 0;
    const header = document.querySelector('.dashboard-header');
    
    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scroll down
            header.style.transform = 'translateY(-100%)';
        } else {
            // Scroll up
            header.style.transform = 'translateY(0)';
        }
        
        lastScrollTop = scrollTop;
    });

    // Add pull-to-refresh functionality for mobile
    let touchStartY = 0;
    let touchEndY = 0;

    document.addEventListener('touchstart', function(e) {
        touchStartY = e.changedTouches[0].screenY;
    });

    document.addEventListener('touchend', function(e) {
        touchEndY = e.changedTouches[0].screenY;
        handleSwipe();
    });

    function handleSwipe() {
        const swipeDistance = touchEndY - touchStartY;
        
        // Pull to refresh - only at the top of the page
        if (swipeDistance > 100 && window.pageYOffset === 0) {
            location.reload();
        }
    }

    // Keyboard shortcuts for better accessibility
    document.addEventListener('keydown', function(e) {
        // Alt + 1 for payment
        if (e.altKey && e.key === '1') {
            e.preventDefault();
            document.querySelector('.payment-card').click();
        }
        
        // Alt + 2 for history
        if (e.altKey && e.key === '2') {
            e.preventDefault();
            document.querySelector('.history-card').click();
        }
        
        // Escape to close any modals (if any)
        if (e.key === 'Escape') {
            // Close any open modals here
        }
    });

    // Initialize the dashboard
    console.log('Member Dashboard initialized successfully!');
    
    // Show welcome toast
    setTimeout(() => {
        showToast('Dashboard loaded successfully!', 'success');
    }, 1000);
});

// Toast notification system
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <div class="toast-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Add styles for toast
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
        color: ${type === 'success' ? '#155724' : '#721c24'};
        padding: 12px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 1000;
        animation: slideIn 0.3s ease;
        max-width: 300px;
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.5s ease';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 500);
    }, 3000);
}

// Add CSS for animations
const style = document.createElement('style');
style.textContent = `
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
    
    .nav-item.active {
        background: var(--secondary) !important;
        transform: scale(1.05);
    }
    
    .fa-spin {
        animation: fa-spin 1s infinite linear;
    }
    
    @keyframes fa-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);