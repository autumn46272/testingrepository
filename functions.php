<?php
function clean_input($data)
{
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function redirect($url)
{
    header("Location: $url");
    exit();
}

/**
 * Format date to a readable format
 */
function format_date($date)
{
    if (!$date)
        return 'N/A';
    return date('M d, Y', strtotime($date));
}

/**
 * Generate a unique course code
 * logic: 25D-26.1 (Program Code - Year . Batch)
 * This is a simplified version, real logic might need DB checks
 */
function generate_course_code($program_name, $batch_year, $batch_seq)
{
    // Extract first 2 digits of Program name for code, or use a map
    // Demo: "25 Day Course" -> "25D"
    $prog_code = "GEN";
    if (preg_match('/(\d+)/', $program_name, $matches)) {
        $prog_code = $matches[0] . "D";
    } else {
        $prog_code = strtoupper(substr($program_name, 0, 3));
    }

    $year_suffix = substr($batch_year, -2);

    return $prog_code . "-" . $year_suffix . "." . $batch_seq;
}

/**
 * Generate Student ID
 * Format: YYYY-XXXX (e.g., 2026-0001)
 */
function generate_student_id($pdo, $year = null)
{
    if (!$year) {
        $year = date('Y');
    }

    // Find the highest sequence for this year
    // Assuming student_id is stored as VARCHAR '2026-0001'
    $stmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id LIKE :year_prefix ORDER BY student_id DESC LIMIT 1");
    $stmt->execute([':year_prefix' => $year . '-%']);
    $last_id = $stmt->fetchColumn();

    if ($last_id) {
        // Extract sequence
        $parts = explode('-', $last_id);
        $seq = intval($parts[1]);
        $new_seq = $seq + 1;
    } else {
        $new_seq = 1;
    }

    return $year . '-' . str_pad($new_seq, 4, '0', STR_PAD_LEFT);
}

/**
 * Execute a prepared statement with parameters
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement
 */
function db_query($sql, $params = []) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage() . " | SQL: " . $sql);
        throw $e;
    }
}

/**
 * Fetch a single row
 * 
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array|null
 */
function db_fetch($sql, $params = []) {
    $result = db_query($sql, $params);
    return $result->fetch();
}

/**
 * Fetch all rows
 * 
 * @param string $sql SQL query
 * @param array $params Parameters
 * @return array
 */
function db_fetch_all($sql, $params = []) {
    $result = db_query($sql, $params);
    return $result->fetchAll();
}

/**
 * Insert and return last ID
 * 
 * @param string $sql INSERT query
 * @param array $params Parameters
 * @return int Last inserted ID
 */
function db_insert($sql, $params = []) {
    global $pdo;
    db_query($sql, $params);
    return $pdo->lastInsertId();
}

/**
 * Get database connection object
 * 
 * @return PDO
 */
function get_db_connection() {
    global $pdo;
    return $pdo;
}

/**
 * Check if request is POST
 */
function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Get POST value
 */
function post($key, $default = null) {
    return $_POST[$key] ?? $default;
}

/**
 * Set flash message
 */
function set_flash($type, $message) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

/**
 * Get and clear flash message
 */
function get_flash() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['flash_message'])) {
        $flash = [
            'type' => $_SESSION['flash_type'] ?? 'info',
            'message' => $_SESSION['flash_message']
        ];
        unset($_SESSION['flash_type'], $_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Generate branch-specific student ID
 * 
 * @param PDO $pdo Database connection
 * @param string $branch Branch name (Philippines or US)
 * @return string Generated student ID (e.g., RAPH-001 or RAUS-001)
 */
function generate_branch_student_id($pdo, $branch) {
    try {
        // 1. Get branch code (prefix) from branch_counters or hardcode map
        $stmt = $pdo->prepare("SELECT branch_code FROM branch_counters WHERE branch_name = ?");
        $stmt->execute([$branch]);
        $branch_data = $stmt->fetch();
        
        $prefix = ($branch_data && !empty($branch_data['branch_code'])) ? $branch_data['branch_code'] : 'GEN';

        // Additional check: If prefix is GEN, try to map it using the hardcoded list
        // This covers cases where the DB has the branch but the code is set to 'GEN'
        if ($prefix === 'GEN') {
            $branch_map = [
                'Philippines' => 'RAPH',
                'US' => 'RAUS',
                'USA' => 'RAUS'
            ];
            if (isset($branch_map[$branch])) {
                $prefix = $branch_map[$branch];
            }
        }

        $search_pattern = $prefix . '-%';

        // 2. Find the highest existing ID in the students table for this prefix
        // We order by length first, then value, to handle numeric sorting 009 vs 010 correctly if needed
        // Or simply order by student_id DESC if consistent length
        $stmt_last = $pdo->prepare("SELECT student_id FROM students WHERE student_id LIKE ? ORDER BY LENGTH(student_id) DESC, student_id DESC LIMIT 1");
        $stmt_last->execute([$search_pattern]);
        $last_id = $stmt_last->fetchColumn();

        if ($last_id) {
            // Extract the numeric part (Assumes FORMAT: PREFIX-XXX...)
            // Explode by last hyphen to be safe
            $parts = explode('-', $last_id);
            $last_num = end($parts);
            
            // Check if it's numeric before incrementing
            if (is_numeric($last_num)) {
                $new_seq = intval($last_num) + 1;
            } else {
                $new_seq = 1; // Fallback if format is weird
            }
        } else {
            $new_seq = 1; // Start at 1 if no students found
        }
        
        // 3. Generate new ID
        $student_id = $prefix . '-' . str_pad($new_seq, 3, '0', STR_PAD_LEFT);
        
        return $student_id;
        
    } catch (Exception $e) {
        error_log("Error generating student ID: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Log activity
 */
function log_activity($user_id, $action, $table_name, $record_id, $details = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, table_name, record_id, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $table_name, $record_id, $details]);
    } catch (PDOException $e) {
        // Silently fail if activity_log table doesn't exist
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Create a user account for a student
 * 
 * @param PDO $pdo Database connection
 * @param string $student_id Student ID (will be used as username)
 * @param string $first_name Student's first name
 * @param string $last_name Student's last name
 * @param string $user_type User type (default: "Academic Assistant")
 * @return array Array with 'success' (bool), 'username', 'password' (plain), and 'message'
 */
function create_user_for_student($pdo, $student_id, $first_name, $last_name, $email = null, $user_type = 'Student') {
    try {
        // Use student ID as username
        $username = $student_id;
        
        // Use student ID as default password
        $plain_password = $student_id;
        $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
        
        // Insert user into database with 'student' role (system checks role for access control)
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, first_name, last_name, email, role, user_type) VALUES (:user, :pass, :fname, :lname, :email, 'student', :type)");
        $stmt->execute([
            'user' => $username,
            'pass' => $password_hash,
            'fname' => $first_name,
            'lname' => $last_name,
            'email' => $email,
            'type' => $user_type
        ]);
        
        return [
            'success' => true,
            'username' => $username,
            'password' => $plain_password,
            'message' => "User account created successfully."
        ];
        
    } catch (PDOException $e) {
        // If username already exists, don't fail the whole process
        if ($e->getCode() == 23000) {
            error_log("User creation failed - username '$username' already exists: " . $e->getMessage());
            return [
                'success' => false,
                'username' => $username,
                'password' => null,
                'message' => "Note: User account with username '$username' already exists."
            ];
        } else {
            error_log("User creation error: " . $e->getMessage());
            return [
                'success' => false,
                'username' => null,
                'password' => null,
                'message' => "Error creating user account: " . $e->getMessage()
            ];
        }
    }
}