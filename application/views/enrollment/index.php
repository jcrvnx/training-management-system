<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Enrollment Management</h1>
    <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
        <a href="<?php echo base_url('enrollments/create'); ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Enrollment
        </a>
    <?php endif; ?>
</div>

<!-- Statistics Cards -->
<?php if (isset($enrollment_stats)): ?>
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Enrollments
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $enrollment_stats['total'] ?? 0; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Completed
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $enrollment_stats['completed'] ?? 0; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            In Progress
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $enrollment_stats['in_progress'] ?? 0; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Pending
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $enrollment_stats['pending'] ?? 0; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-hourglass-half fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Enrollments Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <?php echo ($user_role === 'user') ? 'My Enrollments' : 'All Enrollments'; ?>
        </h6>
    </div>
    <div class="card-body">
        <?php if (!empty($enrollments)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="enrollmentsTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Training</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Enrollment Date</th>
                            <th>Completion Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($enrollments as $enrollment): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($enrollment['user_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($enrollment['user_email'] ?? ''); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($enrollment['training_title']); ?></strong>
                                <br><small class="text-muted"><?php echo character_limiter($enrollment['training_description'] ?? '', 50); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $enrollment['status'] === 'completed' ? 'success' : 
                                        ($enrollment['status'] === 'in_progress' ? 'warning' : 
                                        ($enrollment['status'] === 'dropped' ? 'danger' : 'secondary')); 
                                ?>">
                                    <?php echo ucfirst($enrollment['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?php echo $enrollment['progress_percentage'] == 100 ? 'success' : 'info'; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $enrollment['progress_percentage']; ?>%" 
                                         aria-valuenow="<?php echo $enrollment['progress_percentage']; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?php echo $enrollment['progress_percentage']; ?>%
                                    </div>
                                </div>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($enrollment['enrollment_date'])); ?></td>
                            <td>
                                <?php if ($enrollment['completion_date']): ?>
                                    <?php echo date('M j, Y', strtotime($enrollment['completion_date'])); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="<?php echo base_url('enrollments/view/' . $enrollment['id']); ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
                                        <a href="<?php echo base_url('enrollments/edit/' . $enrollment['id']); ?>" 
                                           class="btn btn-sm btn-outline-secondary" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <?php if ($user_role === 'admin'): ?>
                                        <a href="<?php echo base_url('enrollments/delete/' . $enrollment['id']); ?>" 
                                           class="btn btn-sm btn-outline-danger delete-confirm" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-user-plus fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Enrollments Found</h4>
                <p class="text-muted">
                    <?php if ($user_role === 'user'): ?>
                        You haven't enrolled in any trainings yet.
                    <?php else: ?>
                        No enrollments have been created yet.
                    <?php endif; ?>
                </p>
                <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
                    <a href="<?php echo base_url('enrollments/create'); ?>" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create First Enrollment
                    </a>
                <?php else: ?>
                    <a href="<?php echo base_url('trainings'); ?>" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Browse Trainings
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable for better sorting and searching
    if ($.fn.DataTable) {
        $('#enrollmentsTable').DataTable({
            "pageLength": 25,
            "order": [[ 4, "desc" ]], // Sort by enrollment date descending
            "columnDefs": [
                { "orderable": false, "targets": [6] } // Disable sorting on Actions column
            ]
        });
    }
});
</script>
