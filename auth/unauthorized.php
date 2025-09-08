<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - Training Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body text-center p-5">
                        <div class="mb-4">
                            <i class="bi bi-shield-exclamation text-danger" style="font-size: 4rem;"></i>
                        </div>
                        
                        <h2 class="mb-3">Access Denied</h2>
                        <p class="text-muted mb-4">
                            You don't have sufficient permissions to access this resource. 
                            Please contact your administrator if you believe this is an error.
                        </p>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="javascript:history.back()" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Go Back
                            </a>
                            <a href="../dashboard.php" class="btn btn-primary">
                                <i class="bi bi-house"></i> Dashboard
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </div>
                        
                        <?php if (isset($_SESSION['user'])): ?>
                            <div class="mt-4 pt-3 border-top">
                                <small class="text-muted">
                                    Logged in as: <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong> 
                                    (<?php echo htmlspecialchars($_SESSION['user']['role']); ?>)
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
