<?php
// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP host
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // or 'ssl'
define('SMTP_USERNAME', 'your_email@gmail.com'); // Your email address
define('SMTP_PASSWORD', 'your_app_password'); // Your email password or app password
define('FROM_EMAIL', 'your_email@gmail.com'); // From email address
define('FROM_NAME', 'Training Management System'); // From name
define('REPLY_TO_EMAIL', 'noreply@yourcompany.com'); // Reply-to email

// Email templates directory
define('EMAIL_TEMPLATES_DIR', dirname(__DIR__) . '/email-templates/');
?>