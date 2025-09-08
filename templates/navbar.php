<?php
require_once __DIR__ . '/../includes/functions.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 rounded shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo url('dashboard.php'); ?>">
            <i class="bi bi-book me-2"></i>
            <?php echo SITE_NAME; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <?php if (isLoggedIn()): ?>
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="<?php echo url('dashboard.php'); ?>">
                        <i class="bi bi-speedometer2 me-1"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="<?php echo url('trainings/'); ?>">
                        <i class="bi bi-journals me-1"></i>
                        Trainings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="<?php echo url('users/'); ?>">
                        <i class="bi bi-people me-1"></i>
                        Users
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="<?php echo url('enrollments/'); ?>">
                        <i class="bi bi-person-check me-1"></i>
                        Enrollments
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i>
                        <?php echo $_SESSION['user']['name']; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="<?php echo url('users/edit.php?id=' . $_SESSION['user']['id']); ?>">
                                <i class="bi bi-gear me-1"></i> My Profile
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="<?php echo url('auth/logout.php'); ?>">
                                <i class="bi bi-box-arrow-right me-1"></i> Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</nav> 