<?php
// Clean user input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Generate URL for internal links
function url($path = '') {
    return BASE_URL . '/' . $path;
}

// Redirect to another page
function redirect($location) {
    header("Location: $location");
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user']);
}

// Display alert message
function showAlert($message, $type = 'primary') {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
              ' . $message . '
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}
?> 