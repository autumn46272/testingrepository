<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$error = '';
$success = '';

// Handle Create Course
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_course'])) {
    $course_name = clean_input($_POST['course_name']);
    $description = clean_input($_POST['description']);

    if (empty($course_name)) {
        $error = "Course Name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO courses (course_name, description) VALUES (:name, :desc)");
            $stmt->execute(['name' => $course_name, 'desc' => $description]);
            $success = "Course created successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Edit Course
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_course'])) {
    $id = clean_input($_POST['course_id']);
    $course_name = clean_input($_POST['course_name']);
    $description = clean_input($_POST['description']);

    if (empty($course_name)) {
        $error = "Course Name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE courses SET course_name = :name, description = :desc WHERE id = :id");
            $stmt->execute(['name' => $course_name, 'desc' => $description, 'id' => $id]);
            $success = "Course updated successfully!";
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}

// Handle Delete Course
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_course'])) {
    $id = clean_input($_POST['course_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $success = "Course deleted successfully!";
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}

// Fetch All Courses
$stmt = $pdo->query("SELECT * FROM courses ORDER BY created_at DESC");
$courses = $stmt->fetchAll();

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header"
    style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <h2>Courses Management</h2>
    <div style="display: flex; gap: 10px;">
        <input type="text" id="searchInput" class="form-control" placeholder="Search..." onkeyup="filterTable()"
            style="width: 250px; height: 40px;">
        <button class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus" style="margin-right: 8px;"></i> Add Course
        </button>
    </div>
</div>

<div class="card">
    <div class="table-container">
        <table id="coursesTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Course Name</th>
                    <th>Description</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td>
                            <?php echo $course['id']; ?>
                        </td>
                        <td><span style="font-weight: 600; color: var(--primary-color);">
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </span></td>
                        <td>
                            <?php echo htmlspecialchars($course['description']); ?>
                        </td>
                        <td>
                            <?php echo date('M d, Y', strtotime($course['created_at'])); ?>
                        </td>
                        <td>
                            <div style="display: flex;">
                                <a href="course_view.php?id=<?php echo $course['id']; ?>" class="btn-action-gray"
                                    title="View Course">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn-action-gray" title="Edit"
                                    onclick="openEditModal(<?php echo htmlspecialchars(json_encode($course)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('Delete this course?');">
                                    <input type="hidden" name="delete_course" value="1">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" class="btn-action-gray" title="Delete" style="color: #ef4444;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($courses) == 0): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #9ca3af; padding: 30px;">No courses found.</td>
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

<!-- Create Course Modal -->
<div id="createCourseModal" class="modal">
    <div class="modal-content" style="width: 500px;">
        <div
            style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; color: #111827;">Add New Course</h3>
            <span class="modal-close"
                onclick="document.getElementById('createCourseModal').style.display='none'">&times;</span>
        </div>

        <form method="post" style="padding: 24px;">
            <div class="form-group">
                <label class="form-label">Course Name</label>
                <input type="text" name="course_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="4"></textarea>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button type="button" class="btn"
                    style="background: white; border: 1px solid #d1d5db; color: #374151; margin-right: 10px;"
                    onclick="document.getElementById('createCourseModal').style.display='none'">Cancel</button>
                <button type="submit" name="create_course" class="btn btn-primary">Create Course</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Course Modal -->
<div id="editCourseModal" class="modal">
    <div class="modal-content" style="width: 500px;">
        <div
            style="padding: 20px 24px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 18px; color: #111827;">Edit Course</h3>
            <span class="modal-close"
                onclick="document.getElementById('editCourseModal').style.display='none'">&times;</span>
        </div>

        <form method="post" style="padding: 24px;">
            <input type="hidden" name="course_id" id="edit_course_id">

            <div class="form-group">
                <label class="form-label">Course Name</label>
                <input type="text" name="course_name" id="edit_course_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_description" class="form-control" rows="4"></textarea>
            </div>

            <div style="text-align: right; margin-top: 24px;">
                <button type="button" class="btn"
                    style="background: white; border: 1px solid #d1d5db; color: #374151; margin-right: 10px;"
                    onclick="document.getElementById('editCourseModal').style.display='none'">Cancel</button>
                <button type="submit" name="edit_course" class="btn btn-primary">Update Course</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        document.getElementById('createCourseModal').style.display = 'block';
    }

    function openEditModal(course) {
        document.getElementById('edit_course_id').value = course.id;
        document.getElementById('edit_course_name').value = course.course_name;
        document.getElementById('edit_description').value = course.description || '';
        document.getElementById('editCourseModal').style.display = 'block';
    }

    function filterTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toUpperCase();
        const table = document.getElementById('coursesTable');
        const tr = table.getElementsByTagName('tr');
        for (let i = 1; i < tr.length; i++) {
            let visible = false;
            const tds = tr[i].getElementsByTagName('td');
            for (let j = 0; j < tds.length; j++) {
                if (tds[j] && tds[j].innerText.toUpperCase().indexOf(filter) > -1) { visible = true; break; }
            }
            tr[i].style.display = visible ? "" : "none";
        }
    }
</script>

<?php require_once 'includes/footer.php'; ?>