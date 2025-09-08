# Training Management System - Codebase Structure

## 🏗️ System Architecture

This TMS uses a **hybrid PHP/CodeIgniter 3 structure** optimized for XAMPP environments.

### 📁 Directory Structure

```
training-management-system/
├── 📄 index.php                 # Main entry point
├── 📄 install_schema.php        # Single installation system  
├── 📄 dashboard.php             # Main dashboard (standalone PHP)
├── 📄 install_complete.flag     # Installation completion marker
├── 📁 auth/                     # Authentication pages
├── 📁 enrollments/              # Enrollment management
├── 📁 trainings/                # Training management  
├── 📁 users/                    # User management
├── 📁 quizzes/                  # Quiz system
├── 📁 certificates/             # Certificate management
├── 📁 includes/                 # Shared PHP functions & DB
├── 📁 templates/                # HTML templates
├── 📁 uploads/                  # File uploads
├── 📁 assets/                   # CSS/JS assets
├── 📁 application/              # CodeIgniter 3 structure
│   ├── 📁 controllers/          # CI3 controllers (backup/future)
│   ├── 📁 models/               # CI3 models (backup/future)  
│   ├── 📁 views/                # CI3 views (backup/future)
│   ├── 📁 libraries/            # CI3 custom libraries
│   └── 📁 config/               # CI3 configuration
└── 📁 schema/                   # Database schema & migrations
```

## 🎯 Installation System

### Single Installation Process
- **File:** `install_schema.php`
- **Database:** `training_management_system` (23 tables)
- **Completion:** Creates `install_complete.flag`
- **Redirect:** Auto-redirects to `auth/login.php` after completion

### Installation Flow
1. Visit: `http://localhost/training-management-system/`
2. If not installed → redirects to `install_schema.php`
3. Installer creates database, directories, config files
4. Shows "Installation finished. You will be redirected to the login page."
5. Auto-redirects to login after 3 seconds

## 💾 Database Schema

**Database Name:** `training_management_system`
**Tables (23):** users, trainings, enrollments, quizzes, certificates, etc.
**Installation:** Via `schema/tms_db.sql` (consolidated schema)

## 🔐 Authentication & Security

### Login System
- **File:** `auth/login.php`
- **Features:** Role-based access, session management
- **Roles:** admin, trainer, student

### Security Features
- SQL injection prevention (prepared statements)
- XSS protection (sanitization)
- Directory protection (`.htaccess` files)
- Upload restrictions

## 🎨 UI Framework

- **CSS:** Bootstrap 5.3.0 
- **Icons:** Font Awesome 6.0
- **Theme:** Modern, responsive design
- **Layout:** Sidebar navigation with role-based menus

## 🔧 Configuration

### Database Config
- **File:** `includes/config.php` & `application/config/database.php`
- **Connection:** `includes/db.php`

### System Settings
- **Base URL:** Configured in `includes/config.php`
- **Upload Paths:** `uploads/` directory with subdirectories

## 📋 Key Features

### ✅ Core Functionality
- **User Management:** CRUD operations, role management
- **Training Management:** Course creation, materials, sessions
- **Enrollment System:** Registration, progress tracking
- **Quiz System:** Creation, attempts, scoring, results
- **Certificate System:** Auto-generation, templates, downloads
- **Dashboard:** Statistics, recent activities, quick actions

### 🔄 System Managers
- **Notification Manager:** Email notifications, reminders
- **PDF Generator:** Certificate generation, reports
- **Upload Manager:** File handling, validation
- **Quiz Manager:** Question handling, scoring logic
- **Certificate Manager:** Template processing, generation

## 🚀 Getting Started

### Prerequisites
- XAMPP with PHP 7.0+ and MySQL 5.7+
- Web browser with JavaScript enabled

### Quick Setup
1. Extract to `C:\xampp\htdocs\training-management-system\`
2. Start XAMPP (Apache + MySQL)
3. Visit: `http://localhost/training-management-system/`
4. Follow installation wizard
5. Login with created admin account

### Default Test Accounts (after installation)
- **Admin:** admin@example.com / admin123
- **Trainer:** trainer@example.com / trainer123
- **Student:** student@example.com / student123

## 🛠️ Development Notes

### Hybrid Structure Benefits
- **Immediate functionality:** Works without full CI3 installation
- **Future-ready:** CI3 components available for expansion
- **Flexible:** Mix of standalone PHP and framework components
- **Performance:** Lightweight structure, fast loading

### File Organization
- **Standalone Pages:** Direct PHP files for immediate functionality
- **CI3 Components:** Available for advanced features
- **Shared Logic:** `includes/` directory for common functions
- **Clean Separation:** Templates, uploads, assets properly organized

## 📝 Maintenance

### Regular Tasks
- Monitor `uploads/` directory size
- Backup database regularly
- Update user passwords periodically
- Review access logs

### Updates
- Database schema updates via `install_schema.php`
- Feature additions can use either standalone PHP or CI3 structure
- Configuration updates in `includes/config.php`

---

**System Status:** ✅ Production Ready
**Last Updated:** Installation completed successfully
**Version:** 1.0.0
