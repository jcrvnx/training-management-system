<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Certificate Management</h1>
    <?php if ($user_role === 'admin'): ?>
        <div class="btn-group" role="group">
            <a href="<?php echo base_url('certificates/templates'); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-file-alt me-2"></i>Templates
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (isset($message)): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i><?php echo $message; ?>
</div>
<?php endif; ?>

<!-- Certificates Grid -->
<div class="row">
    <?php if (!empty($certificates)): ?>
        <?php foreach ($certificates as $certificate): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-certificate fa-3x text-warning mb-2"></i>
                        <h5 class="card-title"><?php echo htmlspecialchars($certificate['training_title']); ?></h5>
                    </div>
                    
                    <div class="mb-3">
                        <strong>Certificate #:</strong>
                        <span class="text-muted"><?php echo htmlspecialchars($certificate['certificate_number']); ?></span>
                    </div>
                    
                    <?php if ($user_role !== 'user'): ?>
                    <div class="mb-3">
                        <strong>Student:</strong>
                        <span class="text-muted"><?php echo htmlspecialchars($certificate['user_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <strong>Issued Date:</strong>
                        <span class="text-muted"><?php echo date('M j, Y', strtotime($certificate['issued_date'])); ?></span>
                    </div>
                    
                    <div class="mb-3">
                        <span class="badge bg-success">
                            <i class="fas fa-check-circle me-1"></i>Verified
                        </span>
                    </div>
                </div>
                
                <div class="card-footer bg-transparent">
                    <div class="btn-group w-100" role="group">
                        <a href="<?php echo base_url('certificates/view/' . $certificate['id']); ?>" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                        
                        <a href="<?php echo base_url('certificates/download/' . $certificate['id']); ?>" 
                           class="btn btn-success btn-sm">
                            <i class="fas fa-download me-1"></i>Download
                        </a>
                        
                        <?php if ($user_role === 'admin'): ?>
                        <a href="<?php echo base_url('certificates/print/' . $certificate['id']); ?>" 
                           class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-print me-1"></i>Print
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body text-center py-5">
                    <i class="fas fa-certificate fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Certificates Found</h4>
                    <p class="text-muted">
                        <?php if ($user_role === 'user'): ?>
                            You haven't earned any certificates yet. Complete trainings to earn certificates.
                        <?php else: ?>
                            No certificates have been issued yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($user_role === 'user'): ?>
                        <a href="<?php echo base_url('trainings'); ?>" class="btn btn-primary">
                            <i class="fas fa-graduation-cap me-2"></i>Browse Trainings
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Stats for Admins -->
<?php if ($user_role === 'admin' && !empty($certificates)): ?>
<div class="row mt-5">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Certificate Statistics</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h3 class="text-primary"><?php echo count($certificates); ?></h3>
                        <p class="text-muted">Total Certificates</p>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-success">
                            <?php echo count(array_filter($certificates, function($cert) { 
                                return date('Y-m', strtotime($cert['issued_date'])) === date('Y-m'); 
                            })); ?>
                        </h3>
                        <p class="text-muted">This Month</p>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-info">
                            <?php echo count(array_unique(array_column($certificates, 'training_title'))); ?>
                        </h3>
                        <p class="text-muted">Training Programs</p>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-warning">
                            <?php echo count(array_unique(array_column($certificates, 'user_name'))); ?>
                        </h3>
                        <p class="text-muted">Certified Users</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
