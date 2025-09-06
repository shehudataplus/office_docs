/*
   User Management JavaScript
   Tajnur Ventures Limited - User Management System
*/

// API Configuration
const API_BASE_URL = 'api';
let csrfToken = null;

// Input Validation Functions
function validateUserInput(username, password, role) {
    // Validate username
    if (!username || username.length < 3) {
        return 'Username must be at least 3 characters long';
    }
    
    if (username.length > 50) {
        return 'Username must be less than 50 characters';
    }
    
    // Check for valid username characters (alphanumeric and underscore only)
    if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        return 'Username can only contain letters, numbers, and underscores';
    }
    
    // Validate password
    if (!password || password.length < 6) {
        return 'Password must be at least 6 characters long';
    }
    
    if (password.length > 255) {
        return 'Password is too long';
    }
    
    // Validate role
    const validRoles = ['admin', 'staff', 'manager'];
    if (!role || !validRoles.includes(role)) {
        return 'Please select a valid role';
    }
    
    // Check for common weak passwords
    const weakPasswords = ['password', '123456', 'admin', 'user', 'guest'];
    if (weakPasswords.includes(password.toLowerCase())) {
        return 'Please choose a stronger password';
    }
    
    return null; // No validation errors
}

// API Helper Functions
async function apiRequest(endpoint, options = {}) {
    const url = `${API_BASE_URL}?action=${endpoint}`;
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            ...(csrfToken && { 'X-CSRF-Token': csrfToken })
        },
        credentials: 'same-origin'
    };
    
    const config = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, config);
        const data = await response.json();
        
        // Update CSRF token if provided
        if (data.csrf_token) {
            csrfToken = data.csrf_token;
        }
        
        return { success: response.ok, data, status: response.status };
    } catch (error) {
        console.error('API Request failed:', error);
        return { success: false, error: error.message };
    }
}

// Authentication using existing auth.php
async function authApiRequest(endpoint, options = {}) {
    const url = `api/auth.php/${endpoint}`;
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            ...(csrfToken && { 'X-CSRF-Token': csrfToken })
        },
        credentials: 'same-origin'
    };
    
    const config = { ...defaultOptions, ...options };
    
    try {
        const response = await fetch(url, config);
        const data = await response.json();
        
        // Update CSRF token if provided
        if (data.csrf_token) {
            csrfToken = data.csrf_token;
        }
        
        return { success: response.ok, data, status: response.status };
    } catch (error) {
        console.error('Auth API Request failed:', error);
        return { success: false, error: error.message };
    }
}

// Get CSRF token
async function getCSRFToken() {
    const result = await authApiRequest('csrf');
    if (result.success && result.data.csrf_token) {
        csrfToken = result.data.csrf_token;
    }
    return csrfToken;
}

// Check authentication status and admin role
async function checkAuthStatus() {
    const result = await authApiRequest('verify');
    if (result.success && result.data.authenticated) {
        // Check if user has admin role
        return result.data.role === 'admin';
    }
    return false;
}

// Initialize document system when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeAuthentication();
});

// Initialize authentication system
async function initializeAuthentication() {
    // Get CSRF token first
    await getCSRFToken();
    
    // Check server-side authentication status and admin role
    const isAdminAuthenticated = await checkAuthStatus();
    
    if (isAdminAuthenticated) {
        showMainContent();
        loadUsers();
    } else {
        showAuthForm();
    }
    
    // Set up login form event listener
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
    
    // Set up add user form event listener
    const addUserForm = document.getElementById('add-user-form');
    if (addUserForm) {
        addUserForm.addEventListener('submit', handleAddUser);
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
}

// Handle login form submission
async function handleLogin(event) {
    event.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const errorMessage = document.getElementById('error-message');
    const submitBtn = event.target.querySelector('button[type="submit"]');
    
    // Disable submit button and show loading
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Logging in...';
    }
    
    // Clear previous error
    errorMessage.style.display = 'none';
    
    // Frontend validation
    const validationError = validateUserInput(username, password, 'admin');
    if (validationError && !validationError.includes('role')) {
        errorMessage.textContent = validationError;
        errorMessage.style.display = 'block';
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Login';
        }
        return;
    }
    
    try {
        // Make API request to login
        const result = await authApiRequest('/auth.php/login', {
            method: 'POST',
            body: JSON.stringify({
                username: username,
                password: password,
                csrf_token: csrfToken
            })
        });
        
        if (result.success) {
            // Check if user is admin
            if (result.is_admin === true) {
                showMainContent();
                loadUsers();
                
                // Clear form
                document.getElementById('login-form').reset();
            } else {
                errorMessage.textContent = 'Admin access required for user management';
                errorMessage.style.display = 'block';
            }
        } else {
            // Failed login
            const message = result.data?.message || result.error || 'Login failed';
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
        }
    } catch (error) {
        console.error('Login error:', error);
        errorMessage.textContent = 'Network error. Please try again.';
        errorMessage.style.display = 'block';
    } finally {
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Login';
        }
    }
}

// Handle add user form submission
async function handleAddUser(event) {
    event.preventDefault();
    
    const username = document.getElementById('new-username').value.trim();
    const password = document.getElementById('new-password').value;
    const role = document.getElementById('new-role').value;
    const submitBtn = event.target.querySelector('button[type="submit"]');
    
    // Disable submit button and show loading
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adding User...';
    }
    
    // Clear previous messages
    hideMessages();
    
    // Frontend validation
    const validationError = validateUserInput(username, password, role);
    if (validationError) {
        showErrorMessage(validationError);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add User';
        }
        return;
    }
    
    try {
        // Make API request to add user
        const result = await apiRequest('/user-management.php/users', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                username: username,
                password: password,
                role: role,
                is_admin: role === 'admin'
            })
        });
        
        if (result.success) {
            showSuccessMessage('User added successfully');
            
            // Clear form
            document.getElementById('add-user-form').reset();
            
            // Reload users list
            loadUsers();
        } else {
            const message = result.data?.message || result.error || 'Failed to add user';
            showErrorMessage(message);
        }
    } catch (error) {
        console.error('Add user error:', error);
        showErrorMessage('Network error. Please try again.');
    } finally {
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Add User';
        }
    }
}

// Load users list
async function loadUsers() {
    const tbody = document.getElementById('users-tbody');
    const container = document.getElementById('users-container');
    
    // Show loading
    container.classList.add('loading');
    
    try {
        const result = await apiRequest('/user-management.php/users');
        
        if (result.success) {
            const users = result.users || [];
            
            // Clear existing rows
            tbody.innerHTML = '';
            
            if (users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No users found</td></tr>';
            } else {
                users.forEach(user => {
                    const row = createUserRow(user);
                    tbody.appendChild(row);
                });
            }
        } else {
            showErrorMessage('Failed to load users');
        }
    } catch (error) {
        console.error('Load users error:', error);
        showErrorMessage('Network error while loading users');
    } finally {
        // Hide loading
        container.classList.remove('loading');
    }
}

// Create user table row
function createUserRow(user) {
    const row = document.createElement('tr');
    
    const statusClass = user.status === 'active' ? 'status-active' : 'status-inactive';
    const lastLogin = user.last_login ? new Date(user.last_login).toLocaleString() : 'Never';
    
    row.innerHTML = `
        <td>${user.id}</td>
        <td>${escapeHtml(user.username)}</td>
        <td>${escapeHtml(user.role)}</td>
        <td><span class="status-badge ${statusClass}">${user.status || 'active'}</span></td>
        <td>${lastLogin}</td>
        <td>
            <button onclick="deleteUser(${user.id}, '${escapeHtml(user.username)}')" 
                    class="btn btn-danger" 
                    style="padding: 6px 12px; font-size: 14px;"
                    ${user.username === 'admin' ? 'disabled title="Cannot delete admin user"' : ''}>
                <i class="fas fa-trash"></i> Delete
            </button>
        </td>
    `;
    
    return row;
}

// Delete user
async function deleteUser(userId, username) {
    if (!confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
        return;
    }
    
    try {
        const result = await apiRequest(`/user-management.php/users/${userId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': csrfToken
            }
        });
        
        if (result.success) {
            showSuccessMessage(`User "${username}" deleted successfully`);
            loadUsers();
        } else {
            const message = result.message || 'Failed to delete user';
            showErrorMessage(message);
        }
    } catch (error) {
        console.error('Delete user error:', error);
        showErrorMessage('Network error. Please try again.');
    }
}

// Logout function
async function logout() {
    try {
        // Call logout API
        await authApiRequest('/auth.php/logout', {
            method: 'POST',
            body: JSON.stringify({
                csrf_token: csrfToken
            })
        });
    } catch (error) {
        console.error('Logout error:', error);
        // Continue with logout even if API call fails
    }
    
    // Clear session storage
    sessionStorage.clear();
    
    // Show auth form
    showAuthForm();
    
    // Get new CSRF token for next login
    await getCSRFToken();
}

// Utility functions
function showSuccessMessage(message) {
    const element = document.getElementById('success-message');
    element.textContent = message;
    element.style.display = 'block';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        element.style.display = 'none';
    }, 5000);
}

function showErrorMessage(message) {
    const element = document.getElementById('error-message-main');
    element.textContent = message;
    element.style.display = 'block';
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        element.style.display = 'none';
    }, 5000);
}

function hideMessages() {
    document.getElementById('success-message').style.display = 'none';
    document.getElementById('error-message-main').style.display = 'none';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}