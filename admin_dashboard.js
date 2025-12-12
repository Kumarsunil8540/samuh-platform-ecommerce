// admin_dashboard.js - Mobile Optimized with Hindi-English Support

document.addEventListener('DOMContentLoaded', () => {
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
    const requestsTable = document.getElementById('requestsTable');
    
    if (searchInput && requestsTable) {
        // Add debounce for better performance on mobile
        let searchTimeout;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value.trim().toLowerCase();
                const rows = requestsTable.querySelectorAll('tbody tr');
                let visibleCount = 0;
                
                rows.forEach(row => {
                    const rowText = row.textContent.toLowerCase();
                    const shouldShow = rowText.includes(query);
                    row.style.display = shouldShow ? '' : 'none';
                    if (shouldShow) visibleCount++;
                });
                
                // Show no results message if needed
                const noResults = requestsTable.querySelector('.no-results');
                if (visibleCount === 0 && query !== '') {
                    if (!noResults) {
                        const noResultsRow = document.createElement('tr');
                        noResultsRow.className = 'no-results';
                        noResultsRow.innerHTML = `<td colspan="10" class="no-data">
                            <span class="hindi">❌ कोई परिणाम नहीं मिला</span>
                            <span class="english">❌ No results found</span>
                        </td>`;
                        requestsTable.querySelector('tbody').appendChild(noResultsRow);
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
    if (requestsTable) {
        let startX;
        let scrollLeft;
        
        const tableWrap = requestsTable.closest('.table-wrap');
        
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

    // Modal functionality - Mobile optimized
    const modal = document.getElementById('reqModal');
    const modalName = document.getElementById('modalName');
    const modalBody = document.getElementById('modalBody');
    const modalClose = document.getElementById('modalClose');
    const rejectForm = document.getElementById('rejectForm');
    const rejectRequestId = document.getElementById('reject_request_id');

    // View button click handler - Mobile optimized
    document.querySelectorAll('.btn-view').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const reqJson = btn.getAttribute('data-req');
            if (!reqJson) return;
            const req = JSON.parse(reqJson);

            modalName.innerHTML = `
                <span class="hindi">${req.full_name} — अनुरोध #${req.id}</span>
                <span class="english">${req.full_name} — Request #${req.id}</span>
            `;

            let html = '<div style="overflow-x: auto;">';
            html += '<table style="width:100%;border-collapse:collapse;font-size:14px;min-width:300px;">';
            html += `<tr><td style="padding:8px;font-weight:600;width:120px">मोबाइल / Mobile</td><td style="padding:8px">${req.mobile}</td></tr>`;
            html += `<tr><td style="padding:8px;font-weight:600">ईमेल / Email</td><td style="padding:8px">${req.email || '—'}</td></tr>`;
            html += `<tr><td style="padding:8px;font-weight:600">जन्मतिथि / DOB</td><td style="padding:8px">${req.dob || '—'}</td></tr>`;
            html += `<tr><td style="padding:8px;font-weight:600">लिंग / Gender</td><td style="padding:8px">${req.gender || '—'}</td></tr>`;
            html += `<tr><td style="padding:8px;font-weight:600">पता / Address</td><td style="padding:8px">${req.address || '—'}</td></tr>`;
            html += `<tr><td style="padding:8px;font-weight:600">नॉमिनी / Nominee</td><td style="padding:8px">${req.nominee_name || '—'}</td></tr>`;
            html += '</table></div>';

            // Files preview - Mobile optimized
            html += '<div style="margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9">';
            html += '<h4 style="margin-bottom:12px;font-size:15px;">दस्तावेज़ / Documents</h4>';
            html += '<div style="display:flex;flex-direction:column;gap:8px;">';
            if (req.aadhaar_proof_path) 
                html += `<a href="${req.aadhaar_proof_path}" target="_blank" style="color:var(--accent-2);text-decoration:none;padding:8px;background:#f8fbff;border-radius:6px;">🆔 आधार प्रूफ / Aadhaar Proof</a>`;
            if (req.pan_proof_path) 
                html += `<a href="${req.pan_proof_path}" target="_blank" style="color:var(--accent-2);text-decoration:none;padding:8px;background:#f8fbff;border-radius:6px;">📄 पैन प्रूफ / PAN Proof</a>`;
            if (req.photo_path) 
                html += `<a href="${req.photo_path}" target="_blank" style="color:var(--accent-2);text-decoration:none;padding:8px;background:#f8fbff;border-radius:6px;">📷 फोटो / Photo</a>`;
            if (req.signature_path) 
                html += `<a href="${req.signature_path}" target="_blank" style="color:var(--accent-2);text-decoration:none;padding:8px;background:#f8fbff;border-radius:6px;">✍️ हस्ताक्षर / Signature</a>`;
            html += '</div></div>';

            modalBody.innerHTML = html;
            rejectRequestId.value = req.id;
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
    });

    // Modal close
    if (modalClose) {
        modalClose.addEventListener('click', () => {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        });
    }

    // Reject buttons - Mobile optimized
    document.querySelectorAll('.btn-reject').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const id = btn.getAttribute('data-id');
            rejectRequestId.value = id;
            modalName.innerHTML = `
                <span class="hindi">अनुरोध अस्वीकार #${id}</span>
                <span class="english">Reject Request #${id}</span>
            `;
            modalBody.innerHTML = `
                <p class="hindi" style="margin-bottom:12px;">नीचे अस्वीकरण कारण दर्ज करें (वैकल्पिक) और पुष्टि करें।</p>
                <p class="english" style="margin-bottom:12px;">Enter rejection reason below (optional) and confirm.</p>
            `;
            modal.style.display = 'flex';
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modal when clicking outside
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }
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

    // Confirmation for approve/reject actions - Mobile optimized
    document.querySelectorAll('form').forEach(form => {
        const approveBtn = form.querySelector('.btn-approve');
        if (approveBtn) {
            form.addEventListener('submit', function(e) {
                if (!confirm('क्या आप इस अनुरोध को स्वीकार करना चाहते हैं? / Do you want to approve this request?')) {
                    e.preventDefault();
                }
            });
        }
    });

    rejectForm.addEventListener('submit', function(e) {
        if (!confirm('क्या आप इस अनुरोध को अस्वीकार करना चाहते हैं? / Do you want to reject this request?')) {
            e.preventDefault();
        }
    });

    // Initialize dashboard with mobile detection
    function initDashboard() {
        const isMobile = window.innerWidth < 768;
        console.log(`Admin Dashboard initialized in ${isMobile ? 'mobile' : 'desktop'} mode`);
        
        // Add action buttons container for mobile
        if (isMobile) {
            document.querySelectorAll('#requestsTable tbody tr').forEach(row => {
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
                <span class="hindi">✅ एडमिन डैशबोर्ड तैयार!</span>
                <span class="english">✅ Admin Dashboard Ready!</span>
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
            if (modal.style.display !== 'none') {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }
            if (sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }
    });
});