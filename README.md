# Training Management System 🚀

![Version](https://img.shields.io/badge/version-1.0-blue)
![PHP](https://img.shields.io/badge/PHP-7.4+-purple)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange)
![License](https://img.shields.io/badge/license-MIT-green)

<div align="center">
  <img src="https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExNzM0OGJjNjU2OGM1YmJiZWI2MTlmZDUzNzYxNWQyNGNhOTcxNzAxNyZjdD1n/3oKIPsx2VAYAgEHC12/giphy.gif" width="450px">
</div>

A comprehensive PHP-based system for managing training programs, users, and enrollments. Built with PHP and Bootstrap, it provides a robust solution for educational institutions, corporate training departments, and training providers.

**An IT Project by Joel Moreno**  
**Developed by [kingsolomonwisdom (Jcrist Vincent Orhen)](https://github.com/kingsolomonwisdom)**

<a href="https://github.com/jcrvnx">
  <img src="https://img.shields.io/github/followers/jcrvnx?label=Follow%20%40jcrvnx&style=social" alt="Follow @jcrvnx" />
</a>

## ✨ Features

<div align="center">
  <img src="https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExanZwYXcwZzU3ZjI5eWZoZHAyaHo0MzF6eHY2ZGh3cWlrZGpwdWZjaCZjdD1n/077i6AULCXc0FKTj9s/giphy.gif" width="350px">
</div>

- 🔐 **User Authentication System**
  - Secure login/logout functionality
  - Password hashing and protection
  - Session management

- 👥 **User Management**
  - Role-based access control (Admin/User)
  - User registration with email validation
  - User profile management
  - Administrator user management capabilities

- 📚 **Training Management**
  - Create, read, update, and delete training programs
  - Training categorization and scheduling
  - Capacity management for each training
  - Start/end date management

- ✅ **Enrollment Management**
  - Easy enrollment process for users
  - Enrollment tracking and management
  - Capacity checks and validations
  - Enrollment reporting

- 📊 **Dashboard & Analytics**
  - Summary statistics for administrators
  - Visual representation of key metrics
  - Recent activity tracking
  - Quick access to all system features

- 🎨 **Modern UI/UX Design**
  - Responsive design using Bootstrap 5
  - Mobile-friendly interface
  - Clean and intuitive user experience
  - Interactive components with animations

- 🛠️ **System Features**
  - Automatic database setup and installation
  - Error handling and logging
  - Data validation and sanitization
  - Optimized database queries

## 🔧 Technical Concepts

This system implements several important web development concepts:

- **MVC-like Architecture**: Separation of data, logic, and presentation
- **Authentication & Authorization**: Secure user access control
- **Database Design**: Relational database with referential integrity
- **CRUD Operations**: Standard create, read, update, delete functionality
- **Input Validation**: Client and server-side validation
- **Session Management**: Secure user sessions
- **Responsive Design**: Mobile-first approach with Bootstrap
- **Error Handling**: Comprehensive error detection and reporting

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)
- Modern web browser
- Internet connection (for CDN resources)

## 🚀 Installation

<div align="center">
  <img src="https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExZ2g0djRiYnRsYTF2bWcxMmgwZTAxY3g1bGd3cGU1Z294MTJtb3V1dSZjdD1n/LMt9638dO8dftAjtco/giphy.gif" width="200px">
</div>

1. **Clone the repository** to your web server's document root:
   ```bash
   git clone https://github.com/jcrvnx/training-management-system.git
   ```

2. **Configure the database connection** in `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   define('DB_NAME', 'tms_db');
   ```

3. **Update the `BASE_URL` constant** in `includes/config.php` to match your installation directory:
   ```php
   define('BASE_URL', 'http://localhost/training-management-system');
   ```

4. **Access the application** in your web browser. The system will automatically:
   - Check if the database exists
   - Create the database if it doesn't exist
   - Set up all required tables
   - Provide a registration form to create the first admin account

5. **Register the first user**:
   - The first user who registers will automatically become an administrator
   - Subsequent registrations will create regular user accounts

## 📝 Usage Guide

<div align="center">
  <img src="https://media.giphy.com/media/v1.Y2lkPTc5MGI3NjExbzhxY3RwbWhyOGJwOGdoOWEzajN2OXRjMXNtYnViM2Y0NmV1dTg1aiZjdD1n/scZPhLqaVOM1qG4lT9/giphy.gif" width="350px">
</div>

### For Administrators
1. **Log in** with your administrator credentials
2. Use the **Dashboard** to view system statistics and recent activities
3. **Manage Trainings** - Create, edit, and delete training programs
4. **Manage Users** - View, edit, and manage user accounts
5. **Manage Enrollments** - Track and manage user enrollments

### For Users
1. **Register** for an account or **log in** with existing credentials
2. Browse available **training programs** from the training list
3. **Enroll** in training programs of interest
4. View your **enrollment history**
5. **Update your profile** information when needed

## 📁 Directory Structure

```
/training-management-system/
│
├── /assets/            # Static assets (CSS, JS)
│   ├── /css/           # CSS stylesheets
│   └── /js/            # JavaScript files
│
├── /includes/          # Configuration and utilities
│   ├── config.php      # System configuration
│   ├── db.php          # Database connection
│   └── functions.php   # Helper functions
│
├── /templates/         # Page templates and layout files
│
├── /auth/              # Authentication files
│   ├── login.php       # Login form
│   ├── logout.php      # Logout process
│   └── register.php    # Registration form
│
├── /trainings/         # Training management
│   ├── index.php       # List trainings
│   ├── create.php      # Create new training
│   ├── edit.php        # Edit existing training
│   └── delete.php      # Delete training
│
├── /users/             # User management
│
├── /enrollments/       # Enrollment management
│
├── dashboard.php       # Dashboard page
├── index.php           # Entry point
├── install.php         # Automatic installation script
└── install_complete.flag # Flag file indicating installation status
```

## 📜 License

MIT

---

<div align="center">
  <p>Made with ❤️ by <a href="https://github.com/kingsolomonwisdom">kingsolomonwisdom</a></p>
  
  <a href="https://github.com/jcrvnx">
    <img src="https://img.shields.io/github/followers/jcrvnx?label=Follow%20%40jcrvnx&style=social" alt="Follow @jcrvnx" />
  </a>
</div> 