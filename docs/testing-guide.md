# Training Management System - Testing Guide

## Overview
This guide provides comprehensive testing procedures to verify all system functionality works correctly for both Admin and User roles.

## Prerequisites
- XAMPP with PHP 8+ and MySQL running
- Database schema properly installed via `install_schema.php`
- At least one admin user created during installation

## Testing Checklist

### 1. Authentication & User Management ✅

#### Login System
- [ ] Navigate to login page
- [ ] Test valid login credentials
- [ ] Test invalid credentials (should show error)
- [ ] Test password reset functionality
- [ ] Verify session management (logout works)

#### User Creation (Admin Only)
- [ ] Navigate to Users → Create New User
- [ ] Test form validation:
  - [ ] Submit empty form (should show required field errors)
  - [ ] Enter invalid email (should show email format error)
  - [ ] Enter mismatched passwords (should show confirmation error)
  - [ ] Enter weak password (should show password strength indicator)
  - [ ] Enter duplicate email (should show unique constraint error)
- [ ] Create valid user with all fields
- [ ] Verify user appears in users list
- [ ] Verify email notification sent (if email configured)

#### User Roles & Permissions
- [ ] Test Admin role has access to all features
- [ ] Test Trainer role has appropriate restrictions
- [ ] Test User role has limited access
- [ ] Verify role-based navigation menu changes

### 2. Training Management ✅

#### Training Creation (Admin/Trainer)
- [ ] Navigate to Trainings → Create New Training
- [ ] Test form validation:
  - [ ] Submit empty form (should show required field errors)
  - [ ] Enter end date before start date (should show date validation error)
  - [ ] Enter enrollment deadline after start date (should show error)
  - [ ] Enter invalid participant numbers (should show numeric validation)
- [ ] Create valid training with all fields
- [ ] Verify training appears in trainings list
- [ ] Test different difficulty levels and categories

#### Training Viewing
- [ ] View trainings list as Admin (should see all trainings)
- [ ] View trainings list as User (should see enrolled trainings)
- [ ] Test training detail pages
- [ ] Verify filtering and search functionality

### 3. Enrollment System ✅

#### Enrollment Creation (Admin)
- [ ] Navigate to Enrollments → Create New Enrollment
- [ ] Test bulk user enrollment
- [ ] Verify enrollment confirmation
- [ ] Test enrollment notifications (if email configured)

#### User Enrollment Experience
- [ ] Login as regular user
- [ ] View available trainings
- [ ] Check enrollment status
- [ ] Verify enrollment restrictions (max participants, deadlines)

### 4. Quiz/Assessment System ✅

#### Quiz Creation (Admin/Trainer)
- [ ] Navigate to Quizzes → Create New Quiz
- [ ] Create quiz with multiple question types:
  - [ ] Multiple choice questions
  - [ ] True/False questions  
  - [ ] Short answer questions
  - [ ] Essay questions
- [ ] Set passing scores and attempt limits
- [ ] Verify quiz validation rules

#### Quiz Taking (Users)
- [ ] Navigate to available quizzes
- [ ] Start quiz attempt
- [ ] Test answer saving functionality
- [ ] Test timer functionality (if enabled)
- [ ] Submit quiz and verify scoring
- [ ] Test retake functionality
- [ ] Verify quiz completion notifications

### 5. Certificate System ✅

#### Certificate Generation
- [ ] Complete a quiz with passing score
- [ ] Verify automatic certificate generation
- [ ] Test certificate PDF generation
- [ ] Verify certificate email notifications (if email configured)

#### Certificate Viewing
- [ ] Navigate to My Certificates page
- [ ] View certificate details
- [ ] Test certificate sharing functionality
- [ ] Download certificate PDFs

### 6. Dashboard Analytics ✅

#### Admin Dashboard
- [ ] Verify training statistics cards
- [ ] Test dashboard detail pages:
  - [ ] Total Trainings detail page
  - [ ] Total Users detail page  
  - [ ] Active Quizzes detail page
  - [ ] Certificates detail page
- [ ] Check recent activities feed
- [ ] Verify charts and analytics display

#### User Dashboard
- [ ] Verify personalized statistics
- [ ] Check enrolled trainings section
- [ ] Verify certificates section
- [ ] Test quick actions and navigation

### 7. File Upload System ✅

#### Training Materials
- [ ] Upload training materials/documents
- [ ] Test file type restrictions
- [ ] Test file size limits
- [ ] Verify file download functionality

#### Profile Images
- [ ] Upload user profile images
- [ ] Test image type restrictions
- [ ] Verify image display in profiles

### 8. Email Notification System ✅

#### Email Configuration (Admin)
- [ ] Navigate to Email Settings (in admin dropdown)
- [ ] Configure SMTP settings in `/includes/email-config.php`
- [ ] Test email configuration with test email feature
- [ ] Verify email statistics dashboard

#### Automated Notifications
- [ ] Test quiz completion notifications
- [ ] Test certificate earned notifications  
- [ ] Test training enrollment notifications
- [ ] Test training reminder notifications
- [ ] Verify email attachments (certificates)

### 9. Form Validation System ✅

#### Client-Side Validation
- [ ] Test real-time field validation
- [ ] Verify password strength indicators
- [ ] Test date validation and dependencies
- [ ] Check form submission prevention on invalid data

#### Server-Side Validation  
- [ ] Test validation with JavaScript disabled
- [ ] Verify data sanitization
- [ ] Test unique constraint validation
- [ ] Check comprehensive error messages

### 10. Security & Data Integrity

#### Authentication Security
- [ ] Test session timeout
- [ ] Verify password hashing
- [ ] Test SQL injection prevention
- [ ] Check XSS protection

#### Data Validation
- [ ] Test input sanitization
- [ ] Verify data type constraints
- [ ] Test file upload security
- [ ] Check privilege escalation prevention

## Performance Testing

### Database Performance
- [ ] Test with multiple users (>50)
- [ ] Test with large datasets (>1000 records)
- [ ] Verify query optimization
- [ ] Check database connection handling

### Front-End Performance
- [ ] Test page load times
- [ ] Verify responsive design on different devices
- [ ] Test JavaScript functionality across browsers
- [ ] Check form submission performance

## Browser Compatibility Testing

Test the system on:
- [ ] Google Chrome (latest)
- [ ] Mozilla Firefox (latest)
- [ ] Microsoft Edge (latest)
- [ ] Safari (if available)
- [ ] Mobile browsers (responsive design)

## Error Handling Testing

#### Expected Errors
- [ ] Test 404 page handling
- [ ] Test database connection failures
- [ ] Test file upload failures
- [ ] Test email sending failures

#### Error Logging
- [ ] Verify error logging functionality
- [ ] Check log file creation and rotation
- [ ] Test error notification system

## Load Testing (Optional)

#### Concurrent Users
- [ ] Test 10 simultaneous users
- [ ] Test 50 simultaneous users  
- [ ] Monitor server resource usage
- [ ] Check session handling under load

#### Data Volume
- [ ] Test with 1000+ training records
- [ ] Test with 5000+ user accounts
- [ ] Test with large file uploads
- [ ] Verify search performance with large datasets

## Final Verification

### System Health Check
- [ ] All core features functional
- [ ] No critical PHP errors in logs
- [ ] Database integrity maintained
- [ ] File permissions correct
- [ ] Email system operational (if configured)

### User Experience
- [ ] Intuitive navigation
- [ ] Clear error messages
- [ ] Responsive design works
- [ ] Forms are user-friendly
- [ ] Loading times acceptable

### Documentation
- [ ] Installation guide accurate
- [ ] User manuals complete
- [ ] API documentation up-to-date
- [ ] Deployment guide tested

## Bug Reporting

When issues are found:

1. **Document the bug:**
   - Steps to reproduce
   - Expected vs actual behavior
   - Browser and environment details
   - Screenshots if applicable

2. **Categorize by severity:**
   - Critical: System unusable/data loss
   - High: Major feature broken
   - Medium: Minor feature issue
   - Low: Cosmetic/enhancement

3. **Common troubleshooting:**
   - Clear browser cache
   - Check PHP error logs
   - Verify database connection
   - Confirm file permissions

## Test Data Setup

### Sample Users
Create test users for each role:
- admin@test.com (Admin)
- trainer@test.com (Trainer) 
- user@test.com (User)

### Sample Trainings
Create diverse training scenarios:
- Beginner level training with quiz
- Advanced training with multiple assessments
- Mandatory training with deadlines
- Optional training with file uploads

### Sample Content
- Various question types in quizzes
- Different certificate templates
- Mixed file types for uploads
- Various training categories

This comprehensive testing guide ensures the Training Management System is production-ready and meets all functional requirements.