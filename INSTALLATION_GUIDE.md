# Training Management System - CodeIgniter 3 Installation Guide

## Overview

This is a comprehensive Training Management System (TMS) built with CodeIgniter 3 framework. The system provides a complete solution for managing training programs, tracking student progress, conducting quizzes, and issuing certificates.

## Features

- **User Management**: Role-based access control (Admin, Trainer, Student)
- **Training Programs**: Create and manage comprehensive training courses
- **Quiz System**: Interactive quizzes with multiple question types
- **Certificate Generation**: Automated certificate creation and management
- **Progress Tracking**: Real-time monitoring of student progress
- **Notification System**: Email notifications and in-app alerts
- **Reporting**: Comprehensive analytics and compliance reports
- **Material Management**: Upload and organize course materials

## System Requirements

### Minimum Requirements
- **PHP**: 7.2 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Web Server**: Apache 2.4+ or Nginx 1.10+
- **Extensions**: MySQLi, GD, cURL, JSON

### Recommended Requirements
- **PHP**: 7.4 or higher
- **Memory**: 128MB or more
- **Disk Space**: 500MB available
- **SSL Certificate**: For secure HTTPS connections

## Installation Methods

### Method 1: Web-Based Installation (Recommended)

1. **Download and Extract**
   ```bash
   # Extract files to your web server directory
   # For XAMPP: C:\xampp\htdocs\training-management-system\
   # For WAMP: C:\wamp64\www\training-management-system\
   ```

2. **Set Permissions**
   ```bash
   chmod 755 -R /path/to/tms/
   chmod 777 -R /path/to/tms/uploads/
   chmod 777 -R /path/to/tms/application/logs/
   ```

3. **Access Installation Wizard**
   ```
   http://localhost/training-management-system/install
   ```

4. **Follow the Installation Steps**
   - System requirements check
   - Database configuration
   - Database migration
   - Admin user setup
   - Installation completion

### Method 2: Manual Installation

1. **Create Database**
   ```sql
   CREATE DATABASE training_management_system;
   CREATE USER 'tms_user'@'localhost' IDENTIFIED BY 'secure_password';
   GRANT ALL PRIVILEGES ON training_management_system.* TO 'tms_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. **Configure Database Connection**
   Edit `application/config/database.php`:
   ```php
   $db['default'] = array(
       'dsn' => '',
       'hostname' => 'localhost',
       'username' => 'tms_user',
       'password' => 'secure_password',
       'database' => 'training_management_system',
       'dbdriver' => 'mysqli',
       // ... other settings
   );
   ```

3. **Import Database Schema**
   ```bash
   mysql -u tms_user -p training_management_system < schema/tms_db.sql
   ```

4. **Configure Base URL**
   Edit `application/config/config.php`:
   ```php
   $config['base_url'] = 'http://localhost/training-management-system/';
   ```

5. **Create Admin User**
   ```sql
   INSERT INTO users (email, password_hash, full_name, role, is_active, created_at) 
   VALUES ('admin@tms.local', '$2y$10$example_hash', 'System Administrator', 'admin', 1, NOW());
   ```

## Configuration

### Environment Configuration

1. **Set Environment**
   Edit `index.php`:
   ```php
   define('ENVIRONMENT', 'production'); // or 'development'
   ```

2. **Configure Encryption Key**
   Edit `application/config/config.php`:
   ```php
   $config['encryption_key'] = 'your-32-character-secret-key-here';
   ```

### Email Configuration

Edit `application/config/email.php`:
```php
$config['protocol'] = 'smtp';
$config['smtp_host'] = 'your-smtp-server.com';
$config['smtp_user'] = 'your-email@domain.com';
$config['smtp_pass'] = 'your-email-password';
$config['smtp_port'] = 587;
$config['smtp_crypto'] = 'tls';
```

### File Upload Configuration

Edit `application/config/config.php`:
```php
$config['max_size'] = 10240; // 10MB in KB
$config['allowed_types'] = 'pdf|doc|docx|xls|xlsx|ppt|pptx|jpg|jpeg|png|gif';
```

## Default Accounts

After installation, the following test accounts are available:

| Role | Email | Password | Description |
|------|-------|----------|-------------|
| Admin | admin@tms.local | admin123 | Full system access |
| Trainer | trainer@tms.local | trainer123 | Course management |
| Student | user@tms.local | user123 | Learning access |

**⚠️ Important**: Change or delete these accounts in production!

## Directory Structure

```
training-management-system/
├── application/
│   ├── config/          # Configuration files
│   ├── controllers/     # Application controllers
│   ├── models/         # Database models
│   ├── views/          # View templates
│   ├── libraries/      # Custom libraries
│   ├── helpers/        # Helper functions
│   └── migrations/     # Database migrations
├── assets/
│   ├── css/           # Stylesheets
│   └── js/            # JavaScript files
├── uploads/
│   ├── certificates/  # Generated certificates
│   ├── materials/     # Course materials
│   └── profiles/      # User profile images
├── schema/            # Database schema files
└── system/           # CodeIgniter core files
```

## Security Considerations

### Production Deployment

1. **Remove Installation Files**
   ```bash
   rm -rf application/controllers/Install.php
   rm -rf application/views/install/
   ```

2. **Secure File Permissions**
   ```bash
   chmod 644 application/config/*.php
   chmod 600 application/config/database.php
   ```

3. **Environment Variables**
   Use environment variables for sensitive data:
   ```php
   $config['encryption_key'] = $_ENV['TMS_ENCRYPTION_KEY'];
   ```

4. **HTTPS Configuration**
   ```php
   $config['base_url'] = 'https://yourdomain.com/';
   $config['cookie_secure'] = TRUE;
   ```

### Database Security

1. **Create Dedicated User**
   ```sql
   CREATE USER 'tms_prod'@'localhost' IDENTIFIED BY 'complex_password_here';
   GRANT SELECT, INSERT, UPDATE, DELETE ON training_management_system.* TO 'tms_prod'@'localhost';
   ```

2. **Regular Backups**
   ```bash
   mysqldump -u username -p training_management_system > backup_$(date +%Y%m%d).sql
   ```

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials
   - Verify database server is running
   - Confirm database exists

2. **File Upload Issues**
   - Check directory permissions (755/777)
   - Verify upload_max_filesize in php.ini
   - Check post_max_size setting

3. **Email Not Sending**
   - Verify SMTP settings
   - Check firewall/security settings
   - Test with simple mail() function first

4. **Session Issues**
   - Clear browser cache and cookies
   - Check session directory permissions
   - Verify session configuration

### Error Logs

Check application logs:
```
application/logs/log-YYYY-MM-DD.php
```

Enable CodeIgniter debugging:
```php
$config['log_threshold'] = 4; // All messages
```

## Maintenance

### Regular Tasks

1. **Database Backup**
   Schedule regular database backups

2. **Log Rotation**
   Clean up old log files regularly

3. **Certificate Cleanup**
   Remove old certificate files if needed

4. **Update Dependencies**
   Keep CodeIgniter and libraries updated

### Monitoring

Monitor these metrics:
- User login frequency
- Training completion rates
- Quiz performance
- Certificate generation
- System errors and performance

## API Documentation

The system includes basic API endpoints:

- `GET /api/users` - List users
- `GET /api/trainings` - List trainings
- `GET /api/progress/{user_id}` - User progress
- `POST /api/enroll` - Enroll user in training

Authentication required via session or API key.

## Support and Updates

### Getting Help

1. Check the troubleshooting section
2. Review system logs
3. Consult CodeIgniter 3 documentation
4. Contact system administrator

### Version Updates

To update the system:
1. Backup database and files
2. Replace application files (preserve config)
3. Run any new migrations
4. Test functionality
5. Clear caches

## License

This Training Management System is built using CodeIgniter 3 framework and follows the MIT License for the custom application code.

---

**Installation Date**: Generated automatically during setup  
**Version**: 1.0.0  
**Last Updated**: 2024
