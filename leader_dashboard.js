// leader_dashboard.js - Mobile Optimized with Hindi-English Support

document.addEventListener('DOMContentLoaded', function () {
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
    const memberTable = document.getElementById('memberTable');
    
    if (searchInput && memberTable) {
        // Add debounce for better performance on mobile
        let searchTimeout;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value.trim().toLowerCase();
                const rows = memberTable.querySelectorAll('tbody tr');
                let visibleCount = 0;
                
                rows.forEach(row => {
                    const rowText = row.textContent.toLowerCase();
                    const shouldShow = rowText.includes(query);
                    row.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) visibleCount++;
                });
                
                // Show no results message if needed
                const noResults = memberTable.querySelector('.no-results');
                if (visibleCount === 0 && query !== '') {
                    if (!noResults) {
                        const noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results';
                        noResultsRow.innerHTML = `<td colspan="8" class="no-data">
                            <span class="hindi">❌ कोई परिणाम नहीं मिला</span>
                            <span class="english">❌ No results found</span>
                        </td>`;
                        memberTable.querySelector('tbody').appendChild(noResultsRow);
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
    if (memberTable) {
        let startX;
        let scrollLeft;
        
        memberTable.addEventListener('touchstart', function(e) {
            startX = e.touches[0].pageX - this.offsetLeft;
            scrollLeft = this.scrollLeft;
        });
        
        memberTable.addEventListener('touchmove', function(e) {
            if (!startX) return;
            const x = e.touches[0].pageX - this.offsetLeft;
            const walk = (x - startX) * 2;
            this.scrollLeft = scrollLeft - walk;
        });
    }

    // Notifications auto-scroll with pause on hover/touch
    const notifList = document.getElementById('notifList');
    if (notifList && notifList.children.length > 2) {
        let scrollPosition = 0;
        let isPaused = false;
        let scrollInterval;
        
        function startScrolling() {
            scrollInterval = setInterval(() => {
                if (!isPaused) {
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

    // Navigation item click handlers
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // Remove active class from all items
            navItems.forEach(nav => nav.classList.remove('active'));
            // Add active class to clicked item
            this.classList.add('active');
        });
    });

    // Real-time data updates (simulated) - Mobile optimized
    function updateDashboardData() {
        // This would typically fetch data from an API
        console.log('Updating dashboard data...');
        
        // Show loading state
        const cards = document.querySelectorAll('.card-value');
        cards.forEach(card => {
            card.classList.add('loading');
        });
        
        // Simulate API call
        setTimeout(() => {
            cards.forEach(card => {
                card.classList.remove('loading');
                card.style.color = '#10b981';
                setTimeout(() => {
                    card.style.color = '#0f172a';
                }, 1000);
            });
        }, 500);
    }

    // Update data every 30 seconds
    setInterval(updateDashboardData, 30000);

    // Mobile-optimized keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + S for search focus
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Escape to clear search and close sidebar
        if (e.key === 'Escape') {
            if (searchInput) {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
            }
            if (sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }
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
        console.log(`Dashboard initialized in ${isMobile ? 'mobile' : 'desktop'} mode`);
        
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
                <span class="hindi">✅ डैशबोर्ड लोड हो गया!</span>
                <span class="english">✅ Dashboard loaded!</span>
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
});