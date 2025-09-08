<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
redirect(url('auth/login.php'));
?> 