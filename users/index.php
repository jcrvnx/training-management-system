<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(url('auth/login.php'));
}

// Get database connection
$conn = connectDB(false);

// Check database connection
if (!$conn || !($conn instanceof mysqli)) {
    redirect(url('install.php'));
}

// Get all users
$result = $conn->query("SELECT id, full_name, email, role, created_at FROM users ORDER BY id ASC");

// Check if any message in session
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

closeDB($conn);

// Include header
include_once '../templates/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Users</h1>
    <a href="<?php echo url('users/create.php'); ?>" class="btn btn-success">
        <i class="bi bi-plus-circle"></i> Add New User
    </a>
</div>

<?php if (!empty($message)): ?>
    <?php echo showAlert($message, 'success'); ?>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Registered On</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($user = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo $user['full_name']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td class="text-end">
                                    <a href="<?php echo url('users/edit.php?id=' . $user['id']); ?>" class="btn btn-sm btn-primary">
                                        Edit
                                    </a>
                                    <?php if ($user['id'] != $_SESSION['user']['id']): ?>
                                    <a href="<?php echo url('users/delete.php?id=' . $user['id']); ?>" class="btn btn-sm btn-danger btn-delete">
                                        Delete
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?> 