# Ummul Bannin Madrasah - School Management System

A complete school management system for Ummul Bannin Madrasah, built with Node.js, Express, and PostgreSQL (Neon). Deployed on Vercel.

## Features

- **Dashboard** - Overview of students, classes, and recent payments
- **Student Management** - Register, view, edit, and manage students
- **Fee Management** - Record payments, track balances, manage discounts
- **Uniform Management** - Track inventory, issue uniforms to students
- **Reports** - Student balances, class fee status
- **Academic Terms** - Manage academic years and terms
- **User Management** - Staff accounts with role-based access (Admin, Accountant, Teacher)

## Tech Stack

- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Backend**: Node.js, Express
- **Database**: PostgreSQL (Neon)
- **Authentication**: Session-based with bcrypt password hashing
- **Deployment**: Vercel

## Project Structure

```
UmmuL_Bannin_Madrasah/
├── api/
│   ├── index.js          # Express server & API routes
│   ├── db.js             # PostgreSQL connection & initialization
│   └── schema.sql        # Database schema reference
├── assets/
│   ├── css/
│   │   └── style.css     # Application styles
│   ├── js/
│   │   └── app.js        # Frontend application logic
│   └── images/
│       └── logo.png      # School logo
├── index.html            # Main application entry point
├── package.json          # Node.js dependencies
├── vercel.json           # Vercel deployment configuration
└── .env.example          # Environment variables template
```

## Local Development

### Prerequisites

- Node.js 18+
- PostgreSQL database (or Neon account)

### Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/wasi256/UmmuL_Bannin_Madrasah.git
   cd UmmuL_Bannin_Madrasah
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Create `.env` file:
   ```bash
   cp .env.example .env
   ```

4. Update `.env` with your database credentials:
   ```
   DATABASE_URL=your_postgresql_connection_string
   SESSION_SECRET=your_secret_key
   NODE_ENV=development
   ```

5. Start the development server:
   ```bash
   npm run dev
   ```

6. Open http://localhost:3000 in your browser

## Vercel Deployment

### Environment Variables

Set these in your Vercel project settings:

| Variable | Description |
|---|---|
| `DATABASE_URL` | Neon PostgreSQL connection string |
| `SESSION_SECRET` | Secret key for session encryption |
| `NODE_ENV` | Set to `production` |

### Deploy

1. Push your code to GitHub
2. Import the repository in Vercel
3. Set the environment variables
4. Deploy!

## Database

The database schema is automatically created on first run. Tables include:

- `classes` - School classes with fees
- `academic_terms` - Academic year/term tracking
- `students` - Student records
- `boarding_fee` - Boarding fee amount
- `fee_payments` - Payment records
- `fee_discounts` - Fee waivers/discounts
- `uniform_items` - Uniform inventory
- `uniform_issues` - Uniform sales/issues
- `users` - Staff accounts

## API Endpoints

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/login` | User login |
| POST | `/api/logout` | User logout |
| GET | `/api/auth/status` | Check auth status |
| GET | `/api/dashboard` | Dashboard statistics |
| GET | `/api/students` | List all students |
| POST | `/api/students` | Create new student |
| PUT | `/api/students/:id` | Update student |
| DELETE | `/api/students/:id` | Delete student |
| GET | `/api/classes` | List all classes |
| GET | `/api/fee-payments` | List fee payments |
| POST | `/api/fee-payments` | Record payment |
| GET | `/api/uniform-items` | List uniform items |
| POST | `/api/uniform-issues` | Issue uniform |
| GET | `/api/reports/class-fee-status` | Class fee report |
| GET | `/api/reports/student-balances` | Student balance report |
| GET | `/api/terms` | List academic terms |
| POST | `/api/terms` | Add academic term |
| GET | `/api/users` | List users (Admin) |
| POST | `/api/users` | Create user (Admin) |

## License

Private - Ummul Bannin Madrasah
