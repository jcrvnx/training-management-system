<?php
/**
 * Main entry point for Training Management System
 * Redirects to appropriate page based on installation status
 */

// Check if installation is complete via our main installer
if (!file_exists('install_complete.flag')) {
    // Installation needed - redirect to our unified installer
    header('Location: install_schema.php');
    exit;
}

// Installation complete, redirect to dashboard
header('Location: dashboard.php');
exit; 