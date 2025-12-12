// accountant_dashboard.js - Mobile Optimized with Hindi-English Support

document.addEventListener('DOMContentLoaded', function() {
    // Mobile sidebar functionality
    const sidebar = document.getElementById('sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    
    function toggleSidebar() {
        const isActive = sidebar.classList.contains('active');
        
        if (isActive) {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        } else {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        // Update toggle button icon
        if (toggle) {
            toggle.textContent = isActive ? '☰' : '✕';
        }
    }
    
    if (toggle && sidebar) {
        toggle.addEventListener('click', toggleSidebar);
    }
    
    // Close sidebar when clicking on overlay
    overlay.addEventListener('click', toggleSidebar);
    
    // Close sidebar when clicking on nav links (mobile)
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 768) {
                toggleSidebar();
            }
        });
    });

    // Table search functionality - Mobile optimized
    const searchInput = document.getElementById('searchInput');
    const paymentsTable = document.getElementById('paymentsTable');
    
    if (searchInput && paymentsTable) {
        // Add debounce for better performance on mobile
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value.trim().toLowerCase();
                const rows = paymentsTable.querySelectorAll('tbody tr');
                let visibleCount = 0;
                
                rows.forEach(row => {
                    const rowText = row.textContent.toLowerCase();
                    const shouldShow = rowText.includes(query);
                    row.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) visibleCount++;
                });
                
                // Show no results message if needed
                const noResults = paymentsTable.querySelector('.no-results');
                if (visibleCount === 0 && query !== '') {
                    if (!noResults) {
                        const noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results';
                        noResultsRow.innerHTML = `<td colspan="12" class="no-data">
                            <span class="hindi">❌ कोई परिणाम नहीं मिला</span>
                            <span class="english">❌ No results found</span>
                        </td>`;
                        paymentsTable.querySelector('tbody').appendChild(noResultsRow);
                    }
                } else if (noResults) {
                    noResults.remove();
                }
            }, 300);
        });
        
        // Clear search on escape
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                this.dispatchEvent(new Event('input'));
                this.blur();
            }
        });
    }

    // Touch-friendly table scrolling
    if (paymentsTable) {
        let startX;
        let scrollLeft;
        
        const tableWrap = paymentsTable.closest('.table-wrap');
        
        tableWrap.addEventListener('touchstart', function(e) {
            startX = e.touches[0].pageX - this.offsetLeft;
            scrollLeft = this.scrollLeft;
            this.style.cursor = 'grabbing';
        });
        
        tableWrap.addEventListener('touchmove', function(e) {
            if (!startX) return;
            const x = e.touches[0].pageX - this.offsetLeft;
            const walk = (x - startX) * 2;
            this.scrollLeft = scrollLeft - walk;
            e.preventDefault();
        });
        
        tableWrap.addEventListener('touchend', function() {
            startX = null;
            this.style.cursor = 'grab';
        });
    }

    // Reject Payment Modal - Mobile optimized
    const rejectModal = document.getElementById('rejectModal');
    const modalClose = document.getElementById('modalClose');
    const cancelReject = document.getElementById('cancelReject');
    const rejectForm = document.getElementById('rejectForm');
    const rejectPaymentId = document.getElementById('reject_payment_id');
    const rejectReason = document.getElementById('reject_reason');

    // Reject button click handlers - Mobile optimized
    document.querySelectorAll('.btn-reject').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const paymentId = btn.getAttribute('data-payment-id');
            rejectPaymentId.value = paymentId;
            rejectReason.value = ''; // Clear previous reason
            rejectModal.style.display = 'flex';
            rejectModal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            
            // Focus on reason textarea
            setTimeout(() => {
                rejectReason.focus();
            }, 300);
        });
    });

    // Modal close handlers
    if (modalClose) {
        modalClose.addEventListener('click', () => {
            rejectModal.style.display = 'none';
            rejectModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        });
    }

    if (cancelReject) {
        cancelReject.addEventListener('click', () => {
            rejectModal.style.display = 'none';
            rejectModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        });
    }

    // Close modal when clicking outside
    rejectModal.addEventListener('click', (e) => {
        if (e.target === rejectModal) {
            rejectModal.style.display = 'none';
            rejectModal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
    });

    // Form submission confirmation - Mobile optimized
    rejectForm.addEventListener('submit', function(e) {
        const reason = rejectReason.value.trim();
        if (!reason) {
            e.preventDefault();
            alert('कृपया अस्वीकरण कारण दर्ज करें / Please enter rejection reason');
            rejectReason.focus();
            return false;
        }
        
        if (!confirm('क्या आप इस भुगतान को अस्वीकार करना चाहते हैं? / Are you sure you want to reject this payment?')) {
            e.preventDefault();
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('.btn-decline');
        if (submitBtn) {
            submitBtn.innerHTML = '<span class="hindi">⏳ प्रोसेस हो रहा है...</span><span class="english">⏳ Processing...</span>';
            submitBtn.disabled = true;
        }
    });

    // Verify button confirmation - Mobile optimized
    document.querySelectorAll('.btn-verify').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('क्या आप इस भुगतान को सत्यापित करना चाहते हैं? / Do you want to verify this payment?')) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="hindi">⏳ सत्यापित हो रहा है...</span><span class="english">⏳ Verifying...</span>';
            this.disabled = true;
            
            // Restore button after 2 seconds if form doesn't submit
            setTimeout(() => {
                if (this.disabled) {
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }
            }, 2000);
        });
    });

    // Notifications auto-scroll with pause on hover/touch
    const notifList = document.getElementById('notifList');
    if (notifList && notifList.children.length > 2) {
        let scrollPosition = 0;
        let isPaused = false;
        let scrollInterval;
        
        function startScrolling() {
            scrollInterval = setInterval(() => {
                if (!isPaused && notifList.scrollHeight > notifList.clientHeight) {
                    scrollPosition += 1;
                    if (scrollPosition >= notifList.scrollHeight - notifList.clientHeight) {
                        scrollPosition = 0;
                    }
                    notifList.scrollTop = scrollPosition;
                }
            }, 3000);
        }
        
        function stopScrolling() {
            clearInterval(scrollInterval);
        }
        
        // Pause on hover
        notifList.addEventListener('mouseenter', () => isPaused = true);
        notifList.addEventListener('mouseleave', () => isPaused = false);
        
        // Pause on touch
        notifList.addEventListener('touchstart', () => isPaused = true);
        notifList.addEventListener('touchend', () => {
            setTimeout(() => isPaused = false, 2000);
        });
        
        startScrolling();
    }

    // Mobile-optimized card interactions
    const cards = document.querySelectorAll('.card.summary');
    cards.forEach(card => {
        // Add touch feedback
        card.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        
        card.addEventListener('touchend', function() {
            this.style.transform = '';
        });
        
        // Hover effects for desktop
        card.addEventListener('mouseenter', function() {
            if (window.innerWidth >= 768) {
                this.style.transform = 'translateY(-4px)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
        });
    });

    // Handle window resize
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            // Auto-close sidebar on resize to desktop
            if (window.innerWidth >= 768 && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }, 250);
    });

    // Touch gesture for swipe to close sidebar
    let touchStartX = 0;
    let touchEndX = 0;
    
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    document.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        const swipeDistance = touchEndX - touchStartX;
        
        // Swipe left to close sidebar
        if (swipeDistance < -swipeThreshold && sidebar.classList.contains('active')) {
            toggleSidebar();
        }
    }

    // Initialize dashboard with mobile detection
    function initDashboard() {
        const isMobile = window.innerWidth < 768;
        console.log(`Accountant Dashboard initialized in ${isMobile ? 'mobile' : 'desktop'} mode`);
        
        // Add action buttons container for mobile
        if (isMobile) {
            document.querySelectorAll('#paymentsTable tbody tr').forEach(row => {
                const actionCell = row.querySelector('td:last-child');
                if (actionCell) {
                    const buttons = actionCell.innerHTML;
                    actionCell.innerHTML = `<div class="action-buttons">${buttons}</div>`;
                }
            });
        }
        
        // Show welcome message
        setTimeout(() => {
            const welcomeMsg = document.createElement('div');
            welcomeMsg.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--success);
                color: white;
                padding: 12px 16px;
                border-radius: 8px;
                box-shadow: var(--soft-shadow);
                z-index: 1000;
                font-weight: 600;
                font-size: 14px;
                max-width: calc(100vw - 40px);
            `;
            welcomeMsg.innerHTML = `
                <span class="hindi">✅ अकाउंटेंट डैशबोर्ड तैयार!</span>
                <span class="english">✅ Accountant Dashboard Ready!</span>
            `;
            document.body.appendChild(welcomeMsg);
            
            setTimeout(() => {
                welcomeMsg.style.opacity = '0';
                welcomeMsg.style.transition = 'opacity 0.5s ease';
                setTimeout(() => {
                    welcomeMsg.remove();
                }, 500);
            }, 2000);
        }, 500);
    }

    // Initialize everything
    initDashboard();
    
    // Add loading state management
    window.addEventListener('beforeunload', function() {
        document.body.classList.add('loading');
    });

    // Keyboard shortcuts - Mobile optimized
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + F for search focus
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape to clear search and close modal/sidebar
        if (e.key === 'Escape') {
            if (searchInput) {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                searchInput.blur();
            }
            if (rejectModal.style.display !== 'none') {
                rejectModal.style.display = 'none';
                rejectModal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
            if (sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }
    });

    // Auto-refresh dashboard every 30 seconds (simulated)
    setInterval(() => {
        // In future, implement AJAX refresh for real-time updates
        console.log('Auto-refreshing accountant dashboard...');
        
        // Simulate data update with visual feedback
        const cards = document.querySelectorAll('.card-value');
        cards.forEach(card => {
            card.style.color = '#10b981';
            setTimeout(() => {
                card.style.color = '#0f172a';
            }, 1000);
        });
    }, 30000);
});