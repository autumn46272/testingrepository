<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('courses.php');
}

$course_id = clean_input($_GET['id']);
$error = '';
$success = '';

// Handle Add SCORM to Course
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_scorm'])) {
    $scorm_id = clean_input($_POST['scorm_id']);

    if (empty($scorm_id)) {
        $error = "Please select a SCORM package.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO course_scorm (course_id, scorm_id) VALUES (:cid, :sid)");
            $stmt->execute(['cid' => $course_id, 'sid' => $scorm_id]);
            $success = "SCORM package added to course!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "This SCORM package is already added to this course.";
            } else {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Handle Remove SCORM from Course
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_scorm'])) {
    $scorm_id = clean_input($_POST['scorm_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM course_scorm WHERE course_id = :cid AND scorm_id = :sid");
        $stmt->execute(['cid' => $course_id, 'sid' => $scorm_id]);
        $success = "SCORM package removed from course!";
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Fetch Course Details
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    redirect('courses.php');
}

// Fetch Added SCORM Packages
$stmt = $pdo->prepare("
    SELECT sp.*, cs.added_at 
    FROM scorm_packages sp
    JOIN course_scorm cs ON sp.id = cs.scorm_id
    WHERE cs.course_id = ?
    ORDER BY cs.added_at DESC
");
$stmt->execute([$course_id]);
$added_scorms = $stmt->fetchAll();

// Fetch Available SCORM Packages (Not yet added)
$stmt = $pdo->prepare("
    SELECT * FROM scorm_packages 
    WHERE id NOT IN (SELECT scorm_id FROM course_scorm WHERE course_id = ?)
    ORDER BY title ASC
");
$stmt->execute([$course_id]);
$available_scorms = $stmt->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header"
    style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <?php 
        $back_link = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'student') ? 'my_training.php' : 'courses.php';
        $back_text = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'student') ? 'Back to Training' : 'Back to Courses';
        ?>
        <a href="<?php echo $back_link; ?>" class="btn btn-secondary" style="margin-bottom: 10px; display: inline-block;">
            <i class="fas fa-arrow-left"></i> <?php echo $back_text; ?>
        </a>
        <h2>
            <?php echo htmlspecialchars($course['course_name']); ?>
        </h2>
        <p style="color: var(--text-muted); margin-top: 5px;">
            <?php echo htmlspecialchars($course['description']); ?>
        </p>
    </div>
    <div>
        <?php if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student'): ?>
        <button class="btn btn-primary" onclick="openAddScormModal()">
            <i class="fas fa-plus"></i> Add SCORM Package
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <h3>Course Content (SCORM Packages)</h3>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Version</th>
                    <th>Added Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($added_scorms as $scorm): ?>
                    <tr>
                        <td><strong>
                                <?php echo htmlspecialchars($scorm['title']); ?>
                            </strong></td>
                        <td>
                            <?php echo htmlspecialchars($scorm['description']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($scorm['version']); ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($scorm['added_at'])); ?>
                        </td>
                        <td>
                            <div style="display: flex;">
                                <?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'student'): ?>
                                <a href="student_scorm_player.php?id=<?php echo $scorm['id']; ?>" class="btn-action-gray"
                                    title="Start Training" target="_blank">
                                    <i class="fas fa-play"></i>
                                </a>
                                <?php else: ?>
                                <a href="scorm_player.php?id=<?php echo $scorm['id']; ?>" class="btn-action-gray"
                                    title="Preview" target="_blank">
                                    <i class="fas fa-play"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'student'): ?>
                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Remove this package from the course?');">
                                    <input type="hidden" name="remove_scorm" value="1">
                                    <input type="hidden" name="scorm_id" value="<?php echo $scorm['id']; ?>">
                                    <button type="submit" class="btn-action-gray" title="Remove" style="color: #ef4444;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($added_scorms) == 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #9ca3af; padding: 30px;">
                            No SCORM packages added to this course yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
if ($success || $error) {
    $msg = $success ?: $error;
    $type = $success ? 'success' : 'error';
    echo "<script>document.addEventListener('DOMContentLoaded', function() { showToast('" . addslashes($msg) . "', '$type'); });</script>";
}
?>

<!-- Add SCORM Modal -->
<div id="addScormModal" class="modal">
    <div class="modal-content" style="width: 500px;">
        <div
            style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; color: #111827;">Add SCORM Package</h3>
            <span class="modal-close"
                onclick="document.getElementById('addScormModal').style.display='none'">&times;</span>
        </div>

        <form method="post" style="padding: 24px;">
            <div class="form-group">
                <label class="form-label">Select Package</label>

                <!-- Searchable Dropdown -->
                <div class="searchable-dropdown" style="position: relative;">
                    <div class="dropdown-selected" onclick="toggleScormDropdown()"
                        style="padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer; background: white; display: flex; justify-content: space-between; align-items: center;">
                        <span id="selectedScormText" style="color: #6b7280;">-- Choose a package --</span>
                        <i class="fas fa-chevron-down" style="font-size: 12px; color: #9ca3af;"></i>
                    </div>
                    <input type="hidden" name="scorm_id" id="selectedScormId" required>

                    <div id="scormDropdownList" class="dropdown-list"
                        style="display: none; position: absolute; width: 100%; max-height: 250px; overflow-y: auto; background: white; border: 1px solid #d1d5db; border-radius: 6px; margin-top: 5px; z-index: 1000; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                        <div
                            style="padding: 8px; border-bottom: 1px solid #f3f4f6; position: sticky; top: 0; background: white;">
                            <input type="text" id="scormSearchInput" placeholder="Search package..."
                                onkeyup="filterScormPackages()"
                                style="width: 100%; padding: 6px 10px; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 14px;">
                        </div>
                        <div id="scormItemsContainer">
                            <?php foreach ($available_scorms as $p): ?>
                                <div class="dropdown-item"
                                    onclick="selectScorm('<?php echo $p['id']; ?>', '<?php echo addslashes($p['title']); ?> (v<?php echo $p['version']; ?>)')"
                                    style="padding: 10px; cursor: pointer; border-bottom: 1px solid #f9fafb; font-size: 14px;">
                                    <?php echo htmlspecialchars($p['title']); ?> <span
                                        style="color:#9ca3af; font-size:12px;">(v<?php echo $p['version']; ?>)</span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($available_scorms) == 0): ?>
                                <div style="padding: 10px; color: #9ca3af; text-align: center; font-size: 14px;">No
                                    available packages found.</div>
                            <?php endif; ?>
                            <div id="noScormResults"
                                style="display: none; padding: 10px; color: #9ca3af; text-align: center; font-size: 14px;">
                                No packages found.</div>
                        </div>
                    </div>
                </div>

                <?php if (count($available_scorms) == 0): ?>
                    <small style="color: orange; display: block; margin-top: 5px;">No available packages found. <a
                            href="scorm_upload.php">Upload one first</a>.</small>
                <?php endif; ?>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button type="button" class="btn"
                    style="background: white; border: 1px solid #d1d5db; color: #374151; margin-right: 10px;"
                    onclick="document.getElementById('addScormModal').style.display='none'">Cancel</button>
                <button type="submit" name="add_scorm" class="btn btn-primary" <?php echo count($available_scorms) == 0 ? 'disabled' : ''; ?>>Add to Course</button>
            </div>
        </form>
    </div>
</div>

<style>
    .dropdown-item:hover {
        background-color: #f3f4f6;
    }
</style>

<script>
    function openAddScormModal() {
        document.getElementById('addScormModal').style.display = 'block';
    }

    // Dropdown Logic
    function toggleScormDropdown() {
        const list = document.getElementById('scormDropdownList');
        if (list.style.display === 'none') {
            list.style.display = 'block';
            document.getElementById('scormSearchInput').focus();
        } else {
            list.style.display = 'none';
        }
    }

    function filterScormPackages() {
        const input = document.getElementById('scormSearchInput');
        const filter = input.value.toUpperCase();
        const container = document.getElementById('scormItemsContainer');
        const items = container.getElementsByClassName('dropdown-item');
        let visibleCount = 0;

        for (let i = 0; i < items.length; i++) {
            const txtValue = items[i].textContent || items[i].innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                items[i].style.display = "";
                visibleCount++;
            } else {
                items[i].style.display = "none";
            }
        }

        const noResults = document.getElementById('noScormResults');
        if (visibleCount === 0 && items.length > 0) {
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    }

    function selectScorm(id, name) {
        document.getElementById('selectedScormId').value = id;
        document.getElementById('selectedScormText').innerText = name;
        document.getElementById('selectedScormText').style.color = '#111827';
        document.getElementById('scormDropdownList').style.display = 'none';
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function (event) {
        const dropdown = document.querySelector('.searchable-dropdown');
        const list = document.getElementById('scormDropdownList');
        if (dropdown && !dropdown.contains(event.target)) {
            list.style.display = 'none';
        }
    });
</script>

<?php require_once 'includes/footer.php'; ?>