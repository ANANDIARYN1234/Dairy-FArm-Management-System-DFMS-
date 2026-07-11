/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Global JavaScript Functions - Vanilla JS Only
 * =========================================================
 */

// ========== FORM VALIDATION HELPERS ==========

/**
 * Validate email format
 */
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
}

/**
 * Validate phone number (Nepal format)
 */
function validatePhone(phone) {
    const re = /^(\+977)?[9][6-9]\d{8}$/;
    return re.test(phone);
}

/**
 * Validate required field
 */
function validateRequired(value) {
    return value.trim() !== '';
}

/**
 * Validate minimum length
 */
function validateMinLength(value, minLength) {
    return value.length >= minLength;
}

/**
 * Validate number
 */
function validateNumber(value) {
    return !isNaN(value) && value > 0;
}

/**
 * Show error message on form field
 */
function showError(element, message) {
    const formGroup = element.closest('.form-group');
    if (formGroup) {
        formGroup.classList.add('error');
        const errorMsg = formGroup.querySelector('.error-msg');
        if (errorMsg) {
            errorMsg.textContent = message;
        }
    }
}

/**
 * Clear all error messages
 */
function clearErrors() {
    document.querySelectorAll('.error-msg').forEach(el => el.textContent = '');
    document.querySelectorAll('.form-group').forEach(el => el.classList.remove('error'));
}

// ========== UTILITY FUNCTIONS ==========

/**
 * Format currency
 */
function formatCurrency(amount) {
    return 'Rs. ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

/**
 * Format date to readable format
 */
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

/**
 * Confirm action
 */
function confirmAction(message) {
    return confirm(message || 'Are you sure you want to proceed?');
}

/**
 * Show loading spinner
 */
function showLoading(button) {
    if (button) {
        button.disabled = true;
        button.dataset.originalText = button.textContent;
        button.textContent = 'Please wait...';
    }
}

/**
 * Hide loading spinner
 */
function hideLoading(button) {
    if (button && button.dataset.originalText) {
        button.disabled = false;
        button.textContent = button.dataset.originalText;
    }
}

// ========== AJAX HELPERS ==========

/**
 * Make AJAX GET request
 */
async function ajaxGet(url) {
    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        return await response.json();
    } catch (error) {
        console.error('AJAX GET Error:', error);
        return null;
    }
}

/**
 * Make AJAX POST request
 */
async function ajaxPost(url, data) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        
        return await response.json();
    } catch (error) {
        console.error('AJAX POST Error:', error);
        return null;
    }
}

// ========== TABLE HELPERS ==========

/**
 * Search/Filter table
 */
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!input || !table) return;
    
    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) {
            const row = rows[i];
            const text = row.textContent.toLowerCase();
            
            if (text.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

/**
 * Sort table by column
 */
function sortTable(tableId, columnIndex) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
    switching = true;
    dir = "asc";
    
    while (switching) {
        switching = false;
        rows = table.rows;
        
        for (i = 1; i < (rows.length - 1); i++) {
            shouldSwitch = false;
            x = rows[i].getElementsByTagName("TD")[columnIndex];
            y = rows[i + 1].getElementsByTagName("TD")[columnIndex];
            
            if (dir == "asc") {
                if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                    shouldSwitch = true;
                    break;
                }
            } else if (dir == "desc") {
                if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                    shouldSwitch = true;
                    break;
                }
            }
        }
        
        if (shouldSwitch) {
            rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
            switching = true;
            switchcount++;
        } else {
            if (switchcount == 0 && dir == "asc") {
                dir = "desc";
                switching = true;
            }
        }
    }
}

// ========== AUTO-DISMISS ALERTS ==========

/**
 * Auto-dismiss alerts after 5 seconds
 */
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'fadeOut 0.5s ease-out';
            setTimeout(() => {
                alert.remove();
            }, 500);
        }, 5000);
    });
});

// Fade out animation
const style = document.createElement('style');
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
`;
document.head.appendChild(style);

// ========== SMOOTH SCROLL ==========

/**
 * Smooth scroll to element
 */
function scrollToElement(elementId) {
    const element = document.getElementById(elementId);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// ========== PRINT FUNCTION ==========

/**
 * Print specific element
 */
function printElement(elementId) {
    const printContent = document.getElementById(elementId);
    if (!printContent) return;
    
    const windowPrint = window.open('', '', 'height=600,width=800');
    windowPrint.document.write('<html><head><title>Print</title>');
    windowPrint.document.write('<link rel="stylesheet" href="assets/css/style.css">');
    windowPrint.document.write('</head><body>');
    windowPrint.document.write(printContent.innerHTML);
    windowPrint.document.write('</body></html>');
    windowPrint.document.close();
    windowPrint.print();
}

// ========== EXPORT TO CSV ==========

/**
 * Export table to CSV
 */
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push('"' + cols[j].innerText + '"');
        }
        
        csv.push(row.join(','));
    }
    
    downloadCSV(csv.join('\n'), filename);
}

/**
 * Download CSV file
 */
function downloadCSV(csv, filename) {
    const csvFile = new Blob([csv], { type: 'text/csv' });
    const downloadLink = document.createElement('a');
    
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = 'none';
    
    document.body.appendChild(downloadLink);
    downloadLink.click();
    document.body.removeChild(downloadLink);
}

// ========== PREVENT DOUBLE SUBMIT ==========

/**
 * Prevent double form submission
 */
// document.addEventListener('DOMContentLoaded', function() {
//     const forms = document.querySelectorAll('form');
    
//     forms.forEach(form => {
//         form.addEventListener('submit', function(e) {
//             const submitBtn = this.querySelector('button[type="submit"]');
//             if (submitBtn && !submitBtn.disabled) {
//                 showLoading(submitBtn);
//             }
//         });
//     });
// });

// Global loading functions (add if missing)
function showLoading(btn) {
    if (!btn) return;
    btn.originalText = btn.innerHTML;  // Save original text
    btn.innerHTML = '<span class="spinner">⏳</span> Please wait...';  // Or just 'Please wait...' if no spinner
    btn.disabled = true;
    btn.style.opacity = '0.7';
    // Optional: Show global overlay, e.g., document.getElementById('globalLoader').style.display = 'block';
}

function hideLoading(btn) {
    if (!btn || !btn.originalText) return;
    btn.innerHTML = btn.originalText;
    btn.disabled = false;
    btn.style.opacity = '1';
    btn.originalText = null;  // Clean up
    // Optional: Hide global overlay
}

// ========== PREVENT DOUBLE SUBMIT ==========
/**
 * Prevent double form submission with validation-aware loading
 */
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
   
    forms.forEach(form => {
        let isSubmitting = false;  // Flag to prevent double-submit
        
        form.addEventListener('submit', function(e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            if (!submitBtn || submitBtn.disabled) {
                e.preventDefault();
                return false;
            }
            
            // Quick HTML5 validity check
            if (!this.checkValidity()) {
                // Trigger browser's built-in error display (e.g., red outlines on required fields)
                this.reportValidity();
                return;  // Don't show loading on basic invalid
            }
            
            // For custom validation: Give 50ms for per-form handlers to run and potentially preventDefault/hide
            setTimeout(() => {
                if (!isSubmitting && !submitBtn.disabled) {
                    isSubmitting = true;
                    showLoading(submitBtn);
                }
            }, 50);
            
            // Stop other global handlers from interfering
            e.stopImmediatePropagation();
        });
        
        // Reset flag on form reset (e.g., if user fixes errors)
        form.addEventListener('reset', function() {
            isSubmitting = false;
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) hideLoading(submitBtn);
        });
    });
});

// ========== SESSION TIMEOUT WARNING ==========

/**
 * Warn user before session expires (optional)
 */
let sessionTimeout;
let warningTimeout;

function resetSessionTimer() {
    clearTimeout(sessionTimeout);
    clearTimeout(warningTimeout);
    
    // Warning 5 minutes before timeout
    warningTimeout = setTimeout(() => {
        if (confirm('Your session will expire in 5 minutes. Do you want to continue?')) {
            // Ping server to keep session alive
            fetch('keep-alive.php');
            resetSessionTimer();
        }
    }, 55 * 60 * 1000); // 55 minutes
    
    // Auto logout after 1 hour
    sessionTimeout = setTimeout(() => {
        alert('Session expired. Please login again.');
        window.location.href = 'logout.php';
    }, 60 * 60 * 1000); // 60 minutes
}

// Reset timer on user activity
if (document.body.classList.contains('logged-in')) {
    document.addEventListener('mousemove', resetSessionTimer);
    document.addEventListener('keypress', resetSessionTimer);
    resetSessionTimer();
}

console.log('DFMS JavaScript Loaded Successfully!');

// =========================================================
// DATE VALIDATION FUNCTIONS
// Add this to your assets/js/script.js file
// =========================================================

/**
 * Validate date range (from date must be before or equal to to date)
 */
function validateDateRange(fromDateId, toDateId) {
    const fromDate = document.getElementById(fromDateId);
    const toDate = document.getElementById(toDateId);
    
    if (!fromDate || !toDate) {
        console.warn('Date inputs not found');
        return true;
    }
    
    if (!fromDate.value || !toDate.value) {
        alert('❌ Please select both From Date and To Date');
        return false;
    }
    
    const from = new Date(fromDate.value);
    const to = new Date(toDate.value);
    
    // Reset time to compare only dates
    from.setHours(0, 0, 0, 0);
    to.setHours(0, 0, 0, 0);
    
    if (from > to) {
        alert('❌ "From Date" cannot be later than "To Date"');
        fromDate.focus();
        return false;
    }
    
    return true;
}

/**
 * Validate date is not in future
 */
function validateNotFutureDate(dateInputId, fieldName = 'Date') {
    const dateInput = document.getElementById(dateInputId);
    if (!dateInput || !dateInput.value) return true;
    
    const inputDate = new Date(dateInput.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    inputDate.setHours(0, 0, 0, 0);
    
    if (inputDate > today) {
        alert(`❌ ${fieldName} cannot be in the future`);
        dateInput.focus();
        return false;
    }
    
    return true;
}

/**
 * Validate date is not too old (e.g., not older than 1 year)
 */
function validateNotTooOld(dateInputId, maxDaysOld = 365, fieldName = 'Date') {
    const dateInput = document.getElementById(dateInputId);
    if (!dateInput || !dateInput.value) return true;
    
    const inputDate = new Date(dateInput.value);
    const today = new Date();
    const diffTime = Math.abs(today - inputDate);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays > maxDaysOld) {
        alert(`❌ ${fieldName} cannot be older than ${maxDaysOld} days`);
        dateInput.focus();
        return false;
    }
    
    return true;
}

/**
 * Set max date attribute to today (prevent future dates in HTML)
 */
function setMaxDateToday(dateInputId) {
    const dateInput = document.getElementById(dateInputId);
    if (!dateInput) return;
    
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    
    dateInput.setAttribute('max', `${yyyy}-${mm}-${dd}`);
}

/**
 * Set min date attribute (prevent too old dates in HTML)
 */
function setMinDate(dateInputId, daysAgo = 365) {
    const dateInput = document.getElementById(dateInputId);
    if (!dateInput) return;
    
    const minDate = new Date();
    minDate.setDate(minDate.getDate() - daysAgo);
    
    const yyyy = minDate.getFullYear();
    const mm = String(minDate.getMonth() + 1).padStart(2, '0');
    const dd = String(minDate.getDate()).padStart(2, '0');
    
    dateInput.setAttribute('min', `${yyyy}-${mm}-${dd}`);
}

/**
 * Validate complete date range with all checks
 */
function validateCompleteDateRange(fromDateId, toDateId) {
    // First check if both dates are selected
    const fromInput = document.getElementById(fromDateId);
    const toInput = document.getElementById(toDateId);
    
    if (!fromInput || !toInput) {
        console.warn('Date inputs not found');
        return false;
    }
    
    if (!fromInput.value || !toInput.value) {
        alert('❌ Please select both From Date and To Date');
        return false;
    }
    
    // Check if from date is not in future
    if (!validateNotFutureDate(fromDateId, 'From Date')) {
        return false;
    }
    
    // Check if to date is not in future
    if (!validateNotFutureDate(toDateId, 'To Date')) {
        return false;
    }
    
    // Check if from date is before or equal to date
    if (!validateDateRange(fromDateId, toDateId)) {
        return false;
    }
    
    return true;
}

/**
 * Initialize date inputs with restrictions
 */
function initializeDateInputs() {
    // Find all date inputs with data-max-today attribute
    document.querySelectorAll('input[type="date"][data-max-today]').forEach(input => {
        setMaxDateToday(input.id);
    });
    
    // Find all date inputs with data-min-days attribute
    document.querySelectorAll('input[type="date"][data-min-days]').forEach(input => {
        const daysAgo = parseInt(input.getAttribute('data-min-days')) || 365;
        setMinDate(input.id, daysAgo);
    });
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeDateInputs();
    console.log('✅ Date validation initialized');
});