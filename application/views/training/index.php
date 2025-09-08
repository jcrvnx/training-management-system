<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Training Management</h1>
    <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
        <a href="<?php echo base_url('trainings/create'); ?>" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Create Training
        </a>
    <?php endif; ?>
</div>

<!-- Search and Filters -->
<div class="card shadow mb-4">
    <div class="card-body">
        <?php echo form_open('trainings', ['method' => 'GET', 'class' => 'row g-3']); ?>
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search trainings..." 
                       value="<?php echo $this->input->get('search'); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $this->input->get('status') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $this->input->get('status') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="upcoming" <?php echo $this->input->get('status') === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <option value="technical" <?php echo $this->input->get('category') === 'technical' ? 'selected' : ''; ?>>Technical</option>
                    <option value="management" <?php echo $this->input->get('category') === 'management' ? 'selected' : ''; ?>>Management</option>
                    <option value="compliance" <?php echo $this->input->get('category') === 'compliance' ? 'selected' : ''; ?>>Compliance</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">
                    <i class="fas fa-search"></i> Search
                </button>
            </div>
        <?php echo form_close(); ?>
    </div>
</div>

<!-- Training Cards -->
<div class="row">
    <?php if (!empty($trainings)): ?>
        <?php foreach ($trainings as $training): ?>
        <div class="col-lg-4 col-md-6 mb-4">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($training['title']); ?></h5>
                        <span class="badge bg-<?php echo $training['status'] === 'active' ? 'success' : ($training['status'] === 'upcoming' ? 'warning' : 'secondary'); ?>">
                            <?php echo ucfirst($training['status']); ?>
                        </span>
                    </div>
                    
                    <p class="card-text text-muted"><?php echo character_limiter($training['description'], 100); ?></p>
                    
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <small class="text-muted">Duration</small>
                            <div class="fw-bold"><?php echo $training['duration_hours']; ?>h</div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Enrolled</small>
                            <div class="fw-bold"><?php echo $training['enrolled_count'] ?? 0; ?></div>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Max</small>
                            <div class="fw-bold"><?php echo $training['max_participants'] ?? 'N/A'; ?></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('M j, Y', strtotime($training['start_date'])); ?> - 
                            <?php echo date('M j, Y', strtotime($training['end_date'])); ?>
                        </small>
                    </div>
                    
                    <?php if (isset($training['instructor_name'])): ?>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-user-tie me-1"></i>
                            Instructor: <?php echo htmlspecialchars($training['instructor_name']); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer bg-transparent">
                    <div class="btn-group w-100" role="group">
                        <a href="<?php echo base_url('trainings/view/' . $training['id']); ?>" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-1"></i>View
                        </a>
                        
                        <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
                            <a href="<?php echo base_url('trainings/edit/' . $training['id']); ?>" 
                               class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-edit me-1"></i>Edit
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($user_role === 'user' && !isset($training['is_enrolled'])): ?>
                            <a href="<?php echo base_url('trainings/enroll/' . $training['id']); ?>" 
                               class="btn btn-success btn-sm">
                                <i class="fas fa-user-plus me-1"></i>Enroll
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
                    <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Trainings Found</h4>
                    <p class="text-muted">There are no trainings available at the moment.</p>
                    <?php if (in_array($user_role, ['admin', 'trainer'])): ?>
                        <a href="<?php echo base_url('trainings/create'); ?>" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create First Training
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if (isset($pagination)): ?>
<div class="row mt-4">
    <div class="col-12">
        <nav aria-label="Training pagination">
            <?php echo $pagination; ?>
        </nav>
    </div>
</div>
<?php endif; ?>
