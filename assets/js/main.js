// Main JavaScript file for Business Permit System

// Global variables
let currentPage = 1;
let currentFilters = {};

// Utility functions
function showLoading(element) {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    if (element) {
        element.disabled = true;
        const spinner = element.querySelector('.spinner');
        if (spinner) {
            spinner.classList.remove('d-none');
        }
        const text = element.querySelector('.btn-text');
        if (text) {
            text.textContent = 'Loading...';
        }
    }
}

function hideLoading(element, originalText = 'Submit') {
    if (typeof element === 'string') {
        element = document.querySelector(element);
    }
    if (element) {
        element.disabled = false;
        const spinner = element.querySelector('.spinner');
        if (spinner) {
            spinner.classList.add('d-none');
        }
        const text = element.querySelector('.btn-text');
        if (text) {
            text.textContent = originalText;
        }
    }
}

function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at the top of the main content
    const container = document.querySelector('.container, .container-fluid');
    if (container) {
        container.insertBefore(alert, container.firstChild);
    }
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alert.remove();
    }, 5000);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount);
}

function formatNumber(number) {
    return new Intl.NumberFormat('en-PH').format(number);
}

function formatAmount(amount) {
    // Format numeric amounts with comma separators and 2 decimal places
    return parseFloat(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-PH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// API functions
async function apiRequest(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        },
    };
    
    const finalOptions = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, finalOptions);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || 'Request failed');
        }
        
        return data;
    } catch (error) {
        console.error('API request error:', error);
        throw error;
    }
}

// Permit status update
async function updatePermitStatus(permitId, newStatus, notes = '') {
    try {
        showLoading('#updateStatusBtn');
        
        const result = await apiRequest('api/update_permit_status.php', {
            method: 'POST',
            body: JSON.stringify({
                permit_id: permitId,
                status: newStatus,
                notes: notes
            })
        });
        
        if (result.success) {
            showAlert('Permit status updated successfully!', 'success');
            // Reload page or update UI
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(result.message || 'Failed to update status', 'danger');
        }
    } catch (error) {
        showAlert(error.message, 'danger');
    } finally {
        hideLoading('#updateStatusBtn', 'Update Status');
    }
}

// Get permit details
async function getPermitDetails(permitId) {
    try {
        const result = await apiRequest(`api/get_permit_details.php?permit_id=${permitId}`);
        
        if (result.success) {
            return result.permit;
        } else {
            throw new Error(result.message || 'Failed to get permit details');
        }
    } catch (error) {
        console.error('Get permit details error:', error);
        throw error;
    }
}

// Update requirement
async function updateRequirement(requirementId, isSubmitted, isVerified, notes = '') {
    try {
        const result = await apiRequest('api/update_requirement.php', {
            method: 'POST',
            body: JSON.stringify({
                requirement_id: requirementId,
                is_submitted: isSubmitted,
                is_verified: isVerified,
                notes: notes
            })
        });
        
        if (result.success) {
            showAlert('Requirement updated successfully!', 'success');
            return true;
        } else {
            showAlert(result.message || 'Failed to update requirement', 'danger');
            return false;
        }
    } catch (error) {
        showAlert(error.message, 'danger');
        return false;
    }
}

// Dashboard statistics
async function getDashboardStats() {
    try {
        const result = await apiRequest('api/get_dashboard_stats.php');
        
        if (result.success) {
            return result.stats;
        } else {
            throw new Error(result.message || 'Failed to get dashboard stats');
        }
    } catch (error) {
        console.error('Get dashboard stats error:', error);
        throw error;
    }
}

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    // Clear previous errors
    form.querySelectorAll('.is-invalid').forEach(field => {
        field.classList.remove('is-invalid');
    });
    form.querySelectorAll('.invalid-feedback').forEach(feedback => {
        feedback.textContent = '';
    });
    
    // Check required fields
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            showFieldError(field.id || field.name, 'This field is required');
            isValid = false;
        }
    });
    
    // Check email fields
    const emailFields = form.querySelectorAll('input[type="email"]');
    emailFields.forEach(field => {
        if (field.value && !validateEmail(field.value)) {
            showFieldError(field.id || field.name, 'Please enter a valid email address');
            isValid = false;
        }
    });
    
    // Check phone fields
    const phoneFields = form.querySelectorAll('input[type="tel"], input[pattern*="09"]');
    phoneFields.forEach(field => {
        if (field.value && !validatePhoneNumber(field.value)) {
            showFieldError(field.id || field.name, 'Please enter a valid phone number');
            isValid = false;
        }
    });
    
    return isValid;
}

function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId) || document.querySelector(`[name="${fieldId}"]`);
    if (!field) return;
    
    field.classList.add('is-invalid');
    
    let feedback = field.parentElement.querySelector('.invalid-feedback');
    if (!feedback) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        field.parentElement.appendChild(feedback);
    }
    feedback.textContent = message;
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePhoneNumber(phone) {
    // Remove non-digit characters
    const cleanPhone = phone.replace(/\D/g, '');
    const re = /^09[0-9]{9}$/;
    return re.test(cleanPhone);
}

// Search and filter functionality
function setupSearch(searchInputId, resultsContainerId, searchFunction) {
    const searchInput = document.getElementById(searchInputId);
    const resultsContainer = document.getElementById(resultsContainerId);
    
    if (!searchInput || !resultsContainer) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            searchFunction(query);
        }, 300);
    });
}

// Pagination
function setupPagination(containerId, totalPages, currentPage, onPageChange) {
    const container = document.getElementById(containerId);
    if (!container || totalPages <= 1) return;
    
    let paginationHTML = '<nav><ul class="pagination justify-content-center">';
    
    // Previous button
    if (currentPage > 1) {
        paginationHTML += `<li class="page-item">
            <a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>
        </li>`;
    }
    
    // Page numbers
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        paginationHTML += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
        if (startPage > 2) {
            paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for (let i = startPage; i <= endPage; i++) {
        const activeClass = i === currentPage ? 'active' : '';
        paginationHTML += `<li class="page-item ${activeClass}">
            <a class="page-link" href="#" data-page="${i}">${i}</a>
        </li>`;
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        paginationHTML += `<li class="page-item">
            <a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a>
        </li>`;
    }
    
    // Next button
    if (currentPage < totalPages) {
        paginationHTML += `<li class="page-item">
            <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
        </li>`;
    }
    
    paginationHTML += '</ul></nav>';
    container.innerHTML = paginationHTML;
    
    // Add click handlers
    container.addEventListener('click', function(e) {
        e.preventDefault();
        if (e.target.classList.contains('page-link')) {
            const page = parseInt(e.target.dataset.page);
            if (page && page !== currentPage) {
                onPageChange(page);
            }
        }
    });
}

// Modal functions
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('show');
        modal.style.display = 'block';
        document.body.classList.add('modal-open');
        
        // Add backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'modal-backdrop';
        document.body.appendChild(backdrop);
    }
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
        
        // Remove backdrop
        const backdrop = document.getElementById('modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
    }
}

// Auto-refresh functionality
function setupAutoRefresh(intervalInSeconds = 30) {
    setInterval(() => {
        // Only refresh if page is visible
        if (!document.hidden) {
            location.reload();
        }
    }, intervalInSeconds * 1000);
}

// Sidebar Management
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    
    if (!sidebar) return;
    
    // Check for saved sidebar state
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        sidebar.classList.add('collapsed');
    }
    
    // Desktop toggle
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }
    
    // Mobile menu toggle
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            sidebar.classList.add('mobile-open');
            if (sidebarOverlay) {
                sidebarOverlay.classList.add('active');
            }
        });
    }
    
    // Close sidebar on overlay click (mobile)
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        });
    }
    
    // Close sidebar on window resize (if desktop)
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            sidebar.classList.remove('mobile-open');
            if (sidebarOverlay) {
                sidebarOverlay.classList.remove('active');
            }
        }
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize sidebar
    initSidebar();
    
    // Setup auto-close for alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
    
    // Setup tooltips if needed
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Setup form validation on all forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.hasAttribute('data-no-validate')) {
                if (!validateForm(this.id)) {
                    e.preventDefault();
                }
            }
        });
    });
});

// Export functions for use in other scripts
window.BusinessPermitSystem = {
    apiRequest,
    updatePermitStatus,
    getPermitDetails,
    updateRequirement,
    getDashboardStats,
    showAlert,
    showLoading,
    hideLoading,
    setupSearch,
    setupPagination,
    showModal,
    hideModal,
    setupAutoRefresh,
    formatCurrency,
    formatNumber,
    formatAmount,
    formatDate,
    confirmAction
};
