<?php
/**
 * SCORM Player with Integrated Score Display
 * Student Database System
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

$package_id = $_GET['id'] ?? 0;

if (!$package_id) {
    die('No package ID specified');
}

// Get package details
$package = db_fetch("SELECT * FROM scorm_packages WHERE id = ?", [$package_id]);

if (!$package) {
    die('Package not found');
}

// Create new attempt record
$user_id = $_SESSION['user_id'];
$attempt_id = db_insert(
    "INSERT INTO scorm_attempts (user_id, package_id, status, started_at) 
     VALUES (?, ?, 'in_progress', NOW())",
    [$user_id, $package_id]
);

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
    <h2>📦 <?php echo htmlspecialchars($package['title']); ?></h2>
    <?php 
    $back_link = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'student') ? 'my_training.php' : 'scorm_packages.php';
    $back_text = (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'student') ? 'Back to Training' : 'Back to Packages';
    ?>
    <a href="<?php echo $back_link; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> <?php echo $back_text; ?>
    </a>
</div>

<div class="card">
    <div style="margin-bottom: 20px;">
        <h4 style="color: var(--secondary-color); margin-bottom: 8px;">Package Information</h4>
        <p><strong>Title:</strong> <?php echo htmlspecialchars($package['title']); ?></p>
        <?php if ($package['description']): ?>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($package['description']); ?></p>
        <?php endif; ?>
        <p><strong>Version:</strong> <?php echo htmlspecialchars($package['version'] ?? '1.0'); ?></p>
        <p><strong>Created:</strong> <?php echo format_date($package['created_at']); ?></p>
    </div>

    <div style="border-top: 1px solid var(--border-color); padding-top: 20px;">
        <h4 style="color: var(--secondary-color); margin-bottom: 16px;">
            <i class="fas fa-play-circle"></i> SCORM Content Player
        </h4>
        
        <?php
        // Find the index file (could be in various locations)
        $base_path = BASE_PATH . '/' . $package['folder_path'];
        
        // Common possible index file locations in SCORM packages
        $possible_files = [
            'index.html',
            'story.html', 
            'index_lms.html',
            'scormcontent/index.html',
            'res/index.html',
            'res/story.html',
            'content/index.html',
            'player.html',
            'launch.html'
        ];
        
        $index_file = null;
        
        // First, try common locations
        foreach ($possible_files as $file) {
            if (file_exists($base_path . '/' . $file)) {
                $index_file = $file;
                break;
            }
        }
        
        // If not found, search recursively in subdirectories
        if (!$index_file && is_dir($base_path)) {
            function findIndexRecursive($dir, $base) {
                $items = scandir($dir);
                foreach ($items as $item) {
                    if ($item == '.' || $item == '..') continue;
                    
                    $path = $dir . '/' . $item;
                    
                    // Check if it's an index file
                    if (is_file($path) && in_array(strtolower($item), ['index.html', 'story.html', 'index_lms.html'])) {
                        return str_replace($base . '/', '', $path);
                    }
                    
                    // Recurse into subdirectories (max 3 levels deep)
                    if (is_dir($path) && substr_count($path, '/') - substr_count($base, '/') < 3) {
                        $found = findIndexRecursive($path, $base);
                        if ($found) return $found;
                    }
                }
                return null;
            }
            
            $index_file = findIndexRecursive($base_path, $base_path);
        }
        
        if ($index_file):
            $index_path = $package['folder_path'] . '/' . $index_file;
        ?>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <p style="margin: 0; color: var(--text-muted);">
                    <i class="fas fa-info-circle"></i> 
                    Complete the exam below. Your score will be displayed automatically when you finish.
                </p>
            </div>
            
            <!-- SCORM Player iframe -->
            <div class="player-content" id="scorm-player-container">
                <iframe 
                    id="scorm-iframe"
                    src="<?php echo htmlspecialchars($index_path); ?>"
                    width="100%"
                    height="800"
                    style="border: 1px solid #ddd; border-radius: 8px;"
                    allow="fullscreen; autoplay"
                ></iframe>
            </div>

            <!-- Score Display Section (hidden initially) -->
            <div id="score-display" style="display: none; margin-top: 30px;">
                <div class="card">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <h2 style="color: var(--secondary-color); margin-bottom: 10px;">🎉 Test Completed!</h2>
                        <p style="color: var(--text-muted);">Here are your results</p>
                    </div>
                        
                    <div class="stat-grid">
                        <!-- Score Card -->
                        <div class="stat-card highlight">
                            <div>
                                <div class="stat-value" id="score-percentage">--%</div>
                                <div class="stat-label">Your Score</div>
                                <div id="score-status" style="font-weight: 600; margin-top: 5px;"></div>
                            </div>
                            <i class="fas fa-chart-line" style="font-size: 2.5rem; color: var(--primary-color); opacity: 0.2;"></i>
                        </div>
                        
                        <!-- Correct Answers Card -->
                        <div class="stat-card">
                            <div>
                                <div class="stat-value" id="correct-count" style="color: #10B981;">--</div>
                                <div class="stat-label">Correct Answers</div>
                                <div id="total-questions" style="font-size: 0.9rem; color: var(--text-muted); margin-top: 5px;">out of -- questions</div>
                            </div>
                            <i class="fas fa-check-circle" style="font-size: 2.5rem; color: #10B981; opacity: 0.2;"></i>
                        </div>
                        
                        <!-- Time Elapsed Card -->
                        <div class="stat-card">
                            <div>
                                <div class="stat-value" id="time-elapsed" style="color: var(--secondary-color);">--:--</div>
                                <div class="stat-label">Time Taken</div>
                            </div>
                            <i class="fas fa-clock" style="font-size: 2.5rem; color: var(--secondary-color); opacity: 0.2;"></i>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-top: 10px;">
                        <a href="scorm_player.php?id=<?php echo $package_id; ?>" class="btn btn-primary">
                            <i class="fas fa-redo" style="margin-right: 8px;"></i> Retake Test
                        </a>
                        <a id="view-details-btn" href="#" class="btn btn-secondary">
                            <i class="fas fa-chart-bar" style="margin-right: 8px;"></i> View Detailed Results
                        </a>
                        <a href="scorm_history.php" class="btn btn-secondary">
                            <i class="fas fa-history" style="margin-right: 8px;"></i> My History
                        </a>
                        <a href="scorm_packages.php" class="btn btn-secondary" style="background-color: #6c757d;">
                            <i class="fas fa-arrow-left" style="margin-right: 8px;"></i> Back to Packages
                        </a>
                    </div>
                </div>
            </div>

            <!-- Hidden data for SCORM tracking -->
            <script>
                window.scormData = {
                    packageId: <?php echo $package_id; ?>,
                    attemptId: <?php echo $attempt_id; ?>,
                    userId: <?php echo $_SESSION['user_id']; ?>,
                    apiUrl: 'scorm_handler.php'
                };
            </script>
            
            <!-- SCORM API Wrapper - Must load BEFORE iframe -->
            <script src="assets/js/scorm-api.js?v=<?php echo time(); ?>"></script>
            
            <!-- Score Display Handler -->
            <script>
                // Poll for completion and display results
                let pollInterval;
                let pollCount = 0;
                const maxPolls = 300; // 5 minutes (300 * 1 second)
                
                function formatDuration(seconds) {
                    if (!seconds) return '0 sec';
                    const hours = Math.floor(seconds / 3600);
                    const minutes = Math.floor((seconds % 3600) / 60);
                    const secs = seconds % 60;
                    
                    let parts = [];
                    if (hours > 0) parts.push(hours + ' hr');
                    if (minutes > 0) parts.push(minutes + ' min');
                    if (secs > 0 || parts.length === 0) parts.push(secs + ' sec');
                    
                    return parts.join(' ');
                }
                
                function checkCompletion() {
                    pollCount++;
                    
                    if (pollCount > maxPolls) {
                        clearInterval(pollInterval);
                        console.log('[Score Display] Stopped polling after max attempts');
                        return;
                    }
                    
                    fetch(window.scormData.apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'get_attempt_results',
                            package_id: window.scormData.packageId,
                            attempt_id: window.scormData.attemptId
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.attempt && data.attempt.status === 'completed') {
                            clearInterval(pollInterval);
                            displayResults(data.attempt);
                        }
                    })
                    .catch(err => console.error('[Score Display] Error checking completion:', err));
                }
                
                function displayResults(attempt) {
                    console.log('[Score Display] Showing results:', attempt);
                    
                    // Update score percentage
                    const score = parseFloat(attempt.score || 0);
                    document.getElementById('score-percentage').textContent = score.toFixed(1) + '%';
                    
                    // Update status
                    const statusElem = document.getElementById('score-status');
                    if (score >= 70) {
                        statusElem.innerHTML = '✓ PASSED';
                        statusElem.style.color = '#4ade80';
                    } else {
                        statusElem.innerHTML = '✗ NEEDS IMPROVEMENT';
                        statusElem.style.color = '#fbbf24';
                    }
                    
                    // Update correct answers
                    const correct = parseInt(attempt.correct_answers || 0);
                    const total = parseInt(attempt.total_questions || 0);
                    document.getElementById('correct-count').textContent = correct;
                    document.getElementById('total-questions').textContent = 'out of ' + total + ' questions';
                    
                    // Update time elapsed
                    const duration = parseInt(attempt.duration_seconds || 0);
                    document.getElementById('time-elapsed').textContent = formatDuration(duration);
                    
                    // Update view details link
                    document.getElementById('view-details-btn').href = 
                        'scormtesting/student_attempt_details.php?id=' + window.scormData.attemptId;
                    
                    // Hide player, show results
                    document.getElementById('scorm-player-container').style.display = 'none';
                    document.getElementById('score-display').style.display = 'block';
                    
                    // Scroll to results
                    document.getElementById('score-display').scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                
                // Start polling for completion after a short delay
                setTimeout(() => {
                    pollInterval = setInterval(checkCompletion, 1000); // Check every second
                }, 2000);
            </script>

            <!-- SECURITY DETERRENTS -->
            <?php
            // Fetch User Details for Watermark
            $curr_user = db_fetch("SELECT username, first_name, last_name FROM users WHERE id = ?", [$user_id]);
            $user_label = $curr_user['first_name'] . ' ' . $curr_user['last_name'] . ' (' . $curr_user['username'] . ')';
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $watermark_text = $user_label . ' • ' . $ip_address . ' • ' . date('Y-m-d H:i');
            ?>
            
            <style>
                /* Watermark Grid */
                #watermark-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 9999;
                    pointer-events: none;
                    overflow: hidden;
                    display: flex;
                    flex-wrap: wrap;
                    opacity: 0.6; /* Visible but transparent */
                }
                
                .watermark-item {
                    width: 300px;
                    height: 200px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    transform: rotate(-30deg);
                    font-size: 14px;
                    font-weight: 700;
                    color: rgba(0, 0, 0, 0.12); /* Increased opacity */
                    user-select: none;
                    text-align: center;
                }

                /* Focus Loss Overlay */
                #security-blur {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.95);
                    z-index: 10000;
                    display: none;
                    align-items: center;
                    justify-content: center;
                    flex-direction: column;
                    color: white;
                    text-align: center;
                }
            </style>

            <div id="watermark-container">
                <?php 
                // Generate enough items to fill screen
                for ($i = 0; $i < 50; $i++) {
                    echo '<div class="watermark-item">' . htmlspecialchars($watermark_text) . '</div>';
                } 
                ?>
            </div>

            <div id="security-blur">
                <div style="font-size: 60px; margin-bottom: 20px;">🛡️</div>
                <h3>Content Hidden</h3>
                <p>Screen recording or switching windows is restricted.</p>
                <p style="font-size: 0.9rem; opacity: 0.7;">Click anywhere to resume.</p>
            </div>

            <script>
                // 1. Focus Loss Protection
                window.addEventListener('blur', function() {
                    // Small delay to check if focus moved to iframe
                    setTimeout(() => {
                        const active = document.activeElement;
                        const iframe = document.getElementById('scorm-iframe');
                        
                        // If focus moved to our iframe, don't hide content
                        if (active && (active === iframe || active.tagName === 'IFRAME')) {
                            return;
                        }
                        
                        document.getElementById('security-blur').style.display = 'flex';
                        document.title = '⚠️ Content Hidden';
                        
                        // Optional: pausing media inside iframe would require postMessage support
                        // if (iframe && iframe.contentWindow) { ... }
                    }, 50);
                });

                window.addEventListener('focus', function() {
                    document.getElementById('security-blur').style.display = 'none';
                    document.title = '<?php echo htmlspecialchars($package['title']); ?>';
                });

                // 2. Disable Print Screen / Shortcuts (Deterrent only)
                document.addEventListener('keyup', (e) => {
                    if (e.key == 'PrintScreen') {
                        navigator.clipboard.writeText('');
                        alert('Screenshots are disabled for security reasons.');
                    }
                });
                
                // 3. Disable Right Click
                document.addEventListener('contextmenu', event => event.preventDefault());

                // 4. Force check focus on load
                if (!document.hasFocus()) {
                    // document.getElementById('security-blur').style.display = 'flex';
                    // Commented out on load to avoid annoying popup if opened in background tab
                }
            </script>

            <!-- END SECURITY DETERRENTS -->

            
        <?php else: ?>
            <div style="background: #fee2e2; padding: 20px; border-radius: 8px; text-align: center;">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #991b1b; margin-bottom: 15px;"></i>
                <h4 style="color: #991b1b; margin-bottom: 8px;">SCORM Index File Not Found</h4>
                <p style="color: #991b1b;">
                    Could not locate index.html or story.html in the package.
                    <br>Please ensure your SCORM package is properly exported.
                </p>
                <p style="margin-top: 15px; font-size: 0.9rem; color: #666;">
                    <strong>Package Path:</strong> <code><?php echo htmlspecialchars($package['folder_path']); ?></code>
                </p>
                <div style="margin-top: 20px;">
                    <h5 style="color: #666; margin-bottom: 10px;">Files in Package:</h5>
                    <div style="background: white; padding: 15px; border-radius: 4px; text-align: left; max-height: 200px; overflow-y: auto;">
                        <?php
                        if (is_dir($base_path)) {
                            $files = scandir($base_path);
                            echo '<ul style="list-style: none; padding: 0; margin: 0;">';
                            foreach ($files as $file) {
                                if ($file != '.' && $file != '..') {
                                    $icon = is_dir($base_path . '/' . $file) ? '📁' : '📄';
                                    echo '<li style="padding: 4px 0;">' . $icon . ' ' . htmlspecialchars($file) . '</li>';
                                }
                            }
                            echo '</ul>';
                        } else {
                            echo '<p style="color: #991b1b;">Directory not found!</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="background-color: #f8f9fa;">
    <h4 style="color: var(--secondary-color); margin-bottom: 12px;">
        <i class="fas fa-question-circle"></i> Troubleshooting
    </h4>
    <ul style="color: var(--text-color); line-height: 1.8; padding-left: 20px;">
        <li>If content doesn't load, check browser console for errors (F12)</li>
        <li>Ensure the ZIP file was properly extracted</li>
        <li>SCORM packages should contain an index.html or story.html file</li>
        <li>Some SCORM packages may require specific LMS integration to work fully</li>
        <li>Your results will automatically appear once you complete the exam</li>
    </ul>
</div>

<?php require_once 'includes/footer.php'; ?>
