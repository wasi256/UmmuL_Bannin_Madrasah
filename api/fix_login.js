// This script ensures the admin user is properly set up
// Run: node api/fix_login.js

const { Pool } = require('pg');
const bcrypt = require('bcrypt');

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: { rejectUnauthorized: false }
};

async function fixLogin() {
  try {
    // First, delete any existing admin
    await pool.query('DELETE FROM users WHERE username = \'admin\'');
    console.log('Deleted existing admin user');
    
    // Generate fresh bcrypt hash
    const hash = await bcrypt.hash('admin123', 10);
    console.log('Generated hash:', hash);
    
    // Insert admin user
    const result = await pool.query(
      `INSERT INTO users (username, password_hash, full_name, role) 
       VALUES ($1, $2, $3, 'Admin') 
       RETURNING username, role`,
      ['admin', hash, 'Admin User']
    );
    console.log('Created admin user:', result.rows[0]);
    
    // Verify the hash works
    const user = result.rows[0];
    const valid = await bcrypt.compare('admin123', user.password_hash);
    console.log('Does admin123 match hash?', valid);
    
    // List all users to verify
    const allUsers = await pool.query('SELECT username, role FROM users');
    console.log('All users:', allUsers.rows);
    
    console.log('\\nFix complete! You can now login with: admin / admin123');
    
  } catch (err) {
    console.error('Fix failed:', err);
  } finally {
    await pool.end();
  }
}

fixLogin();
