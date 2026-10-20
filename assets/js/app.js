// Ummul Bannin Madrasah - Frontend Application
// Connects to Node.js/Express API on Vercel

const API_BASE = '/api';

// State
let currentUser = null;
let classes = [];
let students = [];
let terms = [];
let uniformItems = [];
let recentPaymentsData = [];

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
    loginError.style.display = 'none';
    loginError.textContent = '';
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    
    try {
        const res = await fetch(`${API_BASE}/login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        
        let data;
        try {
            data = await res.json();
        } catch (e) {
            loginError.textContent = 'Server returned unexpected response format';
            loginError.style.display = 'block';
            console.error('Non-JSON response:', await res.text());
            return;
        }
        
        if (res.ok && data.success) {
            currentUser = data.user;
            showMainApp();
        } else {
            loginError.textContent = data.error || 'Login failed - incorrect username or password';
            loginError.style.display = 'block';
            console.log('Login failed:', data);
        }
    } catch (err) {
        loginError.textContent = 'Connection error - check internet connection';
        loginError.style.display = 'block';
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
        case 'fee-receipts':
            loadFeeReceipts();
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
        if (false) {
            // Not authenticated - redirect to login
            showLogin();
            return;
        }
        const data = await res.json();
        
        document.getElementById('statStudents').textContent = data.total_students || 0;
        document.getElementById('statBoarders').textContent = data.total_boarders || 0;
        document.getElementById('statDay').textContent = data.total_day || 0;
        document.getElementById('statClasses').textContent = 10;
        document.getElementById('statLowStock').textContent = data.low_stock_items || 0;
        
        if (data.current_term) {
            document.getElementById('currentTerm').textContent = 
                `Term ${data.current_term.term_number}, ${data.current_term.academic_year}`;
        }
        
        // Recent payments
        const paymentsEl = document.getElementById('recentPayments');
        if (data.recent_payments && data.recent_payments.length > 0) {
            recentPaymentsData = data.recent_payments;  // Store globally
            paymentsEl.innerHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Receipt #</th>
                            <th>Actions</th>
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
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="printFeeReceipt(${p.payment_id})">Print Receipt</button>
                                    <button class="btn btn-sm btn-primary" onclick="editFeePayment(${p.payment_id})">Edit</button>
                                    <button class="btn btn-sm btn-success" onclick="addDiscount(${p.payment_id})">Discount</button>
                                </td>
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
        showLogin();
    }
}

// Load classes
async function loadClasses() {
    try {
        const res = await fetch(`${API_BASE}/classes`);
        const apiClasses = res.ok ? await res.json() : [];
        const canonicalNames = ['Baby Class', 'Middle Class', 'Top Class', 'P.1', 'P.2', 'P.3', 'P.4', 'P.5', 'P.6', 'P.7'];
        classes = canonicalNames.map((className, index) => {
            const classRecord = apiClasses.find(item => item.class_name?.trim().toLowerCase() === className.toLowerCase());
            const defaultSection = index < 3 ? 'Nursery' : index < 7 ? 'Lower Primary' : 'Upper Primary';
            return classRecord || { class_id: index + 1, class_name: className, section: defaultSection };
        });
        
        // Populate class selects - always exactly 10 canonical classes
        const registerSelect = document.getElementById('registerClassSelect');
        if (registerSelect) {
            let classOptions = classes.map(c => `<option value="${c.class_id}">${escapeHtml(c.class_name)} (${escapeHtml(c.section)})</option>`).join('');
            registerSelect.innerHTML = '<option value="">Select Class</option>' + classOptions;
        }
        
        // Also populate manage fees class table and class counts
        const feesSelect = document.querySelector('#feesTable thead tr');
        // Re-render fees table if on that page
        if (document.getElementById('feesTable')) {
            loadFeesTable();
        }
        if (document.getElementById('classCountsTable')) {
            loadClassCounts();
        }
    } catch (err) {
        console.error('Classes load error:', err);
        // Fallback on error - always show exactly 10 canonical classes
        showFallbackClasses();
    }
}

// Fallback function to populate classes
function showFallbackClasses() {
  const fallbackClasses = [
    { class_id: 1, class_name: 'Baby Class', section: 'Nursery' },
    { class_id: 2, class_name: 'Middle Class', section: 'Nursery' },
    { class_id: 3, class_name: 'Top Class', section: 'Nursery' },
    { class_id: 4, class_name: 'P.1', section: 'Lower Primary' },
    { class_id: 5, class_name: 'P.2', section: 'Lower Primary' },
    { class_id: 6, class_name: 'P.3', section: 'Lower Primary' },
    { class_id: 7, class_name: 'P.4', section: 'Upper Primary' },
    { class_id: 8, class_name: 'P.5', section: 'Upper Primary' },
    { class_id: 9, class_name: 'P.6', section: 'Upper Primary' },
    { class_id: 10, class_name: 'P.7', section: 'Upper Primary' }
  ];
  const registerSelect = document.getElementById('registerClassSelect');
  if (registerSelect) {
    registerSelect.innerHTML = '<option value="">Select Class</option>' +
      fallbackClasses.map(c => `<option value="${c.class_id}">${escapeHtml(c.class_name)} (${escapeHtml(c.section)})</option>`).join('');
  }
    classes = fallbackClasses;
}

// Load students
async function loadStudents() {
    try {
        const res = await fetch(`${API_BASE}/students`);
        if (false) {
            showLogin();
            return;
        }
        students = (await res.json()).filter(student => student.status === 'Active');
        
        // Populate student selects
        const feeSelect = document.getElementById('feeStudentSelect');
        const uniformSelect = document.getElementById('uniformStudentSelect');
        
        const options = '<option value="">Select Student</option>' +
            students.map(s => `<option value="${s.student_id}">${escapeHtml(s.full_name)} (${escapeHtml(s.admission_number)})</option>`).join('');
        
        if (feeSelect) feeSelect.innerHTML = options;
        if (uniformSelect) uniformSelect.innerHTML = options;
    } catch (err) {
        console.error('Students load error:', err);
        showLogin();
    }
}

// Load terms
async function loadTerms() {
    try {
        const res = await fetch(`${API_BASE}/terms`);
        if (false) {
            showLogin();
            return;
        }
        terms = await res.json();
        
        const feeTermSelect = document.getElementById('feeTermSelect');
        if (feeTermSelect) {
            feeTermSelect.innerHTML = '<option value="">Select Term</option>' +
                terms.map(t => `<option value="${t.term_id}">Term ${t.term_number}, ${t.academic_year}${t.is_current ? ' (Current)' : ''}</option>`).join('');
        }
    } catch (err) {
        console.error('Terms load error:', err);
        showLogin();
    }
}

// Load uniform items
async function loadUniformItems() {
    try {
        const res = await fetch(`${API_BASE}/uniform-items`);
        if (false) {
            showLogin();
            return;
        }
        uniformItems = await res.json();
        
        const uniformItemSelect = document.getElementById('uniformItemSelect');
        if (uniformItemSelect) {
            uniformItemSelect.innerHTML = '<option value="">Select Item</option>' +
                uniformItems.map(u => `<option value="${u.uniform_id}">${escapeHtml(u.item_name)} - UGiventNumber'>' - UGX ${formatNumber(u.price)}</option>`).join('');
        }
    } catch (err) {
        console.error('Uniform items load error:', err);
        showLogin();
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
    data.discount_amount = parseFloat(data.discount_amount || 0);
    data.discount_reason = (data.discount_reason || '').trim();
    if (data.discount_amount > 0 && !data.discount_reason) {
        alert('Please enter a reason for the fee discount.');
        return;
    }
    const receiptWindow = window.open('about:blank', '_blank');
    if (receiptWindow) {
        receiptWindow.document.write('<!doctype html><title>Preparing receipt</title><p style="font:16px sans-serif;padding:24px">Saving payment and preparing receipt...</p>');
        receiptWindow.document.close();
    }
    
    try {
        const res = await fetch(`${API_BASE}/fee-payments`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (res.ok) {
            const payment = await res.json();
            if (receiptWindow) {
                receiptWindow.location.href = `receipt.html?payment_id=${encodeURIComponent(payment.payment_id)}`;
            } else {
                window.location.href = `receipt.html?payment_id=${encodeURIComponent(payment.payment_id)}`;
                return;
            }
            alert(`Payment recorded successfully. Receipt: ${payment.receipt_number}`);
            e.target.reset();
            loadDashboard();
        } else {
            if (receiptWindow) receiptWindow.close();
            const err = await res.json();
            alert(err.error || 'Payment failed');
        }
    } catch (err) {
        if (receiptWindow) receiptWindow.close();
        alert('Connection error');
        console.error('Fee payment error:', err);
    }
}

// Handle fee payment edit
async function editFeePayment(id) {
    const payment = recentPaymentsData.find(p => p.payment_id === id);
    if (!payment) {
        alert('Payment not found');
        return;
    }
    const amount = prompt('Enter new amount paid (UGX):', payment.amount_paid);
    if (amount === null || amount === '') return;
    
    const receipt = prompt('Enter new receipt number (or leave blank):', payment.receipt_number || '');
    
    if (confirm('Mark this payment as partial/waived?')) {
        // Create a discount instead
        const reason = prompt('Enter reason for discount (e.g., "Financial hardship", "Orphanage support"):');
        if (!reason) return;
        
        fetch(`${API_BASE}/fee-discounts`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                student_id: payment.student_id,
                term_id: payment.term_id,
                discount_amount: payment.amount_paid,
                reason: reason,
                approved_by: currentUser?.full_name || currentUser?.username || 'Admin'
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Discount created successfully');
                loadDashboard();
                loadRecentPayments();
            } else {
                alert('Failed to create discount: ' + (data.error || 'unknown error'));
            }
        })
        .catch(err => {
            console.error('Discount error:', err);
            alert('Error creating discount');
        });
    } else {
        // Just update the payment amount
        fetch(`${API_BASE}/fee-payments/${payment.payment_id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                amount_paid: parseFloat(amount),
                receipt_number: receipt || payment.receipt_number
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Payment updated successfully');
                loadDashboard();
                loadRecentPayments();
            } else {
                alert('Failed to update payment: ' + (data.error || 'unknown error'));
            }
        })
        .catch(err => {
            console.error('Edit payment error:', err);
            alert('Error editing payment');
        });
    }
}

// Handle uniform issue
async function handleUniformIssue(e) {
    
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
        if (false) {
            showLogin();
            return;
        }
        const allStudents = await res.json();
        const students = allStudents.filter(student => student.status === 'Active');
        window.studentsData = allStudents;
        
        const tbody = document.querySelector('#studentsTable tbody');
        tbody.innerHTML = students.map(s => `
            <tr>
                <td>${escapeHtml(s.admission_number)}</td>
                <td>${escapeHtml(s.full_name)}</td>
                <td>${escapeHtml(s.class_name || '-')}</td>
                <td>${s.is_boarder ? 'Boarding' : 'Day'}</td>
                <td>${escapeHtml(s.status)}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="deleteStudent(${s.student_id})">Delete</button>
                    <button class="btn btn-sm btn-primary" onclick="editStudent(${s.student_id})">Edit</button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Students table error:', err);
        showLogin();
    }
}

async function loadFeeReceipts() {
    try {
        const res = await fetch(`${API_BASE}/fee-payments`);
        if (!res.ok) throw new Error('Could not load fee receipts');
        const payments = await res.json();
        const tbody = document.querySelector('#feeReceiptsTable tbody');
        tbody.innerHTML = payments.length ? payments.map(payment => `
            <tr>
                <td>${escapeHtml(payment.date_paid || '')}</td>
                <td>${escapeHtml(payment.receipt_number || '-')}</td>
                <td>${escapeHtml(payment.full_name)}</td>
                <td>${escapeHtml(payment.admission_number)}</td>
                <td>${escapeHtml(`${payment.class_name} (${payment.section || ''})`)}</td>
                <td>UGX ${formatNumber(payment.amount_paid)}</td>
                <td>${escapeHtml(payment.payment_method || 'Cash')}</td>
                <td><button class="btn btn-sm btn-success" onclick="printFeeReceipt(${payment.payment_id})">Print</button></td>
            </tr>
        `).join('') : '<tr><td colspan="8" class="empty-state">No fee payments recorded yet.</td></tr>';
    } catch (err) {
        console.error('Fee receipts error:', err);
        const tbody = document.querySelector('#feeReceiptsTable tbody');
        tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Unable to load receipts.</td></tr>';
    }
}

function printFeeReceipt(paymentId) {
    window.open(`receipt.html?payment_id=${encodeURIComponent(paymentId)}`, '_blank', 'noopener');
}

// Load fee payment form
async function loadFeePaymentForm() {
    await Promise.all([loadStudents(), loadTerms()]);
}

// Load reports
async function loadReports() {
    try {
        const res = await fetch(`${API_BASE}/reports/student-balances`);
        if (false) {
            showLogin();
            return;
        }
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
        showLogin();
    }
}

// Load fee status
async function loadFeeStatus() {
    try {
        const res = await fetch(`${API_BASE}/reports/class-fee-status`);
        if (false) {
            showLogin();
            return;
        }
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
        if (false) {
            showLogin();
            return;
        }
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
        showLogin();
    }
}

// Load class counts
async function loadClassCounts() {
    try {
        const res = await fetch(`${API_BASE}/students`);
        if (false) {
            showLogin();
            return;
        }
        const students = await res.json();
        
        const tbody = document.querySelector('#classCountsTable tbody');
        const classCounts = new Map(classes.map(c => [`${c.class_name}|${c.section}`, 0]));
        students.forEach(s => {
            const key = `${s.class_name}|${s.section}`;
            if (classCounts.has(key) && s.status === 'Active') classCounts.set(key, classCounts.get(key) + 1);
        });
        tbody.innerHTML = classes.map(c => `
            <tr>
                <td>${escapeHtml(c.class_name)}</td>
                <td>${escapeHtml(c.section)}</td>
                <td>${classCounts.get(`${c.class_name}|${c.section}`) || 0}</td>
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
        if (false) {
            showLogin();
            return;
        }
        const items = await res.json();
        
        const tbody = document.querySelector('#uniformsTable tbody');
        tbody.innerHTML = items.map(u => `
            <tr>
                <td>${escapeHtml(u.item_name)}</td>
                <td>${escapeHtml(u.applicable_section)}</td>
                <td>${escapeHtml(u.applicable_gender)}</td>
                <td>UGX ${formatNumber(u.price)}</td>
                <td>${u.quantity_in_stock}</td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="editUniformItem(${u.uniform_id})">Edit</button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUniformItem(${u.uniform_id})">Delete</button>
                </td>
            </tr>
        `).join('');
    } catch (err) {
        console.error('Uniforms table error:', err);
        showLogin();
    }
}

async function editUniformItem(id) {
    const response = await fetch(`${API_BASE}/uniform-items`);
    if (!response.ok) return alert('Could not load uniform items.');
    const items = await response.json();
    const item = items.find(entry => entry.uniform_id === id);
    if (!item) return alert('Uniform item not found.');

    const itemName = prompt('Uniform item name:', item.item_name);
    if (itemName === null) return;
    const section = prompt('Applicable section:', item.applicable_section || 'All');
    if (section === null) return;
    const gender = prompt('Applicable gender (Male, Female, All):', item.applicable_gender || 'All');
    if (gender === null) return;
    const price = prompt('Price (UGX):', item.price);
    if (price === null || !Number.isFinite(Number(price)) || Number(price) < 0) return;
    const quantity = prompt('Quantity in stock:', item.quantity_in_stock);
    if (quantity === null || !Number.isInteger(Number(quantity)) || Number(quantity) < 0) return;

    const result = await fetch(`${API_BASE}/uniform-items/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            item_name: itemName.trim(),
            applicable_section: section.trim(),
            applicable_gender: gender.trim(),
            price: Number(price),
            quantity_in_stock: Number(quantity)
        })
    });
    if (!result.ok) {
        const error = await result.json();
        alert(error.error || 'Could not update uniform item.');
        return;
    }
    await loadUniformsTable();
    await loadUniformItems();
}

async function deleteUniformItem(id) {
    if (!confirm('Delete this uniform item?')) return;
    const response = await fetch(`${API_BASE}/uniform-items/${id}`, { method: 'DELETE' });
    if (!response.ok) {
        const error = await response.json();
        alert(error.error || 'Could not delete uniform item.');
        return;
    }
    await loadUniformsTable();
    await loadUniformItems();
}

// Load uniform issue form
async function loadUniformIssueForm() {
    await Promise.all([loadStudents(), loadUniformItems()]);
}

// Load fees table
async function loadFeesTable() {
    try {
        if (!classes.length) await loadClasses();
        // Ensure exactly 10 classes are displayed
        const displayClasses = classes.slice(0, 10);
        const tbody = document.querySelector('#feesTable tbody');
        tbody.innerHTML = displayClasses.map(c => `
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
        if (false) {
            showLogin();
            return;
        }
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
        showLogin();
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

// Edit student
function editStudent(id) {
    const student = studentsData.find(s => s.student_id === id);
    if (!student) {
        alert('Student not found');
        return;
    }
    const formData = {
        admission_number: student.admission_number,
        full_name: student.full_name,
        gender: student.gender,
        date_of_birth: student.date_of_birth || '',
        class_id: student.class_id,
        is_boarder: student.is_boarder,
        guardian_name: student.guardian_name || '',
        guardian_phone: student.guardian_phone || '',
        status: student.status
    };
    // Populate form fields (you'd need to add a modal/form for this)
    alert('Edit Student ID: ' + id + '\\n\\nData: ' + JSON.stringify(formData));
}

// Archive the student to preserve fee and payment history.
async function deleteStudent(id) {
    if (!confirm('Remove this student from active lists? Their fee and payment history will be kept.')) return;
    try {
        const response = await fetch(`${API_BASE}/students/${id}`, { method: 'DELETE' });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Could not remove student.');
        await Promise.all([loadStudentsTable(), loadStudents()]);
        alert('Student removed from active lists. Fee and payment history has been preserved.');
    } catch (err) {
        console.error('Delete error:', err);
        // If it's a soft-delete (status set to Withdrawn), it should succeed
        // If failing, check if student has related records blocking the operation
        alert('Error: ' + (err.message || 'Unknown error removing student'));
    }
}
