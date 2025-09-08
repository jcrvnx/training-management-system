# Training Management System - Deployment Guide

## Overview
This guide covers the complete deployment process for the enhanced Training Management System with quiz management, certificate generation, notifications, and advanced analytics.

## Prerequisites
- XAMPP 2015 or newer (PHP 5.6+, MySQL 5.7+)
- Web browser with JavaScript enabled
- Administrative access to the database

## Installation Steps

### 1. Database Setup

#### Step 1.1: Create Database
```sql
CREATE DATABASE training_management_system;
USE training_management_system;
```

#### Step 1.2: Run Initial Schema
Execute the main schema file in phpMyAdmin:
- Navigate to `http://localhost/phpmyadmin`
- Select your database
- Go to **SQL** tab
- Import and execute: `schema/tms_db.sql`

#### Step 1.3: Run Migration Files (CRITICAL)
Execute these migration files in order:

**Migration 001 - Enhanced User Roles:**
```bash
File: schema/migrations/001_enhanced_user_roles.sql
```

**Migration 002 - Course Management Enhancements:**
```bash
File: schema/migrations/002_course_management_enhancements.sql
```

**Migration 003 - Quiz and Assessment System:**
```bash
File: schema/migrations/003_quiz_and_assessment_system.sql
```

**Migration 004 - Notifications and System Configuration:**
```bash
File: schema/migrations/004_notifications_and_system_config.sql
```

### 2. File System Setup

#### Step 2.1: Directory Permissions
Ensure these directories exist and are writable:
```
uploads/
uploads/course_materials/
uploads/certificates/
uploads/user_avatars/
```

#### Step 2.2: Create Upload Directories
```bash
mkdir uploads
mkdir uploads/course_materials
mkdir uploads/certificates  
mkdir uploads/user_avatars
chmod 755 uploads/
chmod 755 uploads/course_materials/
chmod 755 uploads/certificates/
chmod 755 uploads/user_avatars/
```

### 3. Configuration

#### Step 3.1: Database Configuration
Edit `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'training_management_system');
define('BASE_URL', 'http://localhost/training-management-system/');
```

#### Step 3.2: Email Configuration (Optional)
Update system settings via admin panel or database:
```sql
UPDATE system_settings SET setting_value = 'your-smtp-host' WHERE setting_key = 'smtp_host';
UPDATE system_settings SET setting_value = 'your-email@domain.com' WHERE setting_key = 'from_email';
```

### 4. Initial Setup

#### Step 4.1: Run Installation
1. Navigate to: `http://localhost/training-management-system/`
2. If redirected to install.php, complete the installation wizard
3. Create the first admin user

#### Step 4.2: Verify Installation
Run the integration test suite:
1. Navigate to: `http://localhost/training-management-system/test_integration.php`
2. Login as admin if prompted
3. Review test results - all should pass if setup is correct

## Feature Overview

### Core Features
- **User Management**: Role-based access (Admin, Trainer, Student)
- **Training Management**: Create, schedule, and manage training programs
- **Enrollment System**: Enroll users and track attendance
- **Course Materials**: Upload and organize training materials

### Enhanced Features (New)
- **Quiz System**: Create quizzes, manage questions, track attempts
- **Certificate Generation**: Automatic certificate creation on completion
- **Notification System**: Email and in-app notifications
- **Advanced Analytics**: Role-based dashboard with comprehensive stats
- **File Upload Security**: Secure file handling with validation
- **Progress Tracking**: Detailed user progress monitoring

## User Roles and Permissions

### Administrator
- Full system access
- User and role management
- System configuration
- View all reports and analytics

### Trainer
- Create and manage trainings
- Create quizzes and questions
- View assigned training analytics
- Manage enrollments for their trainings

### Student
- View assigned trainings
- Take quizzes and view results
- Download earned certificates
- Track personal progress

## Testing Checklist

### Database Tests
- [ ] All migration tables exist
- [ ] Default data inserted (templates, settings)
- [ ] Foreign key relationships working
- [ ] User permissions configured

### Functionality Tests
- [ ] User login/logout works
- [ ] Role-based permissions enforced
- [ ] Training creation and management
- [ ] Quiz creation and taking
- [ ] Certificate generation
- [ ] Notification sending
- [ ] File upload functionality

### Security Tests
- [ ] SQL injection protection
- [ ] XSS prevention
- [ ] File upload restrictions
- [ ] Session management
- [ ] Permission validation

## Common Issues and Solutions

### Migration Failures
**Problem**: Migration tables not created
**Solution**: 
1. Check MySQL version compatibility
2. Run migrations manually via phpMyAdmin
3. Verify user has CREATE TABLE permissions

### File Upload Issues
**Problem**: Cannot upload files
**Solution**:
1. Check directory permissions (755 or 777)
2. Verify PHP upload settings in php.ini
3. Ensure upload directories exist

### Email Not Sending
**Problem**: Notifications not being sent
**Solution**:
1. Configure SMTP settings in system_settings table
2. Test with simple mail() function
3. Check server mail configuration

### Performance Issues
**Problem**: Dashboard loading slowly
**Solution**:
1. Check database indexes on large tables
2. Optimize queries in dashboard.php
3. Consider pagination for large datasets

## Maintenance

### Daily Tasks
- Monitor notification queue
- Check failed email attempts
- Review system error logs

### Weekly Tasks
- Database backup
- Clean old notification records
- Update system statistics

### Monthly Tasks
- Review user activity
- Archive completed trainings
- Update certificate templates

## Security Recommendations

### Database Security
- Use strong database passwords
- Limit database user permissions
- Regular security updates

### Application Security
- Keep PHP and MySQL updated
- Regular password policy enforcement
- Monitor for suspicious activities
- Backup data regularly

### File Security
- Restrict executable file uploads
- Implement virus scanning (if available)
- Regular cleanup of temporary files

## Performance Optimization

### Database Optimization
```sql
-- Add indexes for better performance
CREATE INDEX idx_enrollments_user_training ON enrollments(user_id, training_id);
CREATE INDEX idx_quiz_attempts_user_status ON quiz_attempts(user_id, status);
CREATE INDEX idx_notifications_user_status ON notifications(user_id, status);
```

### PHP Optimization
- Enable PHP OpCache
- Optimize included files
- Use connection pooling for database

## Backup and Recovery

### Database Backup
```bash
mysqldump -u root -p training_management_system > tms_backup_$(date +%Y%m%d).sql
```

### File Backup
```bash
tar -czf tms_files_$(date +%Y%m%d).tar.gz uploads/
```

### Recovery Process
1. Restore database from backup
2. Restore uploaded files
3. Verify system configuration
4. Run integration tests

## Support and Documentation

### Log Files
- PHP errors: Check XAMPP error logs
- Application logs: Check includes/logs/ (if implemented)
- Database logs: MySQL error logs

### Debugging
1. Enable PHP error reporting in development
2. Use integration test suite for diagnostics
3. Check browser console for JavaScript errors

### Getting Help
- Review error messages in integration test
- Check database table structure
- Verify file permissions and paths
- Consult PHP and MySQL documentation

---

**Deployment Date**: $(date +%Y-%m-%d)
**Version**: 2.0 Enhanced
**Compatibility**: XAMPP 2015+, PHP 5.6+, MySQL 5.7+
