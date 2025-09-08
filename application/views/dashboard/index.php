<!-- Dashboard Statistics -->
<div class="row">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <?php echo ($user_role === 'admin') ? 'Total Users' : 'My Enrollments'; ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo isset($stats['users']) ? $stats['users'] : (isset($stats['enrollments']) ? $stats['enrollments'] : 0); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-users fa-2x text-gray-300"></i>
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
                            <?php echo ($user_role === 'admin') ? 'Total Trainings' : 'Completed Trainings'; ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo isset($stats['trainings']) ? $stats['trainings'] : (isset($stats['completed_trainings']) ? $stats['completed_trainings'] : 0); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>
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
                            <?php echo ($user_role === 'admin') ? 'Total Enrollments' : 'Certificates Earned'; ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo isset($stats['total_enrollments']) ? $stats['total_enrollments'] : (isset($stats['certificates']) ? $stats['certificates'] : 0); ?>
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
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            <?php echo ($user_role === 'admin') ? 'Active Quizzes' : 'Pending Tasks'; ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo isset($stats['quizzes']) ? $stats['quizzes'] : (isset($stats['pending_tasks']) ? $stats['pending_tasks'] : 0); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-question-circle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Activities -->
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_activities)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                <small><?php echo time_ago($activity['created_at']); ?></small>
                            </div>
                            <p class="mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <?php if (isset($activity['user_name'])): ?>
                                <small class="text-muted">by <?php echo htmlspecialchars($activity['user_name']); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No recent activities.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo base_url('trainings/create'); ?>" class="btn btn-primary btn-block">
                            <i class="fas fa-plus-circle me-2"></i>
                            Create Training
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo base_url('quizzes/create'); ?>" class="btn btn-success btn-block">
                            <i class="fas fa-question-circle me-2"></i>
                            Create Quiz
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user_role === 'admin'): ?>
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo base_url('users/create'); ?>" class="btn btn-info btn-block">
                            <i class="fas fa-user-plus me-2"></i>
                            Add User
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo base_url('enrollments/create'); ?>" class="btn btn-warning btn-block">
                            <i class="fas fa-clipboard-list me-2"></i>
                            Enroll User
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo base_url('trainings'); ?>" class="btn btn-info btn-block">
                            <i class="fas fa-search me-2"></i>
                            Browse Trainings
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="<?php echo base_url('certificates'); ?>" class="btn btn-warning btn-block">
                            <i class="fas fa-certificate me-2"></i>
                            My Certificates
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Upcoming Deadlines -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Upcoming Deadlines</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($upcoming_deadlines)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($upcoming_deadlines as $deadline): ?>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1"><?php echo htmlspecialchars($deadline['title']); ?></h6>
                                <small class="text-danger"><?php echo date('M d, Y', strtotime($deadline['end_date'])); ?></small>
                            </div>
                            <p class="mb-1 text-muted">
                                <?php echo days_until($deadline['end_date']); ?> days remaining
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No upcoming deadlines.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Progress Overview (for regular users) -->
<?php if ($user_role === 'user' && !empty($user_progress)): ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Your Learning Progress</h6>
            </div>
            <div class="card-body">
                <?php foreach ($user_progress as $progress): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="font-weight-bold"><?php echo htmlspecialchars($progress['training_title']); ?></span>
                        <span class="text-muted"><?php echo $progress['progress_percentage']; ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $progress['progress_percentage']; ?>%" 
                             aria-valuenow="<?php echo $progress['progress_percentage']; ?>" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                    <small class="text-muted">
                        Status: <span class="badge badge-<?php echo $progress['status'] === 'completed' ? 'success' : ($progress['status'] === 'in_progress' ? 'warning' : 'secondary'); ?>">
                            <?php echo ucfirst($progress['status']); ?>
                        </span>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
