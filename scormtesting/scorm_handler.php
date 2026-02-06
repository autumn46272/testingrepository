<?php
/**
 * SCORM Handler API
 * Handles SCORM CMI data tracking and test result submissions
 * NCLEX-SCORM
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$action = $input['action'] ?? '';
$package_id = $input['package_id'] ?? 0;
$attempt_id = $input['attempt_id'] ?? 0;

try {
    switch ($action) {
        case 'initialize':
            /**
             * Initialize SCORM tracking
             * Returns existing CMI data if available
             */
            $stmt = db_query(
                "SELECT element, value FROM scorm_tracking WHERE user_id = ? AND package_id = ?",
                [$user_id, $package_id]
            );
            $data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Ensure some defaults if empty
            if (!isset($data['cmi.core.lesson_status'])) {
                $data['cmi.core.lesson_status'] = 'not attempted';
            }
            
            echo json_encode([
                'success' => true,
                'cmi' => $data,
                'attemptId' => $attempt_id
            ]);
            break;

        case 'commit':
            /**
             * Save SCORM CMI data
             * Saves multiple values from the SCORM player
             */
            $data = $input['data'] ?? [];
            if (!empty($data) && is_array($data)) {
                foreach ($data as $element => $value) {
                    db_query(
                        "INSERT INTO scorm_tracking (user_id, package_id, element, value) 
                         VALUES (?, ?, ?, ?) 
                         ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()",
                        [$user_id, $package_id, $element, $value]
                    );
                }
            }
            
            echo json_encode(['success' => true]);
            break;

        case 'get_value':
            /**
             * Get a specific SCORM tracking element
             */
            $element = $input['element'] ?? '';
            $result = db_fetch(
                "SELECT value FROM scorm_tracking WHERE user_id = ? AND package_id = ? AND element = ?",
                [$user_id, $package_id, $element]
            );
            $value = $result ? $result['value'] : '';
            
            echo json_encode([
                'success' => true,
                'value' => $value
            ]);
            break;

        case 'submit_test':
            /**
             * Submit test results when student completes the test
             * This is called when the iSpring package sends final results
             */
            $score = floatval($input['score'] ?? 0);
            $total_questions = intval($input['total_questions'] ?? 0);
            $correct_answers = intval($input['correct_answers'] ?? 0);
            $duration_seconds = intval($input['duration_seconds'] ?? 0);
            $submitted_data = $input['submitted_data'] ?? [];

            // ----------------------------------------------------------------
            // Fix for SCORM 'matching' interaction grading
            // ----------------------------------------------------------------
            $test_results = $input['test_results'] ?? [];
            $recalculated_correct = 0;
            $should_recalculate = false;

            if (!empty($test_results) && is_array($test_results)) {
                // Helper to parse SCORM 'matching' string 
                // Format: source_id.target_id,source_id.target_id
                if (!function_exists('parseScormMatching')) {
                    function parseScormMatching($str) {
                        if (empty($str)) return [];
                        $pairs = [];
                        foreach (explode(',', $str) as $pair) {
                            $parts = explode('.', $pair);
                            if (count($parts) >= 2) {
                                $pairs[$parts[0]] = $parts[1]; // Use source as key
                            }
                        }
                        return $pairs;
                    }
                }

                foreach ($test_results as $idx => &$res) {
                    // Try to determine type from submitted CMI data
                    // Note: cmi.interactions.n.type
                    $type_key = "cmi.interactions.$idx.type";
                    $type = $submitted_data[$type_key] ?? '';
                    
                    // Fallback: heuristic detection if type missing
                    if (empty($type) && isset($res['correct_answer']) && is_string($res['correct_answer'])) {
                        if (preg_match('/^[a-z0-9]+\.[a-z0-9_]+(,[a-z0-9]+\.[a-z0-9_]+)*$/i', $res['correct_answer'])) {
                            $type = 'matching';
                        }
                    }

                    if ($type === 'matching' && !empty($res['correct_answer']) && !empty($res['user_answer'])) {
                        // Apply custom grading
                        $correct_pairs = parseScormMatching($res['correct_answer']);
                        $student_pairs = parseScormMatching($res['user_answer']);
                        
                        $matches = 0;
                        $total_pairs = count($correct_pairs);
                        
                        foreach ($correct_pairs as $src => $tgt) {
                             if (isset($student_pairs[$src]) && $student_pairs[$src] === $tgt) {
                                 $matches++;
                             }
                        }
                        
                        // Partial Scoring Logic
                        $points_possible = floatval($res['points_possible'] ?? 1); 
                        $points_earned = 0;
                        
                        if ($total_pairs > 0) {
                            $ratio = $matches / $total_pairs;
                            $points_earned = round($ratio * $points_possible, 2);
                        }
                        
                        $is_fully_correct = ($matches === $total_pairs);
                        
                        // We update if the points earned or status differs from what was extracted (which was 0/1 boolean)
                        // If fully correct, it's correct. If partial, it's usually marked "incorrect" status but with points.
                        // Or we can mark it correct if > 0. Let's stick to strict status (must be perfect) but full partial points.
                        
                        /* 
                           However, if we update points, we MUST ensure $should_recalculate is true.
                           The JS sends points_earned based on simple isCorrect (0 or Weight).
                           So $res['points_earned'] coming in is either 0 or Weight.
                           Our calculated $points_earned might be 5.5.
                        */
                        
                        if (abs($points_earned - $res['points_earned']) > 0.01 || $is_fully_correct != $res['is_correct']) {
                            $res['is_correct'] = $is_fully_correct;
                            $res['points_earned'] = $points_earned;
                            $should_recalculate = true;
                        }
                    } else if (($type === 'choice' || preg_match('/^\d+(,\d+)*$/', $res['correct_answer'])) && strpos($res['correct_answer'], ',') !== false) {
                        // SCORM 'choice' (multiple response) or pattern looking like "0,1,2"
                        // Partial Scoring Logic
                        $correct_options = explode(',', $res['correct_answer']);
                        $student_options = explode(',', $res['user_answer']);
                        
                        // Filter out empty values
                        $correct_options = array_filter($correct_options, 'strlen');
                        $student_options = array_filter($student_options, 'strlen');
                        
                        $total_correct_options = count($correct_options);
                        
                        if ($total_correct_options > 0) {
                            // Calculate matches (intersection)
                            $matches = count(array_intersect($student_options, $correct_options));
                            
                            // Penalize extra wrong answers? (Optional, but strict scoring usually does)
                            // Formula: Points = (Matches / Total Correct) * Possible Points
                            // Simple proportional scoring:
                            $ratio = $matches / $total_correct_options;
                            
                            // Check if ratio > 0 but < 1 (partial credit)
                            if ($ratio > 0 && $ratio < 1) {
                                $res['is_correct'] = false; // Still "incorrect" fully? Or true? 
                                // Usually partial is "incorrect" for status but > 0 for points. 
                                // Let's keep is_correct=false so it shows red/yellow in UI, but give points.
                                // OR: if ($ratio >= 0.5) is_correct = true?
                                // User asked for "total points award is 2/3", implying points matter most.
                                
                                $points = round($ratio * $res['points_possible'], 2);
                                if ($points != $res['points_earned']) {
                                    $res['points_earned'] = $points;
                                    $should_recalculate = true;
                                    
                                    // Add specific note/marker for partial?
                                    // The UI displays points_earned / points_possible, so it should just work.
                                }
                            } elseif ($ratio == 1 && count($student_options) == $total_correct_options) {
                                // Perfect match
                                if (!$res['is_correct']) {
                                    $res['is_correct'] = true;
                                    $res['points_earned'] = $res['points_possible'];
                                    $should_recalculate = true;
                                }
                            }
                        }
                    }

                    if ($res['is_correct']) {
                        $recalculated_correct++;
                    }
                }
                unset($res); // Break reference
            }

            // Recalculate totals if we changed anything
            if ($should_recalculate) {
                // Determine total points earned and total points possible
                $total_points_earned = 0;
                $total_points_possible = 0;
                $correct_count = 0;

                foreach ($test_results as $res) {
                    $total_points_earned += floatval($res['points_earned']);
                    $total_points_possible += floatval($res['points_possible']);
                    if ($res['is_correct']) {
                        $correct_count++;
                    }
                }

                $correct_answers = $correct_count;
                
                if ($total_points_possible > 0) {
                    // Score based on points, not just count of fully correct items
                    $score = round(($total_points_earned / $total_points_possible) * 100, 2);
                } elseif ($total_questions > 0) {
                     // Fallback if points_possible is 0 for some reason
                    $score = round(($correct_answers / $total_questions) * 100, 2);
                }
            }
            // ----------------------------------------------------------------


            // Update attempt record
            db_query(
                "UPDATE scorm_attempts 
                 SET score = ?, status = 'completed', 
                     total_questions = ?, correct_answers = ?,
                     duration_seconds = ?, completed_at = NOW(),
                     submitted_data = ?
                 WHERE id = ? AND user_id = ? AND package_id = ?",
                [
                    $score,
                    $total_questions,
                    $correct_answers,
                    $duration_seconds,
                    json_encode($submitted_data),
                    $attempt_id,
                    $user_id,
                    $package_id
                ]
            );

            // Store individual test results if provided
            // $test_results is already populated and corrected above

            if (!empty($test_results) && is_array($test_results)) {
                foreach ($test_results as $result) {
                    db_query(
                        "INSERT INTO scorm_test_results 
                         (attempt_id, user_id, package_id, question_id, question_text, 
                          user_answer, correct_answer, is_correct, points_earned, 
                          points_possible, answer_time_seconds)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $attempt_id,
                            $user_id,
                            $package_id,
                            $result['question_id'] ?? null,
                            $result['question_text'] ?? null,
                            json_encode($result['user_answer'] ?? []),
                            json_encode($result['correct_answer'] ?? []),
                            $result['is_correct'] ? 1 : 0,
                            floatval($result['points_earned'] ?? 0),
                            floatval($result['points_possible'] ?? 1),
                            intval($result['answer_time_seconds'] ?? 0)
                        ]
                    );
                }
            }

            // Update or create performance summary
            $existing_perf = db_fetch(
                "SELECT * FROM student_performance_summary WHERE user_id = ? AND package_id = ?",
                [$user_id, $package_id]
            );

            if ($existing_perf) {
                // Update existing
                $new_attempts = $existing_perf['total_attempts'] + 1;
                $new_avg = ($existing_perf['average_score'] * $existing_perf['total_attempts'] + $score) / $new_attempts;
                $new_highest = max($existing_perf['highest_score'], $score);
                $new_lowest = min($existing_perf['lowest_score'], $score);
                $new_time = $existing_perf['total_time_spent_seconds'] + $duration_seconds;

                db_query(
                    "UPDATE student_performance_summary 
                     SET total_attempts = ?, highest_score = ?, average_score = ?, 
                         lowest_score = ?, total_time_spent_seconds = ?, 
                         last_attempt_at = NOW()
                     WHERE user_id = ? AND package_id = ?",
                    [$new_attempts, $new_highest, $new_avg, $new_lowest, $new_time, $user_id, $package_id]
                );
            } else {
                // Create new
                db_query(
                    "INSERT INTO student_performance_summary 
                     (user_id, package_id, total_attempts, highest_score, average_score, 
                      lowest_score, total_time_spent_seconds, last_attempt_at)
                     VALUES (?, ?, 1, ?, ?, ?, ?, NOW())",
                    [$user_id, $package_id, $score, $score, $score, $duration_seconds]
                );
            }

            // Log activity
            log_activity($user_id, 'test_submitted', 'scorm_attempts', $attempt_id, 
                        "Score: $score%, Duration: $duration_seconds seconds");

            echo json_encode([
                'success' => true,
                'message' => 'Test results submitted successfully',
                'score' => $score
            ]);
            break;

        case 'finish':
            /**
             * Mark attempt as finished (completion tracking)
             */
            db_query(
                "UPDATE scorm_attempts SET status = 'completed' WHERE id = ?",
                [$attempt_id]
            );

            echo json_encode(['success' => true]);
            break;

        case 'suspend':
            /**
             * Suspend attempt (student left, may resume later)
             */
            db_query(
                "UPDATE scorm_attempts SET status = 'suspended' WHERE id = ?",
                [$attempt_id]
            );

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }

} catch (Exception $e) {
    error_log("SCORM Handler Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

?>
