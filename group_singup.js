// group_signup.js - Enhanced Version

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('groupCreateForm');
    if (!form) {
        console.error("Form with ID 'groupCreateForm' not found.");
        return;
    }

    // Helper function to display error messages
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
        
        // Add red border
        inputElement.style.borderColor = '#dc3545';
    }

    // Helper function to clear error messages
    function clearError(inputElement) {
        const errorId = inputElement.id + '_error';
        const errorEl = document.getElementById(errorId);
        
        if (errorEl) {
            errorEl.style.display = 'none';
        }
        
        inputElement.classList.remove('is-invalid');
        inputElement.style.borderColor = '';
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
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
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

    function validateNumber(input, min, max) {
        const value = parseFloat(input.value);
        if (isNaN(value) || value < min || value > max) {
            displayError(input, `Must be between ${min} and ${max}.`);
            return false;
        }
        return true;
    }

    function validateFile(input) {
        if (input.files.length === 0) {
            displayError(input, 'Please upload the required file.');
            return false;
        }

        const file = input.files[0];
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        const maxSize = 5 * 1024 * 1024; // 5MB

        if (!allowedTypes.includes(file.type)) {
            displayError(input, 'Only PDF, JPG, and PNG files are allowed.');
            return false;
        }

        if (file.size > maxSize) {
            displayError(input, 'File size must be less than 5MB.');
            return false;
        }

        return true;
    }

    // Main validation function
    function validateForm() {
        let isValid = true;

        // Group Details Validation
        if (!validateRequired(document.getElementById('group_name'))) isValid = false;
        if (!validateNumber(document.getElementById('tenure_months'), 6, 60)) isValid = false;
        if (!validateNumber(document.getElementById('expected_amount'), 100, 1000000)) isValid = false;
        if (!validateNumber(document.getElementById('min_members_count'), 2, 50)) isValid = false;
        if (!validateRequired(document.getElementById('group_conditions'))) isValid = false;
        if (!validateFile(document.getElementById('stamp_upload'))) isValid = false;

        // Core Members Validation
        const coreMembers = [
            { prefix: 'owner', name: 'Owner' },
            { prefix: 'admin', name: 'Admin' },
            { prefix: 'accountant', name: 'Accountant' }
        ];

        coreMembers.forEach(member => {
            if (!validateRequired(document.getElementById(member.prefix + '_name'))) isValid = false;
            if (!validateMobile(document.getElementById(member.prefix + '_mobile'))) isValid = false;
            if (!validateEmail(document.getElementById(member.prefix + '_email'))) isValid = false;
        });

        return isValid;
    }

    // Real-time validation
    function setupRealTimeValidation() {
        const inputs = form.querySelectorAll('input, textarea, select');
        
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
                    case 'number':
                        if (this.id === 'tenure_months') validateNumber(this, 6, 60);
                        else if (this.id === 'expected_amount') validateNumber(this, 100, 1000000);
                        else if (this.id === 'min_members_count') validateNumber(this, 2, 50);
                        break;
                    case 'file':
                        if (this.files.length > 0) validateFile(this);
                        break;
                }
            });
        });
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

    // Add some interactive enhancements
    function enhanceFormInteractivity() {
        // Add character counter for textarea
        const textarea = document.getElementById('group_conditions');
        if (textarea) {
            const counter = document.createElement('div');
            counter.style.textAlign = 'right';
            counter.style.fontSize = '0.8em';
            counter.style.color = '#6c757d';
            counter.style.marginTop = '5px';
            textarea.parentNode.appendChild(counter);

            textarea.addEventListener('input', function() {
                const length = this.value.length;
                counter.textContent = `${length} characters`;
                
                if (length < 10) {
                    counter.style.color = '#dc3545';
                } else if (length < 50) {
                    counter.style.color = '#ffc107';
                } else {
                    counter.style.color = '#28a745';
                }
            });
        }

        // Add file name display for file input
        const fileInput = document.getElementById('stamp_upload');
        if (fileInput) {
            const fileNameDisplay = document.createElement('div');
            fileNameDisplay.style.marginTop = '5px';
            fileNameDisplay.style.fontSize = '0.9em';
            fileNameDisplay.style.color = '#28a745';
            fileInput.parentNode.appendChild(fileNameDisplay);

            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileNameDisplay.textContent = `Selected: ${this.files[0].name}`;
                } else {
                    fileNameDisplay.textContent = '';
                }
            });
        }
    }

    enhanceFormInteractivity();
    console.log('Group Signup Form - Enhanced validation loaded!');
});