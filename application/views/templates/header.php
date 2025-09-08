<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Training Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo base_url('assets/css/style.css'); ?>" rel="stylesheet">
    
    <style>
        .navbar-brand {
            font-weight: bold;
        }
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .border-left-primary {
            border-left: 0.25rem solid #4e73df !important;
        }
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        .border-left-info {
            border-left: 0.25rem solid #36b9cc !important;
        }
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        .text-xs {
            font-size: 0.75rem;
        }
        .shadow {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c5282 10%, #2a4c7c 100%);
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .main-content {
            background-color: #f8f9fc;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php if (is_logged_in()): ?>
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">TMS</h5>
                        <small class="text-white-50">Training Management</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo uri_string() == 'dashboard' ? 'active' : ''; ?>" href="<?php echo base_url('dashboard'); ?>">
                                <i class="fas fa-tachometer-alt me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos(uri_string(), 'training') === 0 ? 'active' : ''; ?>" href="<?php echo base_url('trainings'); ?>">
                                <i class="fas fa-graduation-cap me-2"></i>
                                Trainings
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos(uri_string(), 'enrollment') === 0 ? 'active' : ''; ?>" href="<?php echo base_url('enrollments'); ?>">
                                <i class="fas fa-user-plus me-2"></i>
                                Enrollments
                            </a>
                        </li>
                        
                        <?php if (in_array(get_current_user_role(), ['admin', 'trainer'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos(uri_string(), 'quiz') === 0 ? 'active' : ''; ?>" href="<?php echo base_url('quizzes'); ?>">
                                <i class="fas fa-question-circle me-2"></i>
                                Quizzes
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos(uri_string(), 'certificate') === 0 ? 'active' : ''; ?>" href="<?php echo base_url('certificates'); ?>">
                                <i class="fas fa-certificate me-2"></i>
                                Certificates
                            </a>
                        </li>
                        
                        <?php if (is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos(uri_string(), 'users') === 0 ? 'active' : ''; ?>" href="<?php echo base_url('users'); ?>">
                                <i class="fas fa-users me-2"></i>
                                Users
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <hr class="sidebar-divider">
                        
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_url('users/profile'); ?>">
                                <i class="fas fa-user-cog me-2"></i>
                                Profile
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo base_url('auth/logout'); ?>">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <?php endif; ?>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <?php if (is_logged_in()): ?>
                <!-- Top Navigation -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo isset($page_title) ? $page_title : 'Dashboard'; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="text-muted">Welcome, <?php echo get_current_user()['full_name']; ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Flash Messages -->
                <?php echo flash_message(); ?>
                
                <!-- Page Content -->
                <div class="content-wrapper">
