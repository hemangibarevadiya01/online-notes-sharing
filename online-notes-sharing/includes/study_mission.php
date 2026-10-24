<?php
/**
 * NoteShare — Smart Study Mission & Knowledge Impact System
 * Handles daily study missions, activity logging, knowledge impact calculation, and badge milestones.
 */

require_once __DIR__ . '/db.php';

/**
 * Get or automatically initialize today's study mission for a student
 */
function get_or_create_daily_goal(int $user_id, ?string $date = null): array {
    global $pdo;
    if (!$date) {
        $date = date('Y-m-d');
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM study_goals WHERE user_id = ? AND goal_date = ? LIMIT 1");
        $stmt->execute([$user_id, $date]);
        $goal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$goal) {
            // Count actual activities already performed today
            $stmt_reads = $pdo->prepare("SELECT COUNT(*) FROM study_activity WHERE user_id = ? AND activity_date = ? AND activity_type IN ('read', 'preview')");
            $stmt_reads->execute([$user_id, $date]);
            $c_reads = (int)$stmt_reads->fetchColumn();

            $stmt_dl = $pdo->prepare("SELECT COUNT(*) FROM study_activity WHERE user_id = ? AND activity_date = ? AND activity_type = 'download'");
            $stmt_dl->execute([$user_id, $date]);
            $c_dl = (int)$stmt_dl->fetchColumn();

            $stmt_up = $pdo->prepare("SELECT COUNT(*) FROM study_activity WHERE user_id = ? AND activity_date = ? AND activity_type = 'upload'");
            $stmt_up->execute([$user_id, $date]);
            $c_up = (int)$stmt_up->fetchColumn();

            $target_reads = 3;
            $target_downloads = 2;
            $target_uploads = 1;
            $status = ($c_reads >= $target_reads && $c_dl >= $target_downloads && $c_up >= $target_uploads) ? 'completed' : 'in_progress';

            $stmt_ins = $pdo->prepare("INSERT INTO study_goals (user_id, goal_date, target_reads, target_downloads, target_uploads, completed_reads, completed_downloads, completed_uploads, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt_ins->execute([$user_id, $date, $target_reads, $target_downloads, $target_uploads, $c_reads, $c_dl, $c_up, $status]);

            $stmt->execute([$user_id, $date]);
            $goal = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $goal ?: [];
    } catch (PDOException $e) {
        error_log("Study goal error: " . $e->getMessage());
        return [];
    }
}

/**
 * Record a student learning activity and update daily mission progress
 */
function record_study_activity(int $user_id, string $type, ?int $reference_id = null): void {
    global $pdo;
    $date = date('Y-m-d');
    $points = 10;
    if ($type === 'upload') $points = 25;
    if ($type === 'download') $points = 15;

    try {
        // Log activity
        $stmt = $pdo->prepare("INSERT INTO study_activity (user_id, activity_type, reference_id, activity_date, points) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $type, $reference_id, $date, $points]);

        // Ensure goal exists
        $goal = get_or_create_daily_goal($user_id, $date);
        if ($goal) {
            $col = null;
            if (in_array($type, ['read', 'preview'])) $col = 'completed_reads';
            elseif ($type === 'download') $col = 'completed_downloads';
            elseif ($type === 'upload') $col = 'completed_uploads';

            if ($col) {
                $new_val = (int)$goal[$col] + 1;
                $stmt_upd = $pdo->prepare("UPDATE study_goals SET {$col} = ? WHERE id = ?");
                $stmt_upd->execute([$new_val, $goal['id']]);

                // Check completion
                $t_reads = (int)$goal['target_reads'];
                $t_dl = (int)$goal['target_downloads'];
                $t_up = (int)$goal['target_uploads'];
                $c_reads = ($col === 'completed_reads') ? $new_val : (int)$goal['completed_reads'];
                $c_dl = ($col === 'completed_downloads') ? $new_val : (int)$goal['completed_downloads'];
                $c_up = ($col === 'completed_uploads') ? $new_val : (int)$goal['completed_uploads'];

                if ($c_reads >= $t_reads && $c_dl >= $t_dl && $c_up >= $t_up && $goal['status'] !== 'completed') {
                    $pdo->prepare("UPDATE study_goals SET status = 'completed' WHERE id = ?")->execute([$goal['id']]);
                    if (function_exists('create_notification')) {
                        create_notification($user_id, 'Study Mission Completed! 🎯', 'Congratulations! You accomplished all targets for today\'s study mission.', 'mission', 'dashboard.php');
                    }
                }
            }
        }

        // Trigger badge check
        check_and_award_badges($user_id);
    } catch (PDOException $e) {
        error_log("Study activity error: " . $e->getMessage());
    }
}

/**
 * Calculate the user's Knowledge Impact Score from genuine contributions
 */
function calculate_knowledge_impact(int $user_id): array {
    global $pdo;
    try {
        // Uploads & Downloads received on user's notes
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_notes,
                COALESCE(SUM(downloads), 0) as total_downloads_received,
                COALESCE(SUM(views), 0) as total_views_received
            FROM notes 
            WHERE user_id = ? AND status = 'approved'
        ");
        $stmt->execute([$user_id]);
        $noteStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $notes_count = (int)($noteStats['total_notes'] ?? 0);
        $downloads_received = (int)($noteStats['total_downloads_received'] ?? 0);
        $views_received = (int)($noteStats['total_views_received'] ?? 0);

        // Likes received on user's notes
        $stmt_likes = $pdo->prepare("
            SELECT COUNT(*) 
            FROM likes l
            JOIN notes n ON l.note_id = n.id
            WHERE n.user_id = ?
        ");
        $stmt_likes->execute([$user_id]);
        $likes_received = (int)$stmt_likes->fetchColumn();

        // Average rating received
        $stmt_rate = $pdo->prepare("
            SELECT AVG(r.rating) as avg_rating, COUNT(*) as total_ratings
            FROM ratings r
            JOIN notes n ON r.note_id = n.id
            WHERE n.user_id = ?
        ");
        $stmt_rate->execute([$user_id]);
        $rateData = $stmt_rate->fetch(PDO::FETCH_ASSOC);
        $avg_rating = round((float)($rateData['avg_rating'] ?? 0), 1);
        $total_ratings = (int)($rateData['total_ratings'] ?? 0);

        // Learning activities performed by user
        $stmt_act = $pdo->prepare("SELECT COUNT(*) FROM study_activity WHERE user_id = ?");
        $stmt_act->execute([$user_id]);
        $total_activities = (int)$stmt_act->fetchColumn();

        // Formula: Approved notes (20 pts) + Downloads received (5 pts) + Likes received (3 pts) + Avg rating * 10 pts + Activities (2 pts)
        $score = ($notes_count * 20) + ($downloads_received * 5) + ($likes_received * 3) + (int)($avg_rating * 10) + ($total_activities * 2);

        // Determine Level
        $level = 'Novice Scholar';
        $level_badge = 'bg-secondary';
        if ($score >= 500) { $level = 'Academic Luminary'; $level_badge = 'bg-danger'; }
        elseif ($score >= 250) { $level = 'Distinguished Contributor'; $level_badge = 'bg-purple text-white'; }
        elseif ($score >= 100) { $level = 'Knowledge Champion'; $level_badge = 'bg-primary'; }
        elseif ($score >= 30) { $level = 'Active Contributor'; $level_badge = 'bg-info text-dark'; }

        return [
            'score' => $score,
            'level' => $level,
            'level_badge' => $level_badge,
            'approved_notes' => $notes_count,
            'downloads_received' => $downloads_received,
            'views_received' => $views_received,
            'likes_received' => $likes_received,
            'avg_rating' => $avg_rating,
            'total_ratings' => $total_ratings,
            'total_activities' => $total_activities
        ];
    } catch (PDOException $e) {
        error_log("Knowledge impact error: " . $e->getMessage());
        return [
            'score' => 0,
            'level' => 'Novice Scholar',
            'level_badge' => 'bg-secondary',
            'approved_notes' => 0,
            'downloads_received' => 0,
            'views_received' => 0,
            'likes_received' => 0,
            'avg_rating' => 0,
            'total_ratings' => 0,
            'total_activities' => 0
        ];
    }
}

/**
 * Check and award any earned milestone badges
 */
function check_and_award_badges(int $user_id): array {
    global $pdo;
    $newly_awarded = [];

    try {
        $impact = calculate_knowledge_impact($user_id);

        // Fetch counts for criteria
        $stmt_missions = $pdo->prepare("SELECT COUNT(*) FROM study_goals WHERE user_id = ? AND status = 'completed'");
        $stmt_missions->execute([$user_id]);
        $completed_missions = (int)$stmt_missions->fetchColumn();

        $stmt_reads = $pdo->prepare("SELECT COUNT(*) FROM study_activity WHERE user_id = ? AND activity_type IN ('read', 'preview')");
        $stmt_reads->execute([$user_id]);
        $total_reads = (int)$stmt_reads->fetchColumn();

        $stmt_ratings = $pdo->prepare("SELECT COUNT(*) FROM ratings WHERE user_id = ?");
        $stmt_ratings->execute([$user_id]);
        $total_ratings_given = (int)$stmt_ratings->fetchColumn();

        // Fetch all badges
        $badges = $pdo->query("SELECT * FROM badges")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch already awarded badges
        $stmt_awarded = $pdo->prepare("SELECT badge_id FROM user_badges WHERE user_id = ?");
        $stmt_awarded->execute([$user_id]);
        $already_awarded = $stmt_awarded->fetchAll(PDO::FETCH_COLUMN);

        foreach ($badges as $b) {
            if (in_array($b['id'], $already_awarded)) {
                continue;
            }

            $qualifies = false;
            $type = $b['requirement_type'];
            $req = (int)$b['requirement_count'];

            switch ($type) {
                case 'missions':
                    $qualifies = ($completed_missions >= $req);
                    break;
                case 'reads':
                    $qualifies = ($total_reads >= $req);
                    break;
                case 'uploads':
                    $qualifies = ($impact['approved_notes'] >= $req);
                    break;
                case 'downloads':
                    $qualifies = ($impact['downloads_received'] >= $req);
                    break;
                case 'goals_completed':
                    $qualifies = ($completed_missions >= $req);
                    break;
                case 'ratings':
                    $qualifies = ($total_ratings_given >= $req);
                    break;
            }

            if ($qualifies) {
                $stmt_insert = $pdo->prepare("INSERT IGNORE INTO user_badges (user_id, badge_id, awarded_at) VALUES (?, ?, NOW())");
                $stmt_insert->execute([$user_id, $b['id']]);

                if ($stmt_insert->rowCount() > 0) {
                    $newly_awarded[] = $b;
                    if (function_exists('create_notification')) {
                        create_notification(
                            $user_id,
                            "Badge Unlocked: {$b['title']}! " . ($b['symbol'] ?? '🏆'),
                            "You earned the '{$b['title']}' badge: {$b['description']}",
                            'badge',
                            'profile.php'
                        );
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Badge awarding error: " . $e->getMessage());
    }

    return $newly_awarded;
}

/**
 * Retrieve all badges with unlock status for user
 */
function get_user_badges_full(int $user_id): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                b.*,
                ub.awarded_at,
                IF(ub.id IS NOT NULL, 1, 0) as is_unlocked
            FROM badges b
            LEFT JOIN user_badges ub ON b.id = ub.badge_id AND ub.user_id = ?
            ORDER BY is_unlocked DESC, b.id ASC
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Get user badges error: " . $e->getMessage());
        return [];
    }
}

/**
 * Generate a dynamic study message based on genuine progress
 */
function get_motivational_message(float $progress_pct, int $impact_score): string {
    if ($progress_pct >= 100) {
        return "Outstanding dedication! You've accomplished today's complete study mission. Your knowledge momentum is inspiring!";
    } elseif ($progress_pct >= 66) {
        return "You're in the final stretch! Just one more milestone to complete today's mission. Keep the focus!";
    } elseif ($progress_pct >= 33) {
        return "Great study momentum! Consistency is the foundation of academic mastery. Keep exploring!";
    } elseif ($progress_pct > 0) {
        return "Good start today! Every page read and document reviewed sharpens your academic edge.";
    } else {
        return "A fresh day of learning awaits. Complete your study mission today to climb the knowledge leaderboard!";
    }
}
