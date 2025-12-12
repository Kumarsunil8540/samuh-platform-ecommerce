// header.js - Enhanced Version

document.addEventListener('DOMContentLoaded', () => {
    // 1. ड्रॉपडाउन टॉगल फ़ंक्शन
    const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.stopPropagation(); // इवेंट बबलिंग रोकें
            
            const parentLi = toggle.closest('.nav-dropdown-parent');
            const dropdownMenu = parentLi ? parentLi.querySelector('.dropdown-menu') : null;

            if (dropdownMenu) {
                // सभी खुले हुए मेनू को बंद करें
                closeAllDropdowns();
                
                // वर्तमान मेनू को टॉगल करें
                dropdownMenu.classList.toggle('active');
                
                // बटन का एक्टिव स्टेट मैनेज करें
                toggle.classList.toggle('active');
            }
        });
    });

    // 2. मेनू के बाहर क्लिक करने पर ड्रॉपडाउन बंद करना
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.nav-dropdown-parent')) {
            closeAllDropdowns();
        }
    });

    // 3. हैमबर्गर मेनू टॉगल (मोबाइल के लिए)
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');

    if (hamburger && navLinks) {
        hamburger.addEventListener('click', (e) => {
            e.stopPropagation();
            
            navLinks.classList.toggle('active');
            hamburger.classList.toggle('active');
            
            // मोबाइल मेनू खुला हो तो ड्रॉपडाउन बंद करें
            if (!navLinks.classList.contains('active')) {
                closeAllDropdowns();
            }
        });
    }

    // 4. कीबोर्ड एक्सेसिबिलिटी (ESC key से मेनू बंद करें)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllDropdowns();
            if (navLinks) {
                navLinks.classList.remove('active');
                hamburger.classList.remove('active');
            }
        }
    });

    // 5. विंडो रीसाइज पर मोबाइल मेनू बंद करें
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            if (navLinks) {
                navLinks.classList.remove('active');
                hamburger.classList.remove('active');
            }
        }
    });

    // 6. सभी ड्रॉपडाउन बंद करने का फ़ंक्शन
    function closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu.active').forEach(menu => {
            menu.classList.remove('active');
        });
        
        document.querySelectorAll('.nav-dropdown-toggle.active').forEach(toggle => {
            toggle.classList.remove('active');
        });
    }

    // 7. स्मूथ स्क्रॉलिंग (वैकल्पिक)
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const href = this.getAttribute('href');
            
            if (href !== '#' && href.startsWith('#')) {
                e.preventDefault();
                const target = document.querySelector(href);
                
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });

    console.log('Samuh Header - Enhanced version loaded successfully!');
});