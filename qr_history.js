// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const historyGrid = document.getElementById('historyGrid');
    
    if (searchInput && historyGrid) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = historyGrid.querySelectorAll('.history-card');
            
            cards.forEach(card => {
                const uploader = card.getAttribute('data-uploader');
                if (uploader.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
});

// Modal functionality
let currentQRImage = '';

function viewQR(imageSrc) {
    currentQRImage = imageSrc;
    const modal = document.getElementById('qrModal');
    const modalImage = document.getElementById('modalQrImage');
    
    modalImage.src = imageSrc;
    modal.classList.add('show');
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('qrModal');
    modal.classList.remove('show');
    
    // Restore body scroll
    document.body.style.overflow = 'auto';
}

function downloadQR() {
    if (currentQRImage) {
        const link = document.createElement('a');
        link.href = currentQRImage;
        link.download = 'qr_code_' + Date.now() + '.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Close modal on outside click
document.addEventListener('click', function(e) {
    const modal = document.getElementById('qrModal');
    if (e.target === modal) {
        closeModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// Add some animations
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.history-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.6s ease';
        
        setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
});