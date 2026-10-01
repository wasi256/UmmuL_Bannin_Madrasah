const express = require('express');
const session = require('express-session');
const PgSession = require('connect-pg-simple')(session);
const bcrypt = require('bcryptjs');
const cors = require('cors');
const path = require('path');
const { pool, initDB } = require('./db');

const app = express();
const PORT = process.env.PORT || 3000;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, '..')));

// Session configuration
app.use(session({
  store: new PgSession({
    pool: pool,
    tableName: 'session',
    createTableIfMissing: true
  }),
  secret: process.env.SESSION_SECRET || 'ummul-bannin-secret-key-change-in-production',
  resave: false,
  saveUninitialized: false,
  cookie: {
    secure: process.env.NODE_ENV === 'production',
    maxAge: 24 * 60 * 60 * 1000 // 24 hours
  }
}));

// Auth middleware
function requireAuth(req, res, next) {
  if (req.session && req.session.user_id) {
    next();
  } else {
    res.status(401).json({ error: 'Unauthorized' });
  }
}

function requireAdmin(req, res, next) {
  if (req.session && req.session.role === 'Admin') {
    next();
  } else {
    res.status(403).json({ error: 'Admin access required' });
  }
}

// ==================== AUTH ROUTES ====================

// Login
app.post('/api/login', async (req, res) => {
  try {
    const { username, password } = req.body;
    const result = await pool.query('SELECT * FROM users WHERE username = $1', [username]);
    
    if (result.rows.length === 0) {
      return res.status(401).json({ error: 'Invalid credentials' });
    }
    
    const user = result.rows[0];
    const validPassword = await bcrypt.compare(password, user.password_hash);
    
    if (!validPassword) {
      return res.status(401).json({ error: 'Invalid credentials' });
    }
    
    req.session.user_id = user.user_id;
    req.session.username = user.username;
    req.session.full_name = user.full_name;
    req.session.role = user.role;
    
    res.json({ 
      success: true, 
      user: { 
        user_id: user.user_id, 
        username: user.username, 
        full_name: user.full_name, 
        role: user.role 
      } 
    });
  } catch (err) {
    console.error('Login error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// Logout
app.post('/api/logout', (req, res) => {
  req.session.destroy();
  res.json({ success: true });
});

// Check auth status
app.get('/api/auth/status', (req, res) => {
  if (req.session && req.session.user_id) {
    res.json({ 
      authenticated: true, 
      user: { 
        user_id: req.session.user_id, 
        username: req.session.username, 
        full_name: req.session.full_name, 
        role: req.session.role 
      } 
    });
  } else {
    res.json({ authenticated: false });
  }
});

// ==================== DASHBOARD ====================

app.get('/api/dashboard', requireAuth, async (req, res) => {
  try {
    const totalStudents = await pool.query("SELECT COUNT(*) AS c FROM students WHERE status = 'Active'");
    const totalBoarders = await pool.query("SELECT COUNT(*) AS c FROM students WHERE status = 'Active' AND is_boarder = true");
    const totalDay = await pool.query("SELECT COUNT(*) AS c FROM students WHERE status = 'Active' AND is_boarder = false");
    const totalClasses = await pool.query("SELECT COUNT(*) AS c FROM classes");
    const lowStock = await pool.query("SELECT COUNT(*) AS c FROM uniform_items WHERE quantity_in_stock <= 5");
    
    const currentTerm = await pool.query("SELECT * FROM academic_terms WHERE is_current = true LIMIT 1");
    
    const recentPayments = await pool.query(`
      SELECT fp.amount_paid, fp.date_paid, fp.receipt_number, 
             s.student_id, s.full_name, s.is_boarder, c.class_id, c.class_name, c.term_fee 
      FROM fee_payments fp 
      JOIN students s ON fp.student_id = s.student_id 
      JOIN classes c ON s.class_id = c.class_id 
      ORDER BY fp.payment_id DESC LIMIT 5
    `);
    
    res.json({
      total_students: parseInt(totalStudents.rows[0].c),
      total_boarders: parseInt(totalBoarders.rows[0].c),
      total_day: parseInt(totalDay.rows[0].c),
      total_classes: parseInt(totalClasses.rows[0].c),
      low_stock_items: parseInt(lowStock.rows[0].c),
      current_term: currentTerm.rows[0] || null,
      recent_payments: recentPayments.rows
    });
  } catch (err) {
    console.error('Dashboard error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== STUDENTS ====================

app.get('/api/students', requireAuth, async (req, res) => {
  try {
    const result = await pool.query(`
      SELECT s.*, c.class_name, c.section 
      FROM students s 
      JOIN classes c ON s.class_id = c.class_id 
      ORDER BY s.full_name
    `);
    res.json(result.rows);
  } catch (err) {
    console.error('Students error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.post('/api/students', requireAuth, async (req, res) => {
  try {
    const { admission_number, full_name, gender, date_of_birth, class_id, is_boarder, guardian_name, guardian_phone } = req.body;
    const result = await pool.query(`
      INSERT INTO students (admission_number, full_name, gender, date_of_birth, class_id, is_boarder, guardian_name, guardian_phone)
      VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
      RETURNING *
    `, [admission_number, full_name, gender, date_of_birth, class_id, is_boarder || false, guardian_name, guardian_phone]);
    res.json(result.rows[0]);
  } catch (err) {
    console.error('Create student error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.put('/api/students/:id', requireAuth, async (req, res) => {
  try {
    const { full_name, gender, date_of_birth, class_id, is_boarder, guardian_name, guardian_phone, status } = req.body;
    const result = await pool.query(`
      UPDATE students 
      SET full_name = $1, gender = $2, date_of_birth = $3, class_id = $4, 
          is_boarder = $5, guardian_name = $6, guardian_phone = $7, status = $8
      WHERE student_id = $9
      RETURNING *
    `, [full_name, gender, date_of_birth, class_id, is_boarder, guardian_name, guardian_phone, status, req.params.id]);
    res.json(result.rows[0]);
  } catch (err) {
    console.error('Update student error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.delete('/api/students/:id', requireAuth, async (req, res) => {
  try {
    await pool.query('DELETE FROM students WHERE student_id = $1', [req.params.id]);
    res.json({ success: true });
  } catch (err) {
    console.error('Delete student error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== CLASSES ====================

app.get('/api/classes', requireAuth, async (req, res) => {
  try {
    const result = await pool.query('SELECT * FROM classes ORDER BY class_id');
    res.json(result.rows);
  } catch (err) {
    console.error('Classes error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== FEE PAYMENTS ====================

app.get('/api/fee-payments', requireAuth, async (req, res) => {
  try {
    const result = await pool.query(`
      SELECT fp.*, s.full_name, s.admission_number, c.class_name
      FROM fee_payments fp
      JOIN students s ON fp.student_id = s.student_id
      JOIN classes c ON s.class_id = c.class_id
      ORDER BY fp.payment_id DESC
    `);
    res.json(result.rows);
  } catch (err) {
    console.error('Fee payments error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.post('/api/fee-payments', requireAuth, async (req, res) => {
  try {
    const { student_id, term_id, amount_paid, payment_method, received_by, receipt_number, notes } = req.body;
    const result = await pool.query(`
      INSERT INTO fee_payments (student_id, term_id, amount_paid, payment_method, received_by, receipt_number, notes)
      VALUES ($1, $2, $3, $4, $5, $6, $7)
      RETURNING *
    `, [student_id, term_id, amount_paid, payment_method, received_by, receipt_number, notes]);
    res.json(result.rows[0]);
  } catch (err) {
    console.error('Create fee payment error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== UNIFORMS ====================

app.get('/api/uniform-items', requireAuth, async (req, res) => {
  try {
    const result = await pool.query('SELECT * FROM uniform_items ORDER BY uniform_id');
    res.json(result.rows);
  } catch (err) {
    console.error('Uniform items error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.post('/api/uniform-issues', requireAuth, async (req, res) => {
  try {
    const { student_id, uniform_id, quantity, total_price, amount_paid } = req.body;
    const result = await pool.query(`
      INSERT INTO uniform_issues (student_id, uniform_id, quantity, total_price, amount_paid)
      VALUES ($1, $2, $3, $4, $5)
      RETURNING *
    `, [student_id, uniform_id, quantity, total_price, amount_paid]);
    res.json(result.rows[0]);
  } catch (err) {
    console.error('Uniform issue error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== REPORTS ====================

app.get('/api/reports/class-fee-status', requireAuth, async (req, res) => {
  try {
    const result = await pool.query(`
      SELECT 
        c.class_name,
        c.section,
        c.term_fee,
        COUNT(s.student_id) AS student_count,
        COALESCE(SUM(fp.amount_paid), 0) AS total_collected
      FROM classes c
      LEFT JOIN students s ON c.class_id = s.class_id AND s.status = 'Active'
      LEFT JOIN fee_payments fp ON s.student_id = fp.student_id
      GROUP BY c.class_id, c.class_name, c.section, c.term_fee
      ORDER BY c.class_id
    `);
    res.json(result.rows);
  } catch (err) {
    console.error('Class fee status error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.get('/api/reports/student-balances', requireAuth, async (req, res) => {
  try {
    const result = await pool.query(`
      SELECT 
        s.student_id,
        s.admission_number,
        s.full_name,
        c.class_name,
        c.term_fee + CASE WHEN s.is_boarder THEN (SELECT amount FROM boarding_fee LIMIT 1) ELSE 0 END AS total_due,
        COALESCE(SUM(fp.amount_paid), 0) AS total_paid,
        c.term_fee + CASE WHEN s.is_boarder THEN (SELECT amount FROM boarding_fee LIMIT 1) ELSE 0 END - COALESCE(SUM(fp.amount_paid), 0) AS balance
      FROM students s
      JOIN classes c ON s.class_id = c.class_id
      LEFT JOIN fee_payments fp ON s.student_id = fp.student_id
      WHERE s.status = 'Active'
      GROUP BY s.student_id, s.admission_number, s.full_name, c.class_name, c.term_fee, s.is_boarder
      ORDER BY balance DESC
    `);
    res.json(result.rows);
  } catch (err) {
    console.error('Student balances error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== USERS (Admin only) ====================

app.get('/api/users', requireAdmin, async (req, res) => {
  try {
    const result = await pool.query('SELECT user_id, username, full_name, role, created_at FROM users ORDER BY user_id');
    res.json(result.rows);
  } catch (err) {
    console.error('Users error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.post('/api/users', requireAdmin, async (req, res) => {
  try {
    const { username, password, full_name, role } = req.body;
    const passwordHash = await bcrypt.hash(password, 10);
    const result = await pool.query(`
      INSERT INTO users (username, password_hash, full_name, role)
      VALUES ($1, $2, $3, $4)
      RETURNING user_id, username, full_name, role
    `, [username, passwordHash, full_name, role]);
    res.json(result.rows[0]);
  } catch (err) {
    console.error('Create user error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== TERMS ====================

app.get('/api/terms', requireAuth, async (req, res) => {
  try {
    const result = await pool.query('SELECT * FROM academic_terms ORDER BY academic_year DESC, term_number DESC');
    res.json(result.rows);
  } catch (err) {
    console.error('Terms error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.post('/api/terms', requireAuth, async (req, res) => {
  try {
    const { academic_year, term_number, is_current } = req.body;
    if (is_current) {
      await pool.query('UPDATE academic_terms SET is_current = false');
    }
    const result = await pool.query(`
      INSERT INTO academic_terms (academic_year, term_number, is_current)
      VALUES ($1, $2, $3)
      RETURNING *
    `, [academic_year, term_number, is_current]);
    res.json(result.rows[0]);
  } catch (err) {
    console.error('Create term error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// Initialize DB and start server
initDB().then(() => {
  app.listen(PORT, () => {
    console.log(`Server running on port ${PORT}`);
  });
});

module.exports = app;
