// Ummul Bannin Madrasah - Frontend Application
// Connects to Node.js/Express API on Vercel

const API_BASE = '/api';

// State
let currentUser = null;
let classes = [];
let students = [];
let terms = [];
let uniformItems = [];

// DOM Elements
const loginScreen = document.getElementById('loginScreen');
const mainApp = document.getElementById('mainApp');
const loginForm = document.getElementById('loginForm');
const loginError = document.getElementById('loginError');
const logoutBtn = document.getElementById('logoutBtn');

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    setupEventListeners();
});

// Check authentication status
async function checkAuth() {
    try {
        const res = await fetch(`${API_BASE}/auth/status`);
        const data = await res.json();
        
        if (data.authenticated) {
            currentUser = data.user;
            showMainApp();
        } else {
            showLogin();
        }
    } catch (err) {
        console.error('Auth check failed:', err);
        showLogin();
    }
}

// Setup event listeners
function setupEventListeners() {
    // Login form
    loginForm.addEventListener('submit', handleLogin);
    
    // Logout
    logoutBtn.addEventListener('click', handleLogout);
    
    // Navigation
    document.querySelectorAll('.nav-link, .action-btn').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const page = link.dataset.page;
            if (page) navigateTo(page);
        });
    });
    
    // Register form
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', handleRegisterStudent);
    }
    
    // Fee payment form
    const feePaymentForm = document.getElementById('feePaymentForm');
    if (feePaymentForm) {
        feePaymentForm.addEventListener('submit', handleFeePayment);
    }
    
    // Uniform issue form
    const uniformIssueForm = document.getElementById('uniformIssueForm');
    if (uniformIssueForm) {
        uniformIssueForm.addEventListener('submit', handleUniformIssue);
    }
    
    // Term form
    const termForm = document.getElementById('termForm');
    if (termForm) {
        termForm.addEventListener('submit', handleAddTerm);
    }
    
    // User form
    const userForm = document.getElementById('userForm');
    if (userForm) {
        userForm.addEventListener('submit', handleCreateUser);
    }
}

// Handle login
async function handleLogin(e) {
    e.preventDefault();
    loginError.textContent = '';
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    try {
        const res = await fetch(`${API_BASE}/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        
        const data = await res.json();
        
        if (res.ok && data.success) {
            currentUser = data.user;
            showMainApp();
        } else {
            loginError.textContent = data.error || 'Login failed';
        }
    } catch (err) {
        loginError.textContent = 'Connection error. Please try again.';
        console.error('Login error:', err);
    }
}

// Handle logout
async function handleLogout() {
    try {
        await fetch(`${API_BASE}/logout`, { method: 'POST' });
        currentUser = null;
        showLogin();
    } catch (err) {
        console.error('Logout error:', err);
    }
}

// Show login screen
function showLogin() {
    loginScreen.style.display = 'flex';
    mainApp.style.display = 'none';
}

// Show main app
function showMainApp() {
    loginScreen.style.display = 'none';
    mainApp.style.display = 'flex';
    
    // Update user info
    document.getElementById('userName').textContent = currentUser.full_name || currentUser.username;
    document.getElementById('userRole').textContent = currentUser.role;
    document.getElementById('welcomeName').textContent = (currentUser.full_name || currentUser.username).split(' ')[0];
    
    // Show/hide admin links
    if (currentUser.role === 'Admin') {
        document.querySelectorAll('.admin-only').forEach(el => el.style.display = 'flex');
    }
    
    // Load initial data
    loadDashboard();
    loadClasses();
    loadStudents();
    loadTerms();
    loadUniformItems();
}

// Navigate to page
function navigateTo(page) {
    // Update nav active state
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.toggle('active', link.dataset.page === page);
    });
    
    // Show selected page
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    const pageEl = document.getElementById(`page-${page}`);
    if (pageEl) pageEl.classList.add('active');
    
    // Load page-specific data
    switch (page) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'students':
            loadStudentsTable();
            break;
        case 'fee-payment':
            loadFeePaymentForm();
            break;
        case 'reports':
            loadReports();
            break;
        case 'fee-status':
            loadFeeStatus();
            break;
        case 'terms':
            loadTermsTable();
            break;
        case 'class-counts':
            loadClassCounts();
            break;
        case 'manage-uniforms':
            loadUniformsTable();
            break;
        case 'uniform-issue':
            loadUniformIssueForm();
            break;
        case 'manage-fees':
            loadFeesTable();
            break;
        case 'users':
            loadUsersTable();
            break;
    }
}

// Load dashboard data
async function loadDashboard() {
    try {
        const res = await fetch(`${API_BASE}/dashboard`);
        const data = await res.json();
        
        document.getElementById('statStudents').textContent = data.total_students;
        document.getElementById('statBoarders').textContent = data.total_boarders;
        document.getElementById('statDay').textContent = data.total_day;
        document.getElementById('statClasses').textContent = data.total_classes;
        document.getElementById('statLowStock').textContent = data.low_stock_items;
        
        if (data.current_term) {
            document.getElementById('currentTerm').textContent = 
                `Term ${data.current_term.term_number}, ${data.current_term.academic_year}`;
        }
        
        // Recent payments
        const paymentsEl = document.getElementById('recentPayments');
        if (data.recent_payments && data.recent_payments.length > 0) {
            paymentsEl.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Receipt #</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.recent_payments.map(p => `
                            <tr>
                                <td>${escapeHtml(p.full_name)}</td>
                                <td>${escapeHtml(p.class_name)}</td>
                                <td>UGX ${formatNumber(p.amount_paid)}</td>
                                <td>${p.date_paid}</td>
                                <td>${escapeHtml(p.receipt_number || '-')}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        } else {
            paymentsEl.innerHTML = '<div class="empty-state">No fee payments recorded yet.</div>';
        }
    } catch (err) {
        console.error('Dashboard load error:', err);
    }
}

// Load classes
async function loadClasses() {
    try {
        const res = await fetch(`${API_BASE}/classes`);
        classes = await res.json();
        
        // Populate class selects
        const registerSelect = document.getElementById('registerClassSelect');
        if (registerSelect) {
            registerSelect.innerHTML = '<option value="">Select Class</option>' +
                classes.map(c => `<option value="${c.class_id}">${escapeHtml(c.class_name)} (${escapeHtml(c.section)})</option>`).join('');
        }
    } catch (err) {
        console.error('Classes load error:', err);
    }
}

// Load students
async function loadStudents() {
    try {
        const res = await fetch(`${API_BASE}/students`);
        students = await res.json();
        
        // Populate student selects
        const feeSelect = document.getElementById('feeStudentSelect');
        const uniformSelect = document.getElementById('uniformStudentSelect');
        
        const options = '<option value="">Select Student</option>' +
            students.map(s => `<option value="${s.student_id}">${escapeHtml(s.full_name)} (${escapeHtml(s.admission_number)})</option>`).join('');
        
        if (feeSelect) feeSelect.innerHTML = options;
        if (uniformSelect) uniformSelect.innerHTML = options;
    } catch (err) {
        console.error('Students load error:', err);
    }
}

// Load terms
async function loadTerms() {
    try {
        const res = await fetch(`${API_BASE}/terms`);
        terms = await res.json();
        
        const feeTermSelect = document.getElementById('feeTermSelect');
        if (feeTermSelect) {
            feeTermSelect.innerHTML = '<option value="">Select Term</option>' +
                terms.map(t => `<option value="${t.term_id}">Term ${t.term_number}, ${t.academic_year}${t.is_current ? ' (Current)' : ''}</option>`).join('');
        }
    } catch (err) {
        console.error('Terms load error:', err);
    }
}

// Load uniform items
async function loadUniformItems() {
    try {
        const res = await fetch(`${API_BASE}/uniform-items`);
        uniformItems = await res.json();
        
        const uniformItemSelect = document.getElementById('uniformItemSelect');
        if (uniformItemSelect) {
            uniformItemSelect.innerHTML = '<option value="">Select Item</option>' +
                uniformItems.map(u => `<option value="${u.uniform_id}">${escapeHtml(u.item_name)} - UGX ${formatNumber(u.price)}</option>`).join('');
        }
    } catch (err) {
        console.error('Uniform items load error:', err);
    }
}

// Handle register student
async function handleRegisterStudent(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.is_boarder = data.is_boarder === 'true';
    data.class_id = parseInt(data.class_id);
    
    try {
        const res = await fetch(`${API_BASE}/students`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (res.ok) {
            alert('Student registered successfully!');
            e.target.reset();
            loadStudents();
        } else {
            const err = await res.json();
            alert(err.error || 'Registration failed');
        }
    } catch (err) {
        alert('Connection error');
        console.error('Register student error:', err);
    }
}

// Handle fee payment
async function handleFeePayment(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.student_id = parseInt(data.student_id);
    data.term_id = parseInt(data.term_id);
    data.amount_paid = parseFloat(data.amount_paid);
    
    try {
        const res = await fetch(`${API_BASE}/fee-payments`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (res.ok) {
            alert('Payment recorded successfully!');
            e.target.reset();
        } else {
            const err = await res.json();
            alert(err.error || 'Payment failed');
        }
    } catch (err) {
        alert('Connection error');
        console.error('Fee payment error:', err);
    }
}

// Handle uniform issue
async function handleUniformIssue(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    data.student_id = parseInt(data.student_id);
    data.uniform_id = parseInt(data.uniform_id);
    data.quantity = parseInt(data.quantity);
    data.total_price = parseFloat(data.total_price);
    data.amount_paid = parseFloat(data.amount_paid);
    
    try {
        const res = await fetch(`${API_BASE}/uniform-issues`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (res.ok) {
            alert('Uniform issued successfully!');
            e.target.reset();
        } else {
            const err = await res.json();
            alert(err.error || 'Issue failed');
        }
    } catch (err) {
        alert('Connection error');
        console.error('Uniform issue error:', err);
    }
}

// Handle add term
async function handleAddTerm(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = {
        academic_year: parseInt(formData.get('academic_year')),
        term_number: parseInt(formData.get('term_number')),
        is_current: formData.get('is_current') === 'on'
    };
    
    try {
        const res = await fetch(`${API_BASE}/terms`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (res.ok) {
            alert('Term added successfully!');
            e.target.reset();
            loadTerms();
            loadTermsTable();
        } else {
            const err = await res.json();
            alert(err.error || 'Failed to add term');
        }
    } catch (err) {
        alert('Connection error');
        console.error('Add term error:', err);
    }
}

// Handle create user
async function handleCreateUser(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        const res = await fetch(`${API_BASE}/users`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (res.ok) {
            alert('User created successfully!');
            e.target.reset();
            loadUsersTable();
        } else {
            const err = await res.json();
            alert(err.error || 'Failed to create user');
        }
    } catch (err) {
        alert('Connection error');
        console.error('Create user error:', err);
    }
}

// Load students table
async function loadStudentsTable() {
    try {
        const res = await fetch(`${API_BASE}/students`);
        const students = await res.json();
        
        const tbody = document.querySelector('#studentsTable tbody');
        tbody.innerHTML = students.map(s => `
            <tr>
                <td>${escapeHtml(s.admission_number)}</td>
                <td>${escapeHtml(s.full_name)}</td>
                <td>${escapeHtml(s.class_name || '-')}</td>
                <td>${s.is_boarder ? 'Boarding' : 'Day'}</td>
                <td>${escapeHtml(s.status)}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editStudent(${s.student_id})">Edit</button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Students table error:', err);
    }
}

// Load fee payment form
async function loadFeePaymentForm() {
    await Promise.all([loadStudents(), loadTerms()]);
}

// Load reports
async function loadReports() {
    try {
        const res = await fetch(`${API_BASE}/reports/student-balances`);
        const balances = await res.json();
        
        const tbody = document.querySelector('#balancesTable tbody');
        tbody.innerHTML = balances.map(b => `
            <tr>
                <td>${escapeHtml(b.admission_number)}</td>
                <td>${escapeHtml(b.full_name)}</td>
                <td>${escapeHtml(b.class_name)}</td>
                <td>UGX ${formatNumber(b.total_due)}</td>
                <td>UGX ${formatNumber(b.total_paid)}</td>
                <td style="color: ${b.balance > 0 ? '#c0392b' : '#1b5e20'}; font-weight: 600;">
                    UGX ${formatNumber(b.balance)}
                </td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Reports error:', err);
    }
}

// Load fee status
async function loadFeeStatus() {
    try {
        const res = await fetch(`${API_BASE}/reports/class-fee-status`);
        const data = await res.json();
        
        const tbody = document.querySelector('#classFeeTable tbody');
        tbody.innerHTML = data.map(c => `
            <tr>
                <td>${escapeHtml(c.class_name)}</td>
                <td>${escapeHtml(c.section)}</td>
                <td>UGX ${formatNumber(c.term_fee)}</td>
                <td>${c.student_count}</td>
                <td>UGX ${formatNumber(c.total_collected)}</td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Fee status error:', err);
    }
}

// Load terms table
async function loadTermsTable() {
    try {
        const res = await fetch(`${API_BASE}/terms`);
        const terms = await res.json();
        
        const tbody = document.querySelector('#termsTable tbody');
        tbody.innerHTML = terms.map(t => `
            <tr>
                <td>${t.academic_year}</td>
                <td>Term ${t.term_number}</td>
                <td>${t.is_current ? 'Yes' : 'No'}</td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Terms table error:', err);
    }
}

// Load class counts
async function loadClassCounts() {
    try {
        const res = await fetch(`${API_BASE}/students`);
        const students = await res.json();
        
        const classCounts = {};
        students.forEach(s => {
            const key = s.class_name || 'Unknown';
            classCounts[key] = (classCounts[key] || 0) + 1;
        });
        
        const tbody = document.querySelector('#classCountsTable tbody');
        tbody.innerHTML = Object.entries(classCounts).map(([className, count]) => `
            <tr>
                <td>${escapeHtml(className)}</td>
                <td>-</td>
                <td>${count}</td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Class counts error:', err);
    }
}

// Load uniforms table
async function loadUniformsTable() {
    try {
        const res = await fetch(`${API_BASE}/uniform-items`);
        const items = await res.json();
        
        const tbody = document.querySelector('#uniformsTable tbody');
        tbody.innerHTML = items.map(u => `
            <tr>
                <td>${escapeHtml(u.item_name)}</td>
                <td>${escapeHtml(u.applicable_section)}</td>
                <td>${escapeHtml(u.applicable_gender)}</td>
                <td>UGX ${formatNumber(u.price)}</td>
                <td>${u.quantity_in_stock}</td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Uniforms table error:', err);
    }
}

// Load uniform issue form
async function loadUniformIssueForm() {
    await Promise.all([loadStudents(), loadUniformItems()]);
}

// Load fees table
async function loadFeesTable() {
    try {
        const res = await fetch(`${API_BASE}/classes`);
        const classes = await res.json();
        
        const tbody = document.querySelector('#feesTable tbody');
        tbody.innerHTML = classes.map(c => `
            <tr>
                <td>${escapeHtml(c.class_name)}</td>
                <td>${escapeHtml(c.section)}</td>
                <td>UGX ${formatNumber(c.term_fee)}</td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Fees table error:', err);
    }
}

// Load users table
async function loadUsersTable() {
    try {
        const res = await fetch(`${API_BASE}/users`);
        const users = await res.json();
        
        const tbody = document.querySelector('#usersTable tbody');
        tbody.innerHTML = users.map(u => `
            <tr>
                <td>${escapeHtml(u.username)}</td>
                <td>${escapeHtml(u.full_name || '-')}</td>
                <td>${escapeHtml(u.role)}</td>
                <td>${new Date(u.created_at).toLocaleDateString()}</td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Users table error:', err);
    }
}

// Utility functions
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-UG').format(num || 0);
}

// Edit student (placeholder)
function editStudent(id) {
    alert('Edit functionality - Student ID: ' + id);
}
