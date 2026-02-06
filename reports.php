<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth_check.php';

// Filter Defaults
$start_date = isset($_GET['start_date']) ? clean_input($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? clean_input($_GET['end_date']) : '';
$program_filter = isset($_GET['program']) ? clean_input($_GET['program']) : '';
$group_filter = isset($_GET['group_id']) ? clean_input($_GET['group_id']) : '';
$summary_mode = isset($_GET['summary_mode']) ? true : false;

// Fetch Groups for Filter
$groups = $pdo->query("SELECT id, group_name FROM groups ORDER BY group_name DESC")->fetchAll();

// Build Base WHERE Clause
$where_clauses = ["1=1"];
$params = [];

if ($start_date) {
    $where_clauses[] = "ar.activity_date >= :start";
    $params['start'] = $start_date;
}
if ($end_date) {
    $where_clauses[] = "ar.activity_date <= :end";
    $params['end'] = $end_date;
}
if ($program_filter) {
    $where_clauses[] = "ar.program = :prog";
    $params['prog'] = $program_filter;
}
if ($group_filter) {
    $where_clauses[] = "ar.group_id = :gid";
    $params['gid'] = $group_filter;
}

// CSV Export Logic
if (isset($_GET['export'])) {
    $filename = $summary_mode ? 'academic_summary_report_' : 'detailed_report_';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add Metadata Headers
    fputcsv($output, ['Report Generated', date('Y-m-d H:i:s')]);
    if ($program_filter)
        fputcsv($output, ['Program Filter', $program_filter]);
    if ($group_filter) { // Get group name for header
        foreach ($groups as $g)
            if ($g['id'] == $group_filter) {
                fputcsv($output, ['Group Filter', $g['group_name']]);
                break;
            }
    }
    fputcsv($output, []); // Empty line

    if ($summary_mode) {
        // SUMMARY EXPORT
        fputcsv($output, array('Student Name', 'Topic', 'Pre-Test Score', 'Post-Test Score'));

        $q = "SELECT s.first_name, s.last_name, ar.topic,
              MAX(CASE WHEN ar.activity_type = 'Pre-Test' THEN ar.score END) as pre_score,
              MAX(CASE WHEN ar.activity_type = 'Post-Test' THEN ar.score END) as post_score
              FROM academic_records ar 
              JOIN students s ON ar.student_id = s.id 
              WHERE " . implode(" AND ", $where_clauses) . "
              GROUP BY s.id, ar.topic
              ORDER BY s.last_name ASC, ar.topic ASC";

        $stmt = $pdo->prepare($q);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            fputcsv($output, array(
                $row['last_name'] . ', ' . $row['first_name'],
                $row['topic'],
                ($row['pre_score'] !== null ? $row['pre_score'] : 'N/A'),
                ($row['post_score'] !== null ? $row['post_score'] : 'N/A')
            ));
        }

    } else {
        // STANDARD EXPORT
        fputcsv($output, array('Date', 'Student ID', 'Name', 'Program', 'Group/Batch', 'Topic', 'Activity', 'Score/Status', 'Remarks'));

        $q = "SELECT ar.*, s.student_id as sid, s.first_name, s.last_name, g.course_code 
              FROM academic_records ar 
              JOIN students s ON ar.student_id = s.id 
              LEFT JOIN groups g ON ar.group_id = g.id
              WHERE " . implode(" AND ", $where_clauses) . "
              ORDER BY ar.activity_date DESC";

        $stmt = $pdo->prepare($q);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as $row) {
            $result = ($row['score'] !== null) ? $row['score'] : $row['attendance_status'];
            $grp_code = $row['course_code'] ?? 'N/A';
            fputcsv($output, array(
                $row['activity_date'],
                $row['sid'],
                $row['last_name'] . ', ' . $row['first_name'],
                $row['program'],
                $grp_code,
                $row['topic'],
                $row['activity_type'],
                $result,
                $row['remarks']
            ));
        }
    }
    fclose($output);
    exit();
}

// Display Logic
$report_data = [];
$show_results = isset($_GET['start_date']) || isset($_GET['program']) || isset($_GET['group_id']);

if ($show_results) {
    if ($summary_mode) {
        // SUMMARY QUERY
        $query = "SELECT s.first_name, s.last_name, s.student_id as sid, ar.topic,
                  MAX(CASE WHEN ar.activity_type = 'Pre-Test' THEN ar.score END) as pre_score,
                  MAX(CASE WHEN ar.activity_type = 'Post-Test' THEN ar.score END) as post_score
                  FROM academic_records ar 
                  JOIN students s ON ar.student_id = s.id 
                  WHERE " . implode(" AND ", $where_clauses) . "
                  GROUP BY s.id, ar.topic
                  ORDER BY s.last_name ASC, ar.topic ASC";
    } else {
        // STANDARD QUERY
        $query = "SELECT ar.*, s.first_name, s.last_name, s.student_id as sid, g.course_code 
                  FROM academic_records ar 
                  JOIN students s ON ar.student_id = s.id 
                  LEFT JOIN groups g ON ar.group_id = g.id
                  WHERE " . implode(" AND ", $where_clauses) . "
                  ORDER BY ar.activity_date DESC LIMIT 200"; // Limit for performance
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $report_data = $stmt->fetchAll();
}

require_once 'includes/header.php';
require_once 'includes/sidebar.php';
?>

<div class="page-header" style="margin-bottom: 24px;">
    <h2>Reports & Analytics</h2>
</div>

<div class="card" style="padding: 20px;">
    <h4 style="margin-bottom: 15px; color: var(--secondary-color);">Generate Report</h4>
    <form method="get" action="reports.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
            <label class="form-label">Program</label>
            <select name="program" class="form-control">
                <option value="">All Programs</option>
                <option value="25 Day Course" <?php echo $program_filter == '25 Day Course' ? 'selected' : ''; ?>>25 Day
                    Course</option>
                <option value="Final Coaching" <?php echo $program_filter == 'Final Coaching' ? 'selected' : ''; ?>>Final
                    Coaching</option>
                <option value="Review" <?php echo $program_filter == 'Review' ? 'selected' : ''; ?>>Review</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
            <label class="form-label">Group / Batch</label>
            <select name="group_id" class="form-control">
                <option value="">All Groups</option>
                <?php foreach ($groups as $g): ?>
                    <option value="<?php echo $g['id']; ?>" <?php echo $group_filter == $g['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($g['group_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-bottom: 0; display: flex; align-items: center; padding-bottom: 10px;">
            <label class="checkbox-item" style="font-weight: 600; color: var(--secondary-color);">
                <input type="checkbox" name="summary_mode" value="1" <?php echo $summary_mode ? 'checked' : ''; ?>
                    style="margin-right: 8px;">
                Summary Mode
            </label>
        </div>

        <div style="width: 100%; margin-top: 10px;">
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-filter"></i> Apply Filter
            </button>
            <?php
            $export_link = "reports.php?export=true";
            if ($start_date)
                $export_link .= "&start_date=$start_date";
            if ($end_date)
                $export_link .= "&end_date=$end_date";
            if ($program_filter)
                $export_link .= "&program=$program_filter";
            if ($group_filter)
                $export_link .= "&group_id=$group_filter";
            if ($summary_mode)
                $export_link .= "&summary_mode=1";
            ?>
            <a href="<?php echo $export_link; ?>" class="btn btn-primary" target="_blank">
                <i class="fas fa-file-export"></i> Export CSV
            </a>
            <a href="reports.php" class="btn btn-action-gray" title="Reset Filters"><i class="fas fa-undo"></i></a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <?php if ($summary_mode): ?>
                        <th>Student Name</th>
                        <th>Topic</th>
                        <th>Pre-Test Score</th>
                        <th>Post-Test Score</th>
                    <?php else: ?>
                        <th>Date</th>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Grp Code</th>
                        <th>Topic</th>
                        <th>Activity</th>
                        <th>Score / Status</th>
                        <th>Remarks</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($show_results): ?>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <?php if ($summary_mode): ?>
                                <td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['topic']); ?></td>
                                <td><?php echo ($row['pre_score'] !== null) ? $row['pre_score'] . '%' : '<span style="color:#9ca3af;">N/A</span>'; ?>
                                </td>
                                <td><?php echo ($row['post_score'] !== null) ? $row['post_score'] . '%' : '<span style="color:#9ca3af;">N/A</span>'; ?>
                                </td>
                            <?php else: ?>
                                <td><?php echo format_date($row['activity_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['sid']); ?></td>
                                <td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['program']); ?></td>
                                <td><span class="badge"
                                        style="background:#e5e7eb; color:#374151;"><?php echo htmlspecialchars($row['course_code'] ?? 'N/A'); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($row['topic'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['activity_type']); ?></td>
                                <td>
                                    <?php
                                    if ($row['score'] !== null)
                                        echo $row['score'] . '%';
                                    else
                                        echo $row['attendance_status'];
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['remarks']); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($report_data)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 20px;">No records found. Adjust filters to
                                search.</td>
                        </tr>
                    <?php endif; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 30px; color: #6b7280;">Please select filters and
                            click "Apply Filter" to generate data.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>