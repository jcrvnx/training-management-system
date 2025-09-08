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
$conn = connectDB();

// Get all trainings
$result = $conn->query("SELECT * FROM trainings ORDER BY start_date DESC");

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
    <h1>Trainings</h1>
    <a href="<?php echo url('trainings/create.php'); ?>" class="btn btn-success">
        <i class="bi bi-plus-circle"></i> Add New Training
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
                        <th>Title</th>
                        <th>Duration</th>
                        <th>Location</th>
                        <th>Max Participants</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($training = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $training['title']; ?></td>
                                <td>
                                    <?php 
                                    echo date('M d, Y', strtotime($training['start_date'])) . ' - ' . 
                                         date('M d, Y', strtotime($training['end_date']));
                                    ?>
                                </td>
                                <td><?php echo $training['location']; ?></td>
                                <td><?php echo $training['max_participants']; ?></td>
                                <td class="text-end">
                                    <a href="<?php echo url('trainings/edit.php?id=' . $training['id']); ?>" class="btn btn-sm btn-primary">
                                        Edit
                                    </a>
                                    <a href="<?php echo url('trainings/delete.php?id=' . $training['id']); ?>" class="btn btn-sm btn-danger btn-delete">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No trainings found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../templates/footer.php'; ?> 