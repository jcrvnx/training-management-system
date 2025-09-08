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

// Check if required tables exist
$tablesExist = true;
$requiredTables = ['users', 'trainings', 'enrollments'];

foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows === 0) {
        $tablesExist = false;
        break;
    }
}

if (!$tablesExist) {
    closeDB($conn);
    redirect(url('install.php'));
}

// Get all enrollments with user and training details using JOIN
$query = "
    SELECT e.id, u.id as user_id, u.full_name, t.id as training_id, t.title, 
           t.start_date, t.end_date, e.enrolled_at
    FROM enrollments e
    JOIN users u ON e.user_id = u.id
    JOIN trainings t ON e.training_id = t.id
    ORDER BY e.enrolled_at DESC
";
$result = $conn->query($query);

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
    <h1>Enrollments</h1>
    <a href="<?php echo url('enrollments/create.php'); ?>" class="btn btn-success">
        <i class="bi bi-plus-circle"></i> Add New Enrollment
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
                        <th>User</th>
                        <th>Training</th>
                        <th>Training Period</th>
                        <th>Enrolled On</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($enrollment = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $enrollment['id']; ?></td>
                                <td>
                                    <a href="<?php echo url('users/edit.php?id=' . $enrollment['user_id']); ?>">
                                        <?php echo $enrollment['full_name']; ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="<?php echo url('trainings/edit.php?id=' . $enrollment['training_id']); ?>">
                                        <?php echo $enrollment['title']; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    echo date('M d, Y', strtotime($enrollment['start_date'])) . ' - ' . 
                                         date('M d, Y', strtotime($enrollment['end_date']));
                                    ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($enrollment['enrolled_at'])); ?></td>
                                <td class="text-end">
                                    <a href="<?php echo url('enrollments/delete.php?id=' . $enrollment['id']); ?>" class="btn btn-sm btn-danger btn-delete">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center">No enrollments found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?> 