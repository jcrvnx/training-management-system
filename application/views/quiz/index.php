<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Quiz Management</h1>
    <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
        <a href="<?php echo base_url('quizzes/create'); ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create Quiz
        </a>
    <?php endif; ?>
</div>

<!-- Quizzes Grid -->
<div class="row">
    <?php if (!empty($quizzes)): ?>
        <?php foreach ($quizzes as $quiz): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                        <span class="badge bg-<?php echo $quiz['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    
                    <p class="card-text text-muted"><?php echo character_limiter($quiz['description'], 100); ?></p>
                    
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <small class="text-muted">Questions</small>
                            <div class="fw-bold"><?php echo $quiz['question_count'] ?? 0; ?></div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Pass %</small>
                            <div class="fw-bold"><?php echo $quiz['pass_percentage']; ?>%</div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Time Limit</small>
                            <div class="fw-bold"><?php echo $quiz['time_limit'] ?? 'No'; ?> min</div>
                        </div>
                    </div>
                    
                    <?php if (isset($quiz['training_title'])): ?>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-graduation-cap me-1"></i>
                            Training: <?php echo htmlspecialchars($quiz['training_title']); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Created: <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?>
                        </small>
                    </div>
                </div>
                
                <div class="card-footer bg-transparent">
                    <div class="btn-group w-100" role="group">
                        <a href="<?php echo base_url('quizzes/view/' . $quiz['id']); ?>" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                        
                        <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
                            <a href="<?php echo base_url('quizzes/manage/' . $quiz['id']); ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-cog me-1"></i>Manage
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($user_role === 'user'): ?>
                            <a href="<?php echo base_url('quizzes/attempt/' . $quiz['id']); ?>" 
                               class="btn btn-success btn-sm">
                                <i class="fas fa-play me-1"></i>Take Quiz
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
                    <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Quizzes Found</h4>
                    <p class="text-muted">There are no quizzes available at the moment.</p>
                    <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
                        <a href="<?php echo base_url('quizzes/create'); ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create First Quiz
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
