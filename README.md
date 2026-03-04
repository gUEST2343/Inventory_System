# Inventory System - Setup Instructions

A complete PHP and XAMPP-based Inventory Management System with user authentication.

## 📁 File Structure

```
htdocs/inventory-system/
├── sql/
│   └── inventory_system.sql     # Database creation script
├── db_connect.php              # Database connection (PDO)
├── login.php                  # Login page with authentication
├── login.css                  # Professional login page styles
├── dashboard.php             # Main dashboard (after login)
├── logout.php                 # Logout script
└── README.md                  # This file
```

## 🗄️ Database Information

- **Database Name**: `inventory_system`
- **Tables Created**:
  - `users` - User authentication (admin, manager, staff)
  - `categories` - Product categories (hierarchical)
  - `products` - Inventory items
  - `stock_logs` - Audit trail for inventory changes

### Default Admin User
- **Username**: `admin`
- **Password**: `admin123`
- **Email**: admin@inventorysystem.com
- **Role**: admin

## ⚡ Quick Setup Guide

### Step 1: Place Files in XAMPP

1. Make sure XAMPP is installed (usually at `C:\xampp` on Windows)
2. Navigate to the XAMPP htdocs folder:
   - Windows: `C:\xampp\htdocs\`
   - macOS: `/Applications/XAMPP/htdocs/`
   - Linux: `/opt/lampp/htdocs/`
3. Create a new folder called `inventory-system`
4. Copy all the files from this package into `inventory-system/`

### Step 2: Import Database

1. Start Apache and MySQL in XAMPP Control Panel
2. Open your browser and go to: http://localhost/phpmyadmin
3. Click on "New" to create a new database
4. Database name: `inventory_system`
5. Select "utf8mb4_unicode_ci" as collation
6. Click "Create"
7. Click on the "Import" tab
8. Click "Choose File" and select `sql/inventory_system.sql`
9. Scroll down and click "Go" or "Import"
10. You should see success message with all tables created

### Step 3: Access the Login Page

Open your browser and go to:
```
http://localhost/inventory-system/login.php
```

### Step 4: Login

Use the default credentials:
- **Username**: `admin`
- **Password**: `admin123`

## 🔧 Configuration

### Database Connection (db_connect.php)

The default connection settings are:
```
php
$host = 'localhost';
$port = '3306';
$dbname = 'inventory_system';
$username = 'root';
$password = '';  // Default XAMPP has no password
```

If you need to change these settings, edit `db_connect.php`:
```
php
$host = 'your_host';
$port = '3306';
$dbname = 'inventory_system';
$username = 'your_username';
$password = 'your_password';
```

## 📋 Features Included

### 1. Database Creation
- ✅ MySQL database `inventory_system`
- ✅ `users` table with role-based access (admin, manager, staff)
- ✅ `categories` table with parent-child hierarchy support
- ✅ `products` table with SKU, barcode, pricing, quantity
- ✅ `stock_logs` table for audit trail
- ✅ Sample data included

### 2. Database Connection
- ✅ PDO-based connection
- ✅ Error handling with try-catch
- ✅ Secure connection settings (utf8mb4, prepared statements)
- ✅ Reusable `getDBConnection()` function

### 3. User Authentication
- ✅ SQL injection prevention (prepared statements)
- ✅ Password hashing with bcrypt (`password_hash`)
- ✅ Password verification with `password_verify()`
- ✅ Secure session management
- ✅ Session fixation protection (regenerate session ID)
- ✅ Redirect to dashboard after login

### 4. Professional Login Page
- ✅ Modern, clean, centered design
- ✅ Responsive styling (mobile-friendly)
- ✅ Form validation
- ✅ Error messages with animations
- ✅ SVG icons
- ✅ Gradient background with pattern overlay
- ✅ Hover effects and transitions

### 5. Dashboard
- ✅ Protected page (redirects if not logged in)
- ✅ User info display (name, role)
- ✅ Statistics cards
- ✅ Quick action buttons
- ✅ Logout functionality

## 🚀 Additional Features

### Password Security
The default password `admin123` is hashed using bcrypt:
```
php
$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
```

To create a new hashed password:
```
php
echo password_hash('your_password', PASSWORD_BCRYPT);
```

### Role-Based Access
- **admin**: Full access to all features
- **manager**: Can manage inventory
- **staff**: View-only access

## 📝 File Descriptions

| File | Description |
|------|-------------|
| `sql/inventory_system.sql` | Complete database setup with tables and sample data |
| `db_connect.php` | PDO database connection with error handling |
| `login.php` | Login form with authentication logic |
| `login.css` | Professional styling for login page |
| `dashboard.php` | Main dashboard after successful login |
| `logout.php` | Destroys session and redirects to login |

## 🔍 Troubleshooting

### "Database connection failed" Error
1. Make sure MySQL is running in XAMPP
2. Check that database name is `inventory_system`
3. Verify username is `root` and password is empty (default XAMPP)

### "Invalid username or password" Error
1. Make sure you imported the SQL file successfully
2. Check that the users table has data
3. Default credentials: username=`admin`, password=`admin123`

### Blank Page
1. Check PHP error logs in XAMPP
2. Make sure all files are in the correct location
3. Verify PHP version is 7.4 or higher

## 📞 Support

For issues or questions, please check:
1. XAMPP control panel is running Apache and MySQL
2. Files are in correct folder: `C:\xampp\htdocs\inventory-system\`
3. Database was imported correctly in phpMyAdmin

## ✅ Testing the Setup

After setup, verify everything works:
1. ✅ Open http://localhost/inventory-system/login.php
2. ✅ Login with admin/admin123
3. ✅ Should redirect to dashboard.php
4. ✅ Click "Logout" should return to login.php

---

**Created for**: Inventory System using PHP and XAMPP
**Last Updated**: 2024
