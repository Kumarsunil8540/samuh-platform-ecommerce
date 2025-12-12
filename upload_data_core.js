// upload_data_core.js - Enhanced Validation

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('kycForm');
    if (!form) return;

    // Helper functions for validation
    function displayError(inputElement, message) {
        const errorId = inputElement.id + '_error';
        let errorEl = document.getElementById(errorId);
        
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.id = errorId;
            errorEl.classList.add('error-message');
            inputElement.parentNode.insertBefore(errorEl, inputElement.nextSibling);
        }

        errorEl.textContent = message;
        errorEl.style.display = 'block';
        inputElement.classList.add('is-invalid');
    }

    function clearError(inputElement) {
        const errorId = inputElement.id + '_error';
        const errorEl = document.getElementById(errorId);
        
        if (errorEl) {
            errorEl.style.display = 'none';
        }
        
        inputElement.classList.remove('is-invalid');
    }

    // Validation functions
    function validateRequired(input) {
        if (!input.value.trim()) {
            displayError(input, 'This field is required.');
            return false;
        }
        return true;
    }

    function validateEmail(input) {
        const email = input.value.trim();
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email && !emailPattern.test(email)) {
            displayError(input, 'Please enter a valid email address.');
            return false;
        }
        return true;
    }

    function validateMobile(input) {
        const mobile = input.value.trim();
        if (!/^[0-9]{10}$/.test(mobile)) {
            displayError(input, 'Mobile number must be exactly 10 digits.');
            return false;
        }
        return true;
    }

    function validatePAN(input) {
        const pan = input.value.trim();
        const panPattern = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
        
        if (pan && !panPattern.test(pan)) {
            displayError(input, 'Please enter a valid PAN number (e.g., ABCDE1234F).');
            return false;
        }
        return true;
    }

    function validatePassword(input) {
        const password = input.value;
        if (password.length < 6) {
            displayError(input, 'Password must be at least 6 characters long.');
            return false;
        }
        return true;
    }

    function validateFile(input, maxSizeMB) {
        if (input.files.length === 0) {
            displayError(input, 'Please upload the required file.');
            return false;
        }

        const file = input.files[0];
        const maxSize = maxSizeMB * 1024 * 1024;

        if (file.size > maxSize) {
            displayError(input, `File size must be less than ${maxSizeMB}MB.`);
            return false;
        }

        return true;
    }

    function validateDate(input) {
        const selectedDate = new Date(input.value);
        const currentDate = new Date();
        
        if (selectedDate > currentDate) {
            displayError(input, 'Date of birth cannot be in the future.');
            return false;
        }
        return true;
    }

    // Real-time validation setup
    function setupRealTimeValidation() {
        const inputs = form.querySelectorAll('input, textarea');
        
        inputs.forEach(input => {
            // Clear errors on input
            input.addEventListener('input', function() {
                clearError(this);
            });

            // Validate on blur
            input.addEventListener('blur', function() {
                switch (this.type) {
                    case 'email':
                        validateEmail(this);
                        break;
                    case 'tel':
                        if (this.value.trim()) validateMobile(this);
                        break;
                    case 'password':
                        if (this.value.trim()) validatePassword(this);
                        break;
                    case 'date':
                        if (this.value) validateDate(this);
                        break;
                    case 'file':
                        if (this.files.length > 0) {
                            const maxSize = this.id.includes('profile_photo') || 
                                          this.id.includes('signature') ? 2 : 5;
                            validateFile(this, maxSize);
                        }
                        break;
                }
                
                // Special validation for PAN number
                if (this.id === 'pan_number' && this.value.trim()) {
                    validatePAN(this);
                }
            });
        });
    }

    // Main form validation
    function validateForm() {
        let isValid = true;

        // Login Information
        if (!validateRequired(document.getElementById('username'))) isValid = false;
        if (!validatePassword(document.getElementById('password'))) isValid = false;

        // Personal Information
        if (!validateRequired(document.getElementById('full_name'))) isValid = false;
        if (!validateMobile(document.getElementById('mobile'))) isValid = false;
        if (!validateEmail(document.getElementById('email'))) isValid = false;
        if (!validateRequired(document.getElementById('dob'))) isValid = false;
        if (!validateDate(document.getElementById('dob'))) isValid = false;
        if (!validateRequired(document.getElementById('address'))) isValid = false;

        // KYC Documents
        if (!validatePAN(document.getElementById('pan_number'))) isValid = false;
        if (!validateFile(document.getElementById('pan_proof'), 5)) isValid = false;
        if (!validateFile(document.getElementById('aadhaar_proof'), 5)) isValid = false;
        if (!validateFile(document.getElementById('bank_proof'), 5)) isValid = false;
        if (!validateFile(document.getElementById('signature_proof'), 2)) isValid = false;
        if (!validateFile(document.getElementById('profile_photo'), 2)) isValid = false;

        return isValid;
    }

    // Form submission handler
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Clear all previous errors
        const errorElements = form.querySelectorAll('.error-message');
        errorElements.forEach(error => error.style.display = 'none');
        
        const invalidInputs = form.querySelectorAll('.is-invalid');
        invalidInputs.forEach(input => input.classList.remove('is-invalid'));

        // Validate form
        if (validateForm()) {
            // Show loading state
            const submitBtn = form.querySelector('.btn-submit');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="hindi">जमा हो रहा है...</span><span class="english">Submitting...</span>';
            submitBtn.disabled = true;

            // Submit form after short delay to show loading state
            setTimeout(() => {
                form.submit();
            }, 1000);
        } else {
            // Scroll to first error
            const firstError = form.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                firstError.focus();
            }
        }
    });

    // Initialize real-time validation
    setupRealTimeValidation();

    // Additional enhancements
    function enhanceFormInteractivity() {
        // Add file name display
        const fileInputs = form.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            const fileNameDisplay = document.createElement('div');
            fileNameDisplay.style.marginTop = '5px';
            fileNameDisplay.style.fontSize = '0.9em';
            fileNameDisplay.style.color = '#28a745';
            input.parentNode.appendChild(fileNameDisplay);

            input.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileNameDisplay.textContent = `Selected: ${this.files[0].name}`;
                } else {
                    fileNameDisplay.textContent = '';
                }
            });
        });

        // Mobile number formatting
        const mobileInput = document.getElementById('mobile');
        if (mobileInput) {
            mobileInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }
            });
        }

        // PAN number uppercase
        const panInput = document.getElementById('pan_number');
        if (panInput) {
            panInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        }
    }

    enhanceFormInteractivity();
    console.log('KYC Form - Enhanced validation loaded!');
});