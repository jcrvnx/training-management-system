<!-- Training Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1"><?php echo htmlspecialchars($training['title']); ?></h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo base_url('trainings'); ?>">Trainings</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($training['title']); ?></li>
            </ol>
        </nav>
    </div>
    <div class="btn-group" role="group">
        <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
            <a href="<?php echo base_url('trainings/edit/' . $training['id']); ?>" class="btn btn-outline-primary">
                <i class="fas fa-edit me-1"></i>Edit
            </a>
            <?php if ($user_role === 'admin'): ?>
            <a href="<?php echo base_url('trainings/delete/' . $training['id']); ?>" 
               class="btn btn-outline-danger delete-confirm">
                <i class="fas fa-trash me-1"></i>Delete
            </a>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($user_role === 'user' && !$is_enrolled): ?>
            <a href="<?php echo base_url('trainings/enroll/' . $training['id']); ?>" class="btn btn-success">
                <i class="fas fa-user-plus me-1"></i>Enroll Now
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- Main Training Info -->
    <div class="col-lg-8">
        <!-- Training Details Card -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Training Information</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Status:</strong>
                        <span class="badge bg-<?php echo $training['status'] === 'active' ? 'success' : ($training['status'] === 'upcoming' ? 'warning' : 'secondary'); ?> ms-2">
                            <?php echo ucfirst($training['status']); ?>
                        </span>
                    </div>
                    <div class="col-md-6">
                        <strong>Category:</strong>
                        <span class="text-muted"><?php echo ucfirst($training['category'] ?? 'General'); ?></span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Duration:</strong>
                        <span class="text-muted"><?php echo $training['duration_hours']; ?> hours</span>
                    </div>
                    <div class="col-md-6">
                        <strong>Max Participants:</strong>
                        <span class="text-muted"><?php echo $training['max_participants'] ?? 'Unlimited'; ?></span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Start Date:</strong>
                        <span class="text-muted"><?php echo date('F j, Y', strtotime($training['start_date'])); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>End Date:</strong>
                        <span class="text-muted"><?php echo date('F j, Y', strtotime($training['end_date'])); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($training['instructor_name'])): ?>
                <div class="mb-3">
                    <strong>Instructor:</strong>
                    <span class="text-muted"><?php echo htmlspecialchars($training['instructor_name']); ?></span>
                </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="mb-3">
                    <h6><strong>Description</strong></h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($training['description'])); ?></p>
                </div>
                
                <?php if (!empty($training['objectives'])): ?>
                <div class="mb-3">
                    <h6><strong>Learning Objectives</strong></h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($training['objectives'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($training['prerequisites'])): ?>
                <div class="mb-3">
                    <h6><strong>Prerequisites</strong></h6>
                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($training['prerequisites'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Course Materials -->
        <?php if (!empty($materials)): ?>
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Course Materials</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($materials as $material): ?>
                    <div class="col-md-6 mb-3">
                        <div class="d-flex align-items-center p-3 border rounded">
                            <i class="fas fa-download text-primary me-3 fa-2x"></i>
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($material['title']); ?></h6>
                                <small class="text-muted"><?php echo ucfirst($material['type']); ?></small>
                                <?php if ($is_enrolled || in_array($user_role, ['admin', 'trainer'])): ?>
                                <br><a href="<?php echo base_url('trainings/download_material/' . $material['id']); ?>" 
                                       class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-download me-1"></i>Download
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!$is_enrolled && $user_role === 'user'): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    You need to enroll in this training to access materials.
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Training Sessions -->
        <?php if (!empty($sessions)): ?>
        <div class="card shadow mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Training Sessions</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Date & Time</th>
                                <th>Location</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($session['title']); ?></td>
                                <td>
                                    <?php echo date('M j, Y g:i A', strtotime($session['session_date'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($session['location'] ?? 'Online'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $session['status'] === 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($session['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Sidebar -->
    <div class="col-lg-4">
        <!-- Enrollment Status -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Enrollment Information</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="row">
                        <div class="col-6">
                            <h4 class="text-primary"><?php echo $enrollment_count; ?></h4>
                            <small class="text-muted">Enrolled</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $completed_count; ?></h4>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
                
                <?php if ($training['max_participants']): ?>
                <div class="progress mb-3">
                    <?php $enrollment_percentage = ($enrollment_count / $training['max_participants']) * 100; ?>
                    <div class="progress-bar" role="progressbar" style="width: <?php echo $enrollment_percentage; ?>%" 
                         aria-valuenow="<?php echo $enrollment_percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                        <?php echo round($enrollment_percentage); ?>%
                    </div>
                </div>
                <small class="text-muted">
                    <?php echo $training['max_participants'] - $enrollment_count; ?> spots remaining
                </small>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- User's Progress (if enrolled) -->
        <?php if ($is_enrolled && isset($user_progress)): ?>
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Your Progress</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $user_progress['progress_percentage']; ?>%" 
                             aria-valuenow="<?php echo $user_progress['progress_percentage']; ?>" 
                             aria-valuemin="0" aria-valuemax="100">
                            <?php echo $user_progress['progress_percentage']; ?>%
                        </div>
                    </div>
                    <span class="badge bg-<?php echo $user_progress['status'] === 'completed' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($user_progress['status']); ?>
                    </span>
                </div>
                
                <div class="small text-muted">
                    <strong>Enrolled:</strong> <?php echo date('M j, Y', strtotime($user_progress['enrollment_date'])); ?><br>
                    <?php if ($user_progress['completion_date']): ?>
                    <strong>Completed:</strong> <?php echo date('M j, Y', strtotime($user_progress['completion_date'])); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Related Quizzes -->
        <?php if (!empty($quizzes)): ?>
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>Related Quizzes</h6>
            </div>
            <div class="card-body">
                <?php foreach ($quizzes as $quiz): ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong><?php echo htmlspecialchars($quiz['title']); ?></strong>
                        <br><small class="text-muted"><?php echo $quiz['question_count'] ?? 0; ?> questions</small>
                    </div>
                    <?php if ($is_enrolled || in_array($user_role, ['admin', 'trainer'])): ?>
                    <a href="<?php echo base_url('quizzes/view/' . $quiz['id']); ?>" class="btn btn-sm btn-outline-primary">
                        View Quiz
                    </a>
                    <?php endif; ?>
                </div>
                <hr class="my-2">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Activity -->
        <div class="card shadow mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Recent Activity</h6>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_enrollments)): ?>
                    <?php foreach (array_slice($recent_enrollments, 0, 5) as $enrollment): ?>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-user-plus text-success me-2"></i>
                        <small>
                            <strong><?php echo htmlspecialchars($enrollment['user_name']); ?></strong>
                            enrolled
                            <br><span class="text-muted"><?php echo time_ago($enrollment['enrollment_date']); ?></span>
                        </small>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">No recent activity.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
