<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .container { max-width: 800px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background-color: #f8fafc; font-weight: 600; }
        .correct { background-color: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 4px; font-size: 0.875rem; }
        .incorrect { background-color: #fee2e2; color: #991b1b; padding: 4px 8px; border-radius: 4px; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card" style="text-align: left;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <h1>Results Board</h1>
                <button class="btn" onclick="location.reload()" style="padding: 0.5rem 1rem; font-size: 0.875rem;">Refresh</button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">Rank</th>
                        <th>Student Name</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th style="width: 15%;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Enable error reporting for debugging
                    ini_set('display_errors', 1);
                    ini_set('display_startup_errors', 1);
                    error_reporting(E_ALL);

                    // Include global config
                    require_once '../config.php';
                    
                    // $pdo is available from config.php
                    
                    try {
                        $exam_code = isset($_GET['code']) ? trim($_GET['code']) : '';
                        $exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

                        // 1. Resolve Exam ID
                        $exam_title = '';
                        $display_code = '';

                        if ($exam_id > 0) {
                            // Fetch by ID
                            $stmt = $pdo->prepare("SELECT exam_code, title FROM quiz_exams WHERE id = ?");
                            $stmt->execute([$exam_id]);
                            $row = $stmt->fetch();
                            
                            if ($row) {
                                $display_code = $row['exam_code'];
                                $exam_title = $row['title'];
                            } else {
                                $exam_id = 0; // Not found
                            }
                        } elseif (!empty($exam_code)) {
                            // Fallback: Fetch by Code
                            $stmt = $pdo->prepare("SELECT id, title FROM quiz_exams WHERE exam_code = ?");
                            $stmt->execute([$exam_code]);
                            $row = $stmt->fetch();
                            
                            if ($row) {
                                $exam_id = $row['id'];
                                $display_code = $exam_code;
                                $exam_title = $row['title'];
                            }
                        }

                        if ($exam_id == 0) {
                             echo "<tr><td colspan='5' style='text-align:center; padding: 40px; color: #ef4444;'>
                                        <h3>Session Not Found</h3>
                                        <p>Please check your link or code.</p>
                                      </td></tr>";
                        } else {
                                // 3. LEADERBOARD QUERY
                                // Tally 'is_correct' for each student in this exam
                                $sql = "SELECT s.name, 
                                               SUM(r.is_correct) as total_score, 
                                               COUNT(r.id) as total_questions,
                                               MAX(r.submitted_at) as last_submission
                                        FROM quiz_responses r
                                        JOIN quiz_students s ON r.student_id = s.id
                                        WHERE r.exam_id = ?
                                        GROUP BY r.student_id
                                        ORDER BY total_score DESC, last_submission ASC";
                                
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([$exam_id]);
                                $results = $stmt->fetchAll();

                                if (count($results) > 0) {
                                    $rank = 1;
                                    foreach ($results as $row) {
                                        $score = $row['total_score'];
                                        $total = $row['total_questions'];
                                        $percent = ($total > 0) ? round(($score / $total) * 100) : 0;
                                        
                                        // Badge Logic
                                        $badgeClass = 'badge-inactive'; 
                                        $badgeText = 'Participant';
                                        
                                        if ($rank === 1) {
                                            $badgeClass = 'badge-active'; 
                                            $badgeText = 'Winner 🏆';
                                        } elseif ($percent >= 75) {
                                            $badgeClass = 'badge-active'; 
                                            $badgeText = 'Passed';
                                        }

                                        echo "<tr>
                                                <td style='font-weight:bold; color:#64748b;'>#$rank</td>
                                                <td style='font-weight:600;'>" . htmlspecialchars($row['name']) . "</td>
                                                <td>
                                                    <span style='font-weight:bold; color: #0f172a;'>$score</span> 
                                                    <span style='color:#94a3b8; font-size:0.9em;'>/ $total</span>
                                                </td>
                                                <td>
                                                    <div style='display:flex; align-items:center;'>
                                                        <div style='width:100px; height:6px; background:#e2e8f0; border-radius:3px; margin-right:10px;'>
                                                            <div style='width:{$percent}%; height:100%; background:var(--primary-color); border-radius:3px;'></div>
                                                        </div>
                                                        <span>{$percent}%</span>
                                                    </div>
                                                </td>
                                                <td><span class='badge $badgeClass'>$badgeText</span></td>
                                              </tr>";
                                        $rank++;
                                    }
                                } else {
                                    echo "<tr><td colspan='5' style='text-align:center; padding:30px; color: #64748b;'>No results recorded for this session yet.</td></tr>";
                                }
                        }
                    } catch (Exception $e) {
                        echo "<tr><td colspan='5' style='color:red; text-align:center; padding:20px;'>Error: " . $e->getMessage() . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
