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
        
        // Always try to parse JSON, even on error status
        let data;
        try {
            data = await res.json();
        } catch (e) {
            // If response is not JSON (e.g., HTML error page)
            loginError.textContent = 'Server returned unexpected response';
            loginError.style.display = 'block';
            return;
        }
        
        if (res.ok && data.success) {
            currentUser = data.user;
            showMainApp();
        } else {
            loginError.textContent = data.error || 'Login failed - incorrect username or password';
            loginError.style.display = 'block';
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
        document.getElementById('statClasses').textContent = data.total_classes || 0;
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
        if (res.ok) {
            classes = await res.json();
        }
        
        // Populate class selects - with fallback hardcoded classes if needed
        const registerSelect = document.getElementById('registerClassSelect');
        if (registerSelect) {
            // Use API data if available, otherwise use fallback
            let classOptions = '';
            if (classes && classes.length > 0) {
                classOptions = classes.map(c => `<option value="${c.class_id}">${escapeHtml(c.class_name)} (${escapeHtml(c.section)})</option>`).join('');
            } else {
                // Fallback: Hardcoded classes if API returns empty
                const fallbackClasses = [
                  { class_id: 1, class_name: 'Baby Class', section: 'Nursery' },
                  { class_id: 2, class_name: 'Middle Class', section: 'Nursery' },
                  { class_id: 3, class_name: 'Top Class', section: 'Nursery' },
                  { class_id: 4, class_name: 'P.1', section: 'Lower Primary' },
                  { class_id: 5, class_name: 'P.2', section: 'Lower Primary' },
                  { class_id: 6, class_name: 'P.3', section: 'Lower Primary' },
                  { class_id: 7, class_name: 'P.4', section: 'Lower Primary' },
                  { class_id: 8, class_name: 'P.5', section: 'Upper Primary' },
                  { class_id: 9, class_name: 'P.6', section: 'Upper Primary' },
                  { class_id: 10, class_name: 'P.7', section: 'Upper Primary' }
                ];
                classOptions = fallbackClasses.map(c => `<option value="${c.class_id}">${escapeHtml(c.class_name)} (${escapeHtml(c.section)})</option>`).join('');
            }
            registerSelect.innerHTML = '<option value="">Select Class</option>' + classOptions;
        }
    } catch (err) {
        console.error('Classes load error:', err);
        // Fallback on error
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
    { class_id: 5, class_name: 'P.3', section: 'Lower Primary' },
    { class_id: 6, class_name: 'P.4', section: 'Lower Primary' },
    { class_id: 6, class_name: 'P.5', section: 'Upper Primary' },
    { class_id: 7, class_name: 'P.6', section: 'Upper Primary' },
    { class_id: 7, class_name: 'P.7', section: 'Upper Primary' }
  ];
  const registerSelect = document.getElementById('registerClassSelect');
  if (registerSelect) {
    registerSelect.innerHTML = '<option value="">Select Class</option>' +
      fallbackClasses.map(c => `<option value="${c.class_id}">${escapeHtml(c.class_name)} (${escapeHtml(c.section)})</option>`).join('');
  }
}

// Load students
async function loadStudents() {
    try {
        const res = await fetch(`${API_BASE}/students`);
        if (false) {
            showLogin();
            return;
        }
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
                approved_by: '<?= $_SESSION['full_name'] ?? 'Admin' ?>'
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
            </tr>
        `).join('');
    } catch (err) {
        console.error('Uniforms table error:', err);
        showLogin();
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

// Delete student
function deleteStudent(id) {
    if (confirm('Are you sure you want to delete this student?')) {
        fetch(`${API_BASE}/students/${id}`, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Student deleted successfully');
                loadStudentsTable();
                loadStudents();
            } else {
                alert('Failed to delete student');
            }
        })
        .catch(err => {
            console.error('Delete error:', err);
            alert('Error deleting student');
        });
    }
}
