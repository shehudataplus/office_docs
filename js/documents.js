/*
   Office Documents JavaScript
   Tajnur Ventures Limited - Document Management System
*/

// Authentication credentials (in production, use secure backend authentication)
const AUTH_CREDENTIALS = {
    'admin': 'tajnur2024',
    'staff': 'office123',
    'manager': 'docs2024'
};

// Initialize document system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeAuthentication();
});

// Initialize authentication system
function initializeAuthentication() {
    // Check if user is already authenticated
    if (sessionStorage.getItem('authenticated') === 'true') {
        showMainContent();
    } else {
        showAuthForm();
    }
    
    // Set up login form event listener
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
}

// Show authentication form
function showAuthForm() {
    document.getElementById('auth-container').style.display = 'flex';
    document.getElementById('main-content').style.display = 'none';
}

// Show main content after authentication
function showMainContent() {
    document.getElementById('auth-container').style.display = 'none';
    document.getElementById('main-content').style.display = 'block';
    
    // Initialize document system after authentication
    initializeDocuments();
    setCurrentDate();
    setupEventListeners();
}

// Handle login form submission
function handleLogin(event) {
    event.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const errorMessage = document.getElementById('error-message');
    
    // Validate credentials
    if (AUTH_CREDENTIALS[username] && AUTH_CREDENTIALS[username] === password) {
        // Successful login
        sessionStorage.setItem('authenticated', 'true');
        sessionStorage.setItem('username', username);
        showMainContent();
        
        // Clear form
        document.getElementById('login-form').reset();
        errorMessage.style.display = 'none';
    } else {
        // Failed login
        errorMessage.textContent = 'Invalid username or password. Please try again.';
        errorMessage.style.display = 'block';
        
        // Clear password field
        document.getElementById('password').value = '';
    }
}

// Logout function (can be called from anywhere)
function logout() {
    sessionStorage.removeItem('authenticated');
    sessionStorage.removeItem('username');
    showAuthForm();
}

// Initialize document system
function initializeDocuments() {
    // Set up tab switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const templates = document.querySelectorAll('.document-template');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            switchTab(targetTab);
        });
    });
    
    // Initialize invoice calculations
    setupInvoiceCalculations();
}

// Set current date for all date inputs
function setCurrentDate() {
    const today = new Date().toISOString().split('T')[0];
    const dateInputs = document.querySelectorAll('input[type="date"]');
    
    dateInputs.forEach(input => {
        if (!input.value) {
            input.value = today;
        }
    });
}

// Switch between document tabs
function switchTab(tabName) {
    // Remove active class from all tabs and templates
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.document-template').forEach(template => template.classList.remove('active'));
    
    // Add active class to selected tab and template
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
    document.getElementById(`${tabName}-template`).classList.add('active');
}

// Setup event listeners for various interactions
function setupEventListeners() {
    // Auto-generate receipt numbers
    generateReceiptNumber();
    generateInvoiceNumber();
    
    // Setup amount formatting
    const amountInputs = document.querySelectorAll('input[type="number"]');
    amountInputs.forEach(input => {
        input.addEventListener('blur', function() {
            if (this.value && !isNaN(this.value)) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
    
    // Setup amount in words conversion for receipt
    const amountReceivedInput = document.getElementById('amount-received');
    const amountInWordsInput = document.getElementById('amount-in-words');
    
    if (amountReceivedInput && amountInWordsInput) {
        amountReceivedInput.addEventListener('input', function() {
            const amount = parseFloat(this.value);
            if (!isNaN(amount) && amount > 0) {
                amountInWordsInput.value = convertNumberToWords(amount);
            } else {
                amountInWordsInput.value = '';
            }
        });
    }
}

// Generate unique receipt number
function generateReceiptNumber() {
    const receiptInput = document.getElementById('receipt-number');
    if (receiptInput && !receiptInput.value) {
        const timestamp = Date.now().toString().slice(-6);
        receiptInput.value = `RCP-${timestamp}`;
    }
}

// Generate unique invoice number
function generateInvoiceNumber() {
    const invoiceInput = document.getElementById('invoice-number');
    if (invoiceInput && !invoiceInput.value) {
        const timestamp = Date.now().toString().slice(-6);
        invoiceInput.value = `INV-${timestamp}`;
    }
}

// Setup invoice calculations
function setupInvoiceCalculations() {
    const itemsBody = document.getElementById('invoice-items-body');
    if (itemsBody) {
        // Add event listeners to existing items
        setupItemCalculations();
    }
}

// Setup calculations for invoice items
function setupItemCalculations() {
    const itemRows = document.querySelectorAll('.item-row');
    
    itemRows.forEach(row => {
        const qtyInput = row.querySelector('.item-qty');
        const rateInput = row.querySelector('.item-rate');
        const amountCell = row.querySelector('.item-amount');
        
        if (qtyInput && rateInput && amountCell) {
            [qtyInput, rateInput].forEach(input => {
                input.addEventListener('input', function() {
                    calculateItemAmount(row);
                    calculateInvoiceTotals();
                });
            });
        }
    });
}

// Calculate amount for a single item
function calculateItemAmount(row) {
    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
    const amount = qty * rate;
    
    row.querySelector('.item-amount').textContent = formatCurrency(amount);
}

// Calculate invoice totals
function calculateInvoiceTotals() {
    const itemRows = document.querySelectorAll('.item-row');
    let subtotal = 0;
    
    itemRows.forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const rate = parseFloat(row.querySelector('.item-rate').value) || 0;
        subtotal += qty * rate;
    });
    
    const taxRate = 0.075; // 7.5% VAT
    const taxAmount = subtotal * taxRate;
    const total = subtotal + taxAmount;
    
    // Update display
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('tax-amount').textContent = formatCurrency(taxAmount);
    document.getElementById('total-amount').textContent = formatCurrency(total);
}

// Add new invoice item
function addInvoiceItem() {
    const itemsBody = document.getElementById('invoice-items-body');
    const newRow = document.createElement('tr');
    newRow.className = 'item-row';
    
    newRow.innerHTML = `
        <td><input type="text" class="item-description" placeholder="Item description"></td>
        <td><input type="number" class="item-qty" value="1" min="1"></td>
        <td><input type="number" class="item-rate" placeholder="0.00" step="0.01"></td>
        <td class="item-amount">₦0.00</td>
        <td><button class="btn-remove" onclick="removeItem(this)"><i class="fas fa-trash"></i></button></td>
    `;
    
    itemsBody.appendChild(newRow);
    
    // Setup calculations for the new row
    const qtyInput = newRow.querySelector('.item-qty');
    const rateInput = newRow.querySelector('.item-rate');
    
    [qtyInput, rateInput].forEach(input => {
        input.addEventListener('input', function() {
            calculateItemAmount(newRow);
            calculateInvoiceTotals();
        });
    });
    
    // Focus on description input
    newRow.querySelector('.item-description').focus();
}

// Remove invoice item
function removeItem(button) {
    const row = button.closest('.item-row');
    const itemsBody = document.getElementById('invoice-items-body');
    
    // Don't remove if it's the last row
    if (itemsBody.children.length > 1) {
        row.remove();
        calculateInvoiceTotals();
    } else {
        // Clear the last row instead of removing it
        row.querySelectorAll('input').forEach(input => input.value = '');
        row.querySelector('.item-amount').textContent = '₦0.00';
        calculateInvoiceTotals();
    }
}

// Format currency
function formatCurrency(amount) {
    return `₦${amount.toLocaleString('en-NG', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    })}`;
}

// Convert number to words
function convertNumberToWords(amount) {
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    const thousands = ['', 'Thousand', 'Million', 'Billion'];
    
    if (amount === 0) return 'Zero Naira Only';
    
    // Split into naira and kobo
    const parts = amount.toFixed(2).split('.');
    const naira = parseInt(parts[0]);
    const kobo = parseInt(parts[1]);
    
    function convertHundreds(num) {
        let result = '';
        
        if (num >= 100) {
            result += ones[Math.floor(num / 100)] + ' Hundred ';
            num %= 100;
        }
        
        if (num >= 20) {
            result += tens[Math.floor(num / 10)];
            if (num % 10 !== 0) {
                result += ' ' + ones[num % 10];
            }
        } else if (num >= 10) {
            result += teens[num - 10];
        } else if (num > 0) {
            result += ones[num];
        }
        
        return result.trim();
    }
    
    function convertNumber(num) {
        if (num === 0) return '';
        
        let result = '';
        let thousandIndex = 0;
        
        while (num > 0) {
            const chunk = num % 1000;
            if (chunk !== 0) {
                const chunkWords = convertHundreds(chunk);
                if (thousandIndex > 0) {
                    result = chunkWords + ' ' + thousands[thousandIndex] + ' ' + result;
                } else {
                    result = chunkWords + ' ' + result;
                }
            }
            num = Math.floor(num / 1000);
            thousandIndex++;
        }
        
        return result.trim();
    }
    
    let result = '';
    
    // Convert naira part
    if (naira > 0) {
        result += convertNumber(naira) + ' Naira';
    }
    
    // Convert kobo part
    if (kobo > 0) {
        if (result) result += ' and ';
        result += convertNumber(kobo) + ' Kobo';
    }
    
    // If no kobo, just add "Only"
    if (kobo === 0 && naira > 0) {
        result += ' Only';
    } else if (kobo > 0) {
        result += ' Only';
    }
    
    return result;
}

// Clear form data
function clearForm(formType) {
    const template = document.getElementById(`${formType}-template`);
    
    if (template) {
        // Clear all input fields
        template.querySelectorAll('input, textarea, select').forEach(field => {
            if (field.type === 'date') {
                field.value = new Date().toISOString().split('T')[0];
            } else if (field.type === 'number') {
                field.value = field.classList.contains('item-qty') ? '1' : '';
            } else {
                field.value = '';
            }
        });
        
        // Reset specific form elements
        if (formType === 'invoice') {
            // Reset invoice items to single row
            const itemsBody = document.getElementById('invoice-items-body');
            itemsBody.innerHTML = `
                <tr class="item-row">
                    <td><input type="text" class="item-description" placeholder="Item description"></td>
                    <td><input type="number" class="item-qty" value="1" min="1"></td>
                    <td><input type="number" class="item-rate" placeholder="0.00" step="0.01"></td>
                    <td class="item-amount">₦0.00</td>
                    <td><button class="btn-remove" onclick="removeItem(this)"><i class="fas fa-trash"></i></button></td>
                </tr>
            `;
            
            // Reset totals
            document.getElementById('subtotal').textContent = '₦0.00';
            document.getElementById('tax-amount').textContent = '₦0.00';
            document.getElementById('total-amount').textContent = '₦0.00';
            
            // Reset payment terms
            document.getElementById('payment-terms-text').value = 'Payment is due within 30 days of invoice date. Late payments may incur additional charges.';
            
            // Re-setup calculations
            setupItemCalculations();
            
            // Generate new invoice number
            generateInvoiceNumber();
        }
        
        if (formType === 'receipt') {
            // Generate new receipt number
            generateReceiptNumber();
            // Clear amount in words
            const amountInWordsInput = document.getElementById('amount-in-words');
            if (amountInWordsInput) {
                amountInWordsInput.value = '';
            }
        }
        
        if (formType === 'letterhead') {
            // Reset letter content template
            document.getElementById('letter-body').value = 'Dear Sir/Madam,\n\nWrite your letter content here...\n\nThank you for your attention.\n\nYours sincerely,\n\n\n[Your Name]\n[Your Title]\nTajnur Ventures Limited';
        }
    }
    
    showNotification(`${formType.charAt(0).toUpperCase() + formType.slice(1)} form cleared successfully!`, 'success');
}

// Print document
function printDocument(documentType) {
    // Validate required fields before printing
    if (!validateForm(documentType)) {
        return;
    }
    
    // Hide all templates except the active one
    const templates = document.querySelectorAll('.document-template');
    templates.forEach(template => {
        if (template.id !== `${documentType}-template`) {
            template.style.display = 'none';
        }
    });
    
    // Show only the active template
    const activeTemplate = document.getElementById(`${documentType}-template`);
    activeTemplate.style.display = 'block';
    activeTemplate.classList.add('active');
    
    // Print
    window.print();
    
    // Restore display after printing
    setTimeout(() => {
        templates.forEach(template => {
            template.style.display = '';
        });
    }, 1000);
}

// Validate form before printing
function validateForm(formType) {
    const template = document.getElementById(`${formType}-template`);
    const requiredFields = [];
    
    // Define required fields for each form type
    switch (formType) {
        case 'receipt':
            requiredFields.push(
                { id: 'receipt-number', name: 'Receipt Number' },
                { id: 'customer-name', name: 'Customer Name' },
                { id: 'amount-received', name: 'Amount Received' }
            );
            // Check if amount in words is generated
            const amountInWords = document.getElementById('amount-in-words');
            if (amountInWords && !amountInWords.value.trim()) {
                showNotification('Please enter a valid amount to generate amount in words.', 'error');
                document.getElementById('amount-received').focus();
                return false;
            }
            break;
        case 'invoice':
            requiredFields.push(
                { id: 'invoice-number', name: 'Invoice Number' },
                { id: 'client-name', name: 'Client Name' }
            );
            // Check if at least one item has description and rate
            const firstItemDesc = document.querySelector('.item-description').value;
            const firstItemRate = document.querySelector('.item-rate').value;
            if (!firstItemDesc || !firstItemRate) {
                showNotification('Please add at least one item with description and rate.', 'error');
                return false;
            }
            break;
        case 'letterhead':
            requiredFields.push(
                { id: 'recipient-address', name: 'Recipient Address' },
                { id: 'letter-subject', name: 'Letter Subject' },
                { id: 'letter-body', name: 'Letter Content' }
            );
            break;
    }
    
    // Check required fields
    for (const field of requiredFields) {
        const element = document.getElementById(field.id);
        if (!element || !element.value.trim()) {
            showNotification(`Please fill in the ${field.name} field.`, 'error');
            if (element) element.focus();
            return false;
        }
    }
    
    return true;
}

// Show notification
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
            <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        max-width: 400px;
        animation: slideIn 0.3s ease;
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Add CSS animations for notifications
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
    
    .notification-content {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .notification-close {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 0;
        margin-left: auto;
    }
    
    .notification-close:hover {
        opacity: 0.8;
    }
`;
document.head.appendChild(style);

// Export functions for global access
window.addInvoiceItem = addInvoiceItem;
window.removeItem = removeItem;
window.clearForm = clearForm;
window.printDocument = printDocument;