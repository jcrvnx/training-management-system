# Email Notifications Setup Guide

## Overview

The Training Management System includes a comprehensive email notification system that sends notifications for:
- Quiz completions (pass/fail)
- Certificate issuance (with PDF attachments)
- Training enrollments
- Training reminders

## Email Configuration

### 1. Configure SMTP Settings

Edit the file `/includes/email-config.php` with your email provider's SMTP settings:

```php
define('SMTP_HOST', 'smtp.gmail.com'); // Your SMTP host
define('SMTP_PORT', 587);              // SMTP port (587 for TLS, 465 for SSL)
define('SMTP_SECURE', 'tls');          // 'tls' or 'ssl'
define('SMTP_USERNAME', 'your_email@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'your_app_password');    // Your password or app password
define('FROM_EMAIL', 'your_email@gmail.com');    // From email address
define('FROM_NAME', 'Training Management System'); // From name
define('REPLY_TO_EMAIL', 'noreply@yourcompany.com'); // Reply-to email
```

### 2. Gmail Setup (Recommended)

For Gmail accounts:
1. Enable 2-factor authentication on your Google account
2. Generate an App Password:
   - Go to Google Account settings
   - Security → 2-Step Verification → App passwords
   - Generate a new app password for "Mail"
   - Use this app password in the `SMTP_PASSWORD` setting

3. Use these settings:
   ```php
   define('SMTP_HOST', 'smtp.gmail.com');
   define('SMTP_PORT', 587);
   define('SMTP_SECURE', 'tls');
   ```

### 3. Other Email Providers

#### Outlook/Hotmail:
```php
define('SMTP_HOST', 'smtp-mail.outlook.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
```

#### Yahoo Mail:
```php
define('SMTP_HOST', 'smtp.mail.yahoo.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
```

## Testing Email Configuration

1. Log in as an administrator
2. Go to the Email Settings page (available in the user dropdown menu)
3. Enter a test email address
4. Click "Send Test Email"
5. Check if the test email is received

## Email Templates

The system uses beautiful HTML email templates for:

### Quiz Completion Notifications
- Personalized congratulations for passed quizzes
- Encouragement messages for failed attempts
- Quiz results summary with score and status
- Direct link back to the dashboard

### Certificate Earned Notifications
- Celebration message with achievement badges
- Certificate details (ID, issue date, expiration)
- PDF certificate attachment (when available)
- Links to view certificates and dashboard

### Training Enrollment Notifications
- Welcome message for new training enrollments
- Training details and description
- Call-to-action to start the training

### Training Reminders
- Friendly reminders for incomplete trainings
- Progress encouragement
- Direct links to continue training

## Notification Features

### Automatic Triggers
- **Quiz Submission**: Sent immediately when a quiz is submitted
- **Certificate Generation**: Sent when a user passes a quiz and earns a certificate
- **Training Enrollment**: Sent when a user is enrolled in a training
- **Manual Reminders**: Can be triggered by administrators

### Email Logging
The system logs all email attempts including:
- Recipient email
- Subject line
- Delivery status (sent/failed)
- Error messages (if any)
- Timestamp

### Statistics Dashboard
Administrators can view email statistics:
- Total emails sent
- Success/failure rates
- Recent email activity

## Troubleshooting

### Common Issues

1. **"Authentication failed" error**
   - Verify your username and password
   - For Gmail, make sure you're using an App Password, not your regular password
   - Check that 2FA is enabled for Gmail accounts

2. **"Connection failed" error**
   - Verify SMTP host and port settings
   - Check if your hosting provider blocks outgoing SMTP connections
   - Try different ports (587 for TLS, 465 for SSL, 25 for unencrypted)

3. **Emails not being received**
   - Check spam/junk folders
   - Verify the "From" email address is valid
   - Test with different email providers

4. **Certificate attachments not working**
   - Ensure certificate files exist in the specified path
   - Check file permissions
   - Verify the certificate manager is generating PDFs correctly

### Debug Mode

To enable debug mode for email troubleshooting:

1. Edit `/includes/notification-manager.php`
2. Add error logging to catch detailed errors:
   ```php
   // Add this to catch more detailed errors
   ini_set('error_reporting', E_ALL);
   ini_set('display_errors', 1);
   ```

### Server Requirements

- PHP 5.6 or higher
- `mail()` function enabled (fallback)
- SMTP extension (recommended)
- OpenSSL extension for TLS/SSL connections

## Security Considerations

1. **Never commit credentials to version control**
   - Keep email credentials in environment variables or separate config files
   - Add `email-config.php` to `.gitignore`

2. **Use App Passwords**
   - Never use your main email password
   - Generate specific app passwords for the application

3. **Limit sending rates**
   - Be mindful of email provider sending limits
   - Consider implementing rate limiting for bulk notifications

## Support

For additional support:
1. Check the Email Statistics page for delivery status
2. Review PHP error logs
3. Test with the built-in email test feature
4. Verify SMTP settings with your email provider's documentation