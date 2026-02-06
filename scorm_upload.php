<?php
/**
 * Upload SCORM Package
 * Student Database System
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// Access Control
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    set_flash('error', 'Access denied.');
    redirect('student_dashboard.php');
}

$page_title = 'Upload SCORM Package';

if (is_post() && isset($_FILES['scorm_package'])) {
    $title = clean_input(post('title', ''));
    $description = clean_input(post('description', ''));
    $file = $_FILES['scorm_package'];
    
    if (empty($title)) {
        set_flash('error', 'Please provide a package title.');
    } elseif (empty($file['name'])) {
        set_flash('error', 'Please select a file to upload.');
    } elseif (!in_array($file['type'], ALLOWED_SCORM_TYPES)) {
        set_flash('error', 'Only ZIP files are allowed for SCORM packages.');
    } elseif ($file['size'] > MAX_FILE_SIZE) {
        set_flash('error', 'File size exceeds maximum allowed size (50MB).');
    } else {
        try {
            // 1. Create DB entry to get ID
            $package_id = db_insert(
                "INSERT INTO scorm_packages (title, description, folder_path, created_by) VALUES (?, ?, '', ?)",
                [$title, $description, $_SESSION['user_id'] ?? 1]
            );
            
            // 2. Prepare paths
            $upload_dir = SCORM_UPLOAD_PATH . '/' . $package_id;
            
            // Create directory
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // 3. Extract ZIP
            $zip = new ZipArchive;
            if ($zip->open($file['tmp_name']) === TRUE) {
                $zip->extractTo($upload_dir);
                $zip->close();
                
                // 4. Update DB with path (relative to web root)
                $web_path = 'assets/uploads/scorm/' . $package_id;
                db_query(
                    "UPDATE scorm_packages SET folder_path = ? WHERE id = ?",
                    [$web_path, $package_id]
                );
                
                log_activity($_SESSION['user_id'] ?? 1, 'scorm_package_uploaded', 'scorm_packages', $package_id, "Uploaded: $title");
                
                set_flash('success', 'SCORM package uploaded and extracted successfully!');
                redirect('scorm_packages.php');
            } else {
                // Cleanup DB if zip failed
                db_query("DELETE FROM scorm_packages WHERE id = ?", [$package_id]);
                // Delete created directory
                if (is_dir($upload_dir)) {
                    rmdir($upload_dir);
                }
                set_flash('error', 'Failed to extract ZIP file. Please ensure it is a valid SCORM package.');
            }
            
        } catch (Exception $e) {
            error_log("SCORM upload error: " . $e->getMessage());
            set_flash('error', 'Error: ' . $e->getMessage());
        }
    }
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
    <h2>📤 Upload SCORM Package</h2>
    <a href="scorm_packages.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Packages
    </a>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label class="form-label" for="title">Package Title *</label>
            <input type="text" 
                   id="title" 
                   name="title" 
                   class="form-control" 
                   required 
                   placeholder="Ex: Respiratory System Review - iSpring" 
                   value="<?php echo htmlspecialchars(post('title', '')); ?>">
            <small style="color: var(--text-muted); display: block; margin-top: 4px;">This will be the display name for students</small>
        </div>

        <div class="form-group">
            <label class="form-label" for="description">Description</label>
            <textarea id="description" 
                      name="description" 
                      class="form-control" 
                      rows="4" 
                      placeholder="Enter package description..."><?php echo htmlspecialchars(post('description', '')); ?></textarea>
            <small style="color: var(--text-muted); display: block; margin-top: 4px;">Optional: Describe the content and learning objectives</small>
        </div>

        <div class="form-group">
            <label class="form-label" for="scorm_package">SCORM Package (.zip) *</label>
            <div class="file-upload-wrapper" onclick="document.getElementById('file_input').click()" style="border: 2px dashed var(--border-color); padding: 40px; text-align: center; border-radius: 8px; cursor: pointer; transition: var(--transition);">
                <input type="file" 
                       id="file_input" 
                       name="scorm_package" 
                       accept=".zip" 
                       required 
                       onchange="updateFileName(this)" 
                       style="display: none;">
                <div style="font-size: 48px; margin-bottom: 10px;">📦</div>
                <h4 id="file_label" style="color: var(--secondary-color); margin-bottom: 8px;">Click to select ZIP file or drag and drop</h4>
                <p style="color: var(--text-muted); font-size: 0.9rem;">Must be a valid SCORM 1.2 package (exported from iSpring)</p>
            </div>
        </div>

        <div style="margin-top: 30px; display: flex; gap: 12px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload & Extract
            </button>
            <a href="scorm_packages.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>

    <div class="card" style="margin-top: 30px; background-color: #f8f9fa; border: 1px solid var(--border-color);">
        <h4 style="color: var(--secondary-color); margin-bottom: 12px;">
            <i class="fas fa-info-circle"></i> About SCORM Packages
        </h4>
        <ul style="color: var(--text-color); line-height: 1.8; padding-left: 20px;">
            <li>Export your iSpring test as a SCORM 1.2 package (ZIP format)</li>
            <li>The ZIP should contain an imsmanifest.xml or index.html file</li>
            <li>Maximum file size: 50MB</li>
            <li>After upload, you can enroll students and track their results</li>
        </ul>
    </div>
</div>

<script>
function updateFileName(input) {
    const label = document.getElementById('file_label');
    if (input.files && input.files[0]) {
        label.textContent = '✓ Selected: ' + input.files[0].name;
        label.style.color = 'var(--primary-color)';
    }
}

// Drag and drop
const fileUploadWrapper = document.querySelector('.file-upload-wrapper');
if (fileUploadWrapper) {
    fileUploadWrapper.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileUploadWrapper.style.borderColor = 'var(--primary-color)';
        fileUploadWrapper.style.backgroundColor = '#f0f4ff';
    });
    
    fileUploadWrapper.addEventListener('dragleave', (e) => {
        e.preventDefault();
        fileUploadWrapper.style.borderColor = 'var(--border-color)';
        fileUploadWrapper.style.backgroundColor = 'transparent';
    });
    
    fileUploadWrapper.addEventListener('drop', (e) => {
        e.preventDefault();
        fileUploadWrapper.style.borderColor = 'var(--border-color)';
        fileUploadWrapper.style.backgroundColor = 'transparent';
        
        const files = e.dataTransfer.files;
        const fileInput = document.getElementById('file_input');
        fileInput.files = files;
        
        if (files && files[0]) {
            updateFileName(fileInput);
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>
