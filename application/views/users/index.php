<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">User Management</h1>
    <a href="<?php echo base_url('users/create'); ?>" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>Add User
    </a>
</div>

<!-- User Statistics -->
<?php if (isset($user_stats)): ?>
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Users
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $user_stats['total'] ?? 0; ?>
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
                            Admins
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $user_stats['admins'] ?? 0; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-shield fa-2x text-gray-300"></i>
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
                            Trainers
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $user_stats['trainers'] ?? 0; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
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
                            Students
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo $user_stats['users'] ?? 0; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Search and Filters -->
<div class="card shadow mb-4">
    <div class="card-body">
        <?php echo form_open('users', ['method' => 'GET', 'class' => 'row g-3']); ?>
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" placeholder="Search users..." 
                       value="<?php echo $this->input->get('search'); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $this->input->get('role') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="trainer" <?php echo $this->input->get('role') === 'trainer' ? 'selected' : ''; ?>>Trainer</option>
                    <option value="user" <?php echo $this->input->get('role') === 'user' ? 'selected' : ''; ?>>Student</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $this->input->get('status') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $this->input->get('status') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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

<!-- Users Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Users</h6>
    </div>
    <div class="card-body">
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-primary rounded-circle text-white d-flex align-items-center justify-content-center me-3">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                        <?php if ($user['phone']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($user['phone']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $user['role'] === 'admin' ? 'danger' : 
                                        ($user['role'] === 'trainer' ? 'warning' : 'info'); 
                                ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['department'] ?? '-'); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <?php echo time_ago($user['last_login']); ?>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="<?php echo base_url('users/view/' . $user['id']); ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View Profile">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <a href="<?php echo base_url('users/edit/' . $user['id']); ?>" 
                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    
                                    <?php if ($user['id'] != $this->session->userdata('user_id')): ?>
                                    <a href="<?php echo base_url('users/delete/' . $user['id']); ?>" 
                                       class="btn btn-sm btn-outline-danger delete-confirm" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
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
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No Users Found</h4>
                <p class="text-muted">No users match your search criteria.</p>
                <a href="<?php echo base_url('users/create'); ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add First User
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    if ($.fn.DataTable) {
        $('#usersTable').DataTable({
            "pageLength": 25,
            "order": [[ 5, "desc" ]], // Sort by joined date descending
            "columnDefs": [
                { "orderable": false, "targets": [7] } // Disable sorting on Actions column
            ]
        });
    }
});
</script>
