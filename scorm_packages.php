<?php
/**
 * SCORM Packages Management
 * Student Database System
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$page_title = 'SCORM Packages';

// Get all packages
try {
    $stmt = $pdo->query("SELECT * FROM scorm_packages ORDER BY created_at DESC");
    $packages = $stmt->fetchAll();
} catch (PDOException $e) {
    $packages = [];
    $error = "Error loading SCORM packages: " . $e->getMessage();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';

// Display flash message if any
$flash = get_flash();
?>

<?php if ($flash): ?>
<script>
    showToast('<?php echo addslashes($flash['message']); ?>', '<?php echo $flash['type']; ?>');
</script>
<?php endif; ?>

<div class="page-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <h2>📦 SCORM Packages</h2>
    <?php if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student'): ?>
    <a href="scorm_upload.php" class="btn btn-primary">
        <i class="fas fa-upload"></i> Upload Package
    </a>
    <?php endif; ?>
</div>

<div class="card">
    <?php if (empty($packages)): ?>
        <div style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-box-open" style="font-size: 64px; color: var(--text-muted); margin-bottom: 20px;"></i>
            <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 20px;">No SCORM packages uploaded yet.</p>
            <?php if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student'): ?>
            <a href="scorm_upload.php" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload Your First Package
            </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($packages as $package): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($package['title']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($package['description'] ?? '', 0, 60)) . (strlen($package['description'] ?? '') > 60 ? '...' : ''); ?></td>
                            <td><?php echo htmlspecialchars($package['version'] ?? '1.0'); ?></td>
                            <td>
                                <span class="badge <?php echo ($package['is_published'] ?? 0) ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo ($package['is_published'] ?? 0) ? 'Published' : 'Draft'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($package['created_at'])); ?></td>
                            <td>
                                <a href="scorm_player.php?id=<?php echo $package['id']; ?>" class="btn-action-gray" title="Play/Test">
                                    <i class="fas fa-play"></i>
                                </a>
                                <a href="<?php echo $package['folder_path']; ?>" target="_blank" class="btn-action-gray" title="Preview Files">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student'): ?>
                                <a href="scorm_edit.php?id=<?php echo $package['id']; ?>" class="btn-action-gray" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="scorm_delete.php?id=<?php echo $package['id']; ?>" 
                                   class="btn-action-gray delete" 
                                   onclick="return confirm('Are you sure you want to delete this package?')" 
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
