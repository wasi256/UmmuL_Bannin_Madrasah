const express = require('express');
const session = require('express-session');
const PgSession = require('connect-pg-simple')(session);
const bcrypt = require('bcryptjs');
const cors = require('cors');
const path = require('path');
const crypto = require('crypto');
const { pool, initDB } = require('./db');

const app = express();
const PORT = process.env.PORT || 3000;

app.set('trust proxy', 1);

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, '..')));

// Session configuration - FIXED
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

const databaseReady = initDB().then(async () => {
  try {
    // Check if admin user exists, if not create one
    const existingUsers = await pool.query('SELECT COUNT(*) AS c FROM users');
    if (existingUsers.rows[0].c === 0) {
      const hash = await bcrypt.hash('admin123', 10);
      await pool.query(
        `INSERT INTO users (username, password_hash, full_name, role) VALUES ('admin', $1, 'Admin User', 'Admin')`,
        [hash]
      );
      console.log('Created default admin user');
    }
  } catch (err) {
    console.error('Error initializing admin user:', err);
  }
}).catch(err => {
  console.error('Database initialization error:', err);
  throw err;
});

// Require an authenticated session for application data and mutations.
function requireAuth(req, res, next) {
  if (req.session && req.session.user_id) {
    return next();
  }
  return res.status(401).json({ error: 'Unauthorized' });
}

function requireAdmin(req, res, next) {
  if (req.session && req.session.role === 'Admin') {
    return next();
  }
  return res.status(req.session && req.session.user_id ? 403 : 401).json({ error: 'Forbidden' });
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
    
    // Set session
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
    const lowStock = await pool.query("SELECT COUNT(*) AS c FROM uniform_items WHERE quantity_in_stock <= 5");
    
    const currentTerm = await pool.query("SELECT * FROM academic_terms WHERE is_current = true LIMIT 1");
    
    const recentPayments = await pool.query(`
          SELECT fp.payment_id, fp.amount_paid, fp.date_paid, fp.receipt_number,
            s.student_id, s.admission_number, s.full_name, s.is_boarder,
            c.class_id, c.class_name, c.section, c.term_fee
      FROM fee_payments fp 
      JOIN students s ON fp.student_id = s.student_id AND s.status = 'Active'
      JOIN classes c ON s.class_id = c.class_id 
      ORDER BY fp.payment_id DESC LIMIT 5
    `);
    
    res.json({
      total_students: parseInt(totalStudents.rows[0].c),
      total_boarders: parseInt(totalBoarders.rows[0].c),
      total_day: parseInt(totalDay.rows[0].c),
      total_classes: 10,
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
    if (!gender || !['Male', 'Female'].includes(gender)) {
      return res.status(400).json({ error: 'Valid gender (Male or Female) is required.' });
    }
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
    const result = await pool.query(
      "UPDATE students SET status = 'Withdrawn' WHERE student_id = $1 RETURNING student_id",
      [req.params.id]
    );
    if (result.rowCount === 0) return res.status(404).json({ error: 'Student not found' });
    res.json({ success: true, student_id: result.rows[0].student_id });
  } catch (err) {
    console.error('Delete student error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== CLASSES ====================

app.get('/api/classes', requireAuth, async (req, res) => {
  try {
    await databaseReady;
    const result = await pool.query(`
      SELECT * FROM (
        SELECT DISTINCT ON (LOWER(BTRIM(class_name)), LOWER(BTRIM(section))) *
        FROM classes
        WHERE (LOWER(BTRIM(class_name)), LOWER(BTRIM(section))) IN (
          ('baby class', 'nursery'),
          ('middle class', 'nursery'),
          ('top class', 'nursery'),
          ('p.1', 'lower primary'),
          ('p.2', 'lower primary'),
          ('p.3', 'lower primary'),
          ('p.4', 'lower primary'),
          ('p.5', 'upper primary'),
          ('p.6', 'upper primary'),
          ('p.7', 'upper primary')
        )
        ORDER BY LOWER(BTRIM(class_name)), LOWER(BTRIM(section)), class_id
      ) AS unique_classes
      ORDER BY class_id
    `);
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
      SELECT fp.*, s.full_name, s.admission_number, c.class_name, c.section
      FROM fee_payments fp
      JOIN students s ON fp.student_id = s.student_id AND s.status = 'Active'
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
  const { student_id, term_id, amount_paid, payment_method, received_by, notes, discount_amount, discount_reason } = req.body;
  const discountAmount = Number(discount_amount) || 0;
  const discountReason = (discount_reason || '').trim();
  if (discountAmount < 0 || (discountAmount > 0 && !discountReason)) {
    return res.status(400).json({ error: 'Enter a reason for the fee discount.' });
  }
  const client = await pool.connect();
  try {
    const receiptNumber = req.body.receipt_number?.trim()
      || `RCT-${new Date().toISOString().slice(0, 10).replace(/-/g, '')}-${crypto.randomBytes(3).toString('hex').toUpperCase()}`;
    await client.query('BEGIN');
    const result = await client.query(`
      INSERT INTO fee_payments (student_id, term_id, amount_paid, payment_method, received_by, receipt_number, notes)
      VALUES ($1, $2, $3, $4, $5, $6, $7)
      RETURNING *
    `, [student_id, term_id, amount_paid, payment_method, received_by, receiptNumber, notes]);
    if (discountAmount > 0) {
      await client.query(`
        INSERT INTO fee_discounts (student_id, term_id, discount_amount, reason, approved_by)
        VALUES ($1, $2, $3, $4, $5)
      `, [student_id, term_id, discountAmount, discountReason, req.session.full_name || req.session.username]);
    }
    await client.query('COMMIT');
    res.json(result.rows[0]);
  } catch (err) {
    await client.query('ROLLBACK').catch(() => {});
    console.error('Create fee payment error:', err);
    res.status(500).json({ error: 'Server error' });
  } finally {
    client.release();
  }
});

app.get('/api/fee-payments/:id/receipt', requireAuth, async (req, res) => {
  try {
    const result = await pool.query(`
      SELECT
        fp.payment_id,
        fp.receipt_number,
        fp.date_paid,
        fp.amount_paid,
        fp.payment_method,
        fp.received_by,
        fp.notes,
        s.student_id,
        s.full_name,
        s.admission_number,
        s.is_boarder,
        c.class_name,
        c.section,
        at.term_number,
        at.academic_year,
        GREATEST(
          CASE WHEN s.is_boarder THEN COALESCE((SELECT amount FROM boarding_fee ORDER BY id LIMIT 1), 0)
               ELSE c.term_fee END
          - COALESCE((SELECT SUM(discount_amount) FROM fee_discounts WHERE student_id = s.student_id AND term_id = fp.term_id), 0),
          0
        ) AS total_due,
        COALESCE((
          SELECT SUM(amount_paid)
          FROM fee_payments
          WHERE student_id = s.student_id AND term_id = fp.term_id
        ), 0) AS total_paid
      FROM fee_payments fp
      JOIN students s ON s.student_id = fp.student_id
      JOIN classes c ON c.class_id = s.class_id
      JOIN academic_terms at ON at.term_id = fp.term_id
      WHERE fp.payment_id = $1
    `, [req.params.id]);

    if (result.rowCount === 0) {
      return res.status(404).json({ error: 'Receipt not found' });
    }

    res.json(result.rows[0]);
  } catch (err) {
    console.error('Fee receipt error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== EDIT FEE PAYMENT ====================

app.put('/api/fee-payments/:id', requireAuth, async (req, res) => {
  try {
    const { amount_paid, payment_method, received_by, receipt_number, notes } = req.body;
    const result = await pool.query(`
      UPDATE fee_payments 
      SET amount_paid = COALESCE($1, amount_paid),
          payment_method = COALESCE($2, payment_method),
          received_by = COALESCE($3, received_by),
          receipt_number = COALESCE($4, receipt_number),
          notes = COALESCE($5, notes)
      WHERE payment_id = $6
      RETURNING *
    `, [amount_paid, payment_method, received_by, receipt_number, notes, req.params.id]);
    res.json(result.rows[0]);
  } catch (err) {
    console.error('Update fee payment error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== FEE DISCOUNTS / WAIVERS ====================

app.post('/api/fee-discounts', requireAuth, async (req, res) => {
  try {
    const { student_id, term_id, discount_amount, reason, approved_by } = req.body;
    if (!(Number(discount_amount) > 0) || !String(reason || '').trim()) {
      return res.status(400).json({ error: 'A positive discount amount and reason are required.' });
    }
    const result = await pool.query(`
      INSERT INTO fee_discounts (student_id, term_id, discount_amount, reason, approved_by)
      VALUES ($1, $2, $3, $4, $5)
      RETURNING *
    `, [student_id, term_id, discount_amount, reason, approved_by]);
    res.status(201).json({ success: true, discount: result.rows[0] });
  } catch (err) {
    console.error('Create fee discount error:', err);
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

app.put('/api/uniform-items/:id', requireAuth, async (req, res) => {
  try {
    const { item_name, applicable_section, applicable_gender, price, quantity_in_stock } = req.body;
    const result = await pool.query(`
      UPDATE uniform_items
      SET item_name = $1, applicable_section = $2, applicable_gender = $3,
          price = $4, quantity_in_stock = $5
      WHERE uniform_id = $6
      RETURNING *
    `, [item_name, applicable_section, applicable_gender, price, quantity_in_stock, req.params.id]);
    if (result.rowCount === 0) return res.status(404).json({ error: 'Uniform item not found' });
    res.json(result.rows[0]);
  } catch (err) {
    console.error('Update uniform item error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.delete('/api/uniform-items/:id', requireAuth, async (req, res) => {
  try {
    const result = await pool.query('DELETE FROM uniform_items WHERE uniform_id = $1 RETURNING uniform_id', [req.params.id]);
    if (result.rowCount === 0) return res.status(404).json({ error: 'Uniform item not found' });
    res.json({ success: true });
  } catch (err) {
    if (err.code === '23503') {
      return res.status(409).json({ error: 'This uniform has issue history and cannot be deleted.' });
    }
    console.error('Delete uniform item error:', err);
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
      WITH expected_classes(class_name, section, term_fee, sort_order) AS (
        VALUES
          ('Baby Class', 'Nursery', 80000, 1),
          ('Middle Class', 'Nursery', 80000, 2),
          ('Top Class', 'Nursery', 80000, 3),
          ('P.1', 'Lower Primary', 90000, 4),
          ('P.2', 'Lower Primary', 90000, 5),
          ('P.3', 'Lower Primary', 90000, 6),
          ('P.4', 'Lower Primary', 90000, 7),
          ('P.5', 'Upper Primary', 90000, 8),
          ('P.6', 'Upper Primary', 90000, 9),
          ('P.7', 'Upper Primary', 90000, 10)
      )
      SELECT 
        expected.class_name,
        expected.section,
        COALESCE(MIN(class_records.term_fee), expected.term_fee) AS term_fee,
        COUNT(DISTINCT s.student_id) AS student_count,
        COALESCE(SUM(fp.amount_paid), 0) AS total_collected
      FROM expected_classes expected
      LEFT JOIN classes class_records
        ON LOWER(BTRIM(class_records.class_name)) = LOWER(BTRIM(expected.class_name))
        AND LOWER(BTRIM(class_records.section)) = LOWER(BTRIM(expected.section))
      LEFT JOIN students s
        ON s.class_id = class_records.class_id
        AND s.status = 'Active'
      LEFT JOIN fee_payments fp ON s.student_id = fp.student_id
      GROUP BY expected.class_name, expected.section, expected.term_fee, expected.sort_order
      ORDER BY expected.sort_order
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

// ==================== WITHDRAWN STUDENTS HISTORY ====================

app.get('/api/withdrawn-students', requireAuth, async (req, res) => {
  try {
    const result = await pool.query(`
      SELECT s.full_name, s.admission_number, c.class_name, c.section,
        fp.payment_id, fp.amount_paid, fp.date_paid, fp.receipt_number,
        fp.payment_method, fp.received_by, at.term_number, at.academic_year
      FROM students s
      JOIN classes c ON s.class_id = c.class_id
      JOIN fee_payments fp ON s.student_id = fp.student_id
      JOIN academic_terms at ON fp.term_id = at.term_id
      WHERE s.status = 'Withdrawn'
      ORDER BY c.class_name, s.full_name, fp.payment_id DESC
    `);
    res.json(result.rows);
  } catch (err) {
    console.error('Withdrawn students error:', err);
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

// ==================== FEE DEFAULTS / MANAGE FEES ====================

app.get('/api/fees', requireAuth, async (req, res) => {
  try {
    const result = await pool.query('SELECT * FROM fee_defaults ORDER BY label');
    res.json(result.rows);
  } catch (err) {
    console.error('Fees error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

app.post('/api/fees', requireAuth, async (req, res) => {
  try {
    const { fee_key, label, amount } = req.body;
    if (!fee_key || !label || amount === undefined || amount < 0) {
      return res.status(400).json({ error: 'fee_key, label, and non-negative amount are required.' });
    }
    const result = await pool.query(`
      INSERT INTO fee_defaults (fee_key, label, amount) VALUES ($1, $2, $3)
      ON CONFLICT (fee_key) DO UPDATE SET amount = $3, label = $4
      RETURNING *
    `, [fee_key, label, amount, label]);
    res.json(result.rows[0]);
  } catch (err) {
    console.error('Fee defaults error:', err);
    res.status(500).json({ error: 'Server error' });
  }
});

// ==================== TERMS ====================

if (require.main === module) {
  databaseReady.then(() => {
    app.listen(PORT, () => {
      console.log(`Server running on port ${PORT}`);
    });
  }).catch(() => {
    process.exitCode = 1;
  });
}

module.exports = app;