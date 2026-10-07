<?php
/**
 * includes/ai_recommendation.php — Community Wellness Recommendation Engine
 * =====================================================================
 * NEW LIFE FITNESS — Community Engagement Project (CEP)
 *
 * ⚠ HONEST AI DISCLOSURE (required by the project brief):
 * ---------------------------------------------------------------------
 * This module is a **RULE-BASED RECOMMENDATION PROTOTYPE** — NOT a
 * trained machine-learning model.
 *
 * Why not ML?
 *   A legitimate ML recommender needs a dataset of thousands of
 *   historical participation decisions to train and validate on. A new
 *   community platform starts with zero such rows, so any "ML" trained
 *   now would be statistically meaningless and claiming it as ML would
 *   be false advertising of the feature.
 *
 * What this IS:
 *   A transparent, weighted scoring recommender. Every recommendation
 *   carries an explainable reason ("why") and a score (0-100). The rules
 *   encode domain knowledge (fitness level, goal, activity preference,
 *   age, past participation, challenge participation) the way a human
 *   trainer would reason.
 *
 * What it is NOT:
 *   Not a neural network, not collaborative filtering over learned
 *   embeddings, not a trained classifier.
 *
 * Upgrade path to genuine ML (documented for the college report):
 *   Once ≥ ~1,000 real event registrations accumulate, the same input
 *   features can train e.g. a logistic-regression / decision-tree
 *   relevance classifier, or item embeddings for collaborative
 *   filtering. The feature extraction code below (profile features +
 *   participation matrix) is written so it can feed that training
 *   pipeline without re-architecting the app. See docs/AI_DOCUMENTATION.md.
 *
 * HOW IT WORKS (algorithm)
 * ---------------------------------------------------------------------
 * Inputs per community user:
 *   u1 fitness_level    Beginner / Intermediate / Advanced
 *   u2 fitness_goal     General Fitness / Weight Loss / Strength /
 *                       Flexibility / Endurance / Wellness
 *   u3 preferred_activities (CSV string, e.g. "Yoga, Walking")
 *   u4 age
 *   p1 past event registrations + attendance (categories attended)
 *   p2 challenge participation (categories of joined challenges)
 *
 * Candidate items = upcoming community events + active challenges.
 *
 * Scoring per candidate (total = weighted sum, capped at 100):
 *   Category match (0-40): direct keyword match between the user's
 *     preferred activities / goal and the item category.
 *   Level match (0-20): Beginner-friendly categories score higher for
 *     beginners; intense categories higher for advanced users.
 *   Goal match (0-20): mapping from fitness goal → activity categories.
 *   Age suitability (0-10): senior programs boosted for 50+, family
 *     events for mid-ages, etc.
 *   Freshness/novelty (0-10): items from categories the user has NOT
 *     already attended recently get a small boost (variety).
 *
 * Every recommendation is logged into `ai_recommendations` with its
 * reason + score + engine_version so recommendations are auditable.
 */

define('AI_ENGINE_VERSION', 'rule-v1');

/**
 * Goal → recommended activity categories (domain knowledge map).
 */
function ai_goal_categories() {
    return [
        'General Fitness' => ['Fitness Camp', 'Walking', 'Community Challenge', 'Other'],
        'Weight Loss'     => ['Zumba', 'Running', 'Walking', 'Fitness Camp'],
        'Strength'        => ['Fitness Camp', 'Running', 'Community Challenge'],
        'Flexibility'     => ['Yoga', 'Wellness', 'Senior Fitness'],
        'Endurance'       => ['Running', 'Walking', 'Community Challenge'],
        'Wellness'        => ['Yoga', 'Wellness', 'Nutrition Workshop', 'Health Awareness', 'Women Wellness'],
    ];
}

/**
 * Category → typical intensity (used for level matching).
 */
function ai_category_intensity() {
    return [
        'Yoga'              => 'low',
        'Walking'           => 'low',
        'Senior Fitness'    => 'low',
        'Wellness'          => 'low',
        'Health Awareness'  => 'low',
        'Nutrition Workshop'=> 'low',
        'Women Wellness'    => 'low',
        'Zumba'             => 'medium',
        'Fitness Camp'      => 'medium',
        'Community Challenge'=> 'medium',
        'Running'           => 'high',
        'Other'             => 'medium',
    ];
}

/**
 * Extract the user's feature vector (also usable later as ML features).
 */
function ai_user_features($user) {
    $prefs = array_map('trim', explode(',', (string)($user['preferred_activities'] ?? '')));
    $prefs = array_values(array_filter($prefs, fn($p) => $p !== '' && strtolower($p) !== 'any'));
    return [
        'fitness_level' => $user['fitness_level'] ?? 'Beginner',
        'fitness_goal'  => $user['fitness_goal'] ?? 'General Fitness',
        'age'           => (int)($user['age'] ?? 30),
        'preferences'   => $prefs,                     // e.g. ["Yoga","Walking"]
        'pref_lower'    => array_map('strtolower', $prefs),
    ];
}

/**
 * Categories the user has already engaged with (events + challenges).
 */
function ai_participation_features($userId) {
    $db = db();
    $cats = [];
    $stmt = $db->prepare(
        "SELECT DISTINCT ce.category
           FROM event_registrations er
           JOIN community_events ce ON ce.id = er.event_id
          WHERE er.community_user_id = ? AND er.status <> 'Cancelled'"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $cats[] = $r['category'];
    $stmt->close();

    $stmt = $db->prepare(
        "SELECT DISTINCT fc.category
           FROM challenge_participants cp
           JOIN fitness_challenges fc ON fc.id = cp.challenge_id
          WHERE cp.community_user_id = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $cats[] = $r['category'];
    $stmt->close();
    return array_unique($cats);
}

/**
 * Score one candidate item (event or challenge) for one user.
 * Returns [score, reason] — the reason explains the recommendation.
 */
function ai_score_item($features, $item, $itemType, $participatedCats) {
    $score = 0.0;
    $reasons = [];

    $category = $item['category'] ?? 'Other';
    $goalCats = ai_goal_categories()[$features['fitness_goal']] ?? [];

    /* 1) Preference match (0-40) — the strongest signal. */
    $prefHit = false;
    foreach ($features['pref_lower'] as $p) {
        if ($p !== '' && stripos($category, $p) !== false) { $prefHit = true; break; }
        if (stripos($p, $category) !== false) { $prefHit = true; break; }
    }
    if ($prefHit) {
        $score += 40;
        $reasons[] = 'matches your "' . ($item['category']) . '" interest';
    }

    /* 2) Level match (0-20). */
    $intensity = ai_category_intensity()[$category] ?? 'medium';
    $level     = $features['fitness_level'];
    $levelBonus = 0;
    if ($level === 'Beginner')   $levelBonus = ($intensity === 'low') ? 20 : ($intensity === 'medium' ? 10 : 2);
    if ($level === 'Intermediate') $levelBonus = ($intensity === 'medium') ? 20 : ($intensity === 'low' ? 14 : 10);
    if ($level === 'Advanced')   $levelBonus = ($intensity === 'high') ? 20 : ($intensity === 'medium' ? 14 : 8);
    $score += $levelBonus;
    if ($levelBonus >= 14) $reasons[] = 'suitable for ' . strtolower($level) . ' level';

    /* 3) Goal match (0-20). */
    if (in_array($category, $goalCats, true)) {
        $score += 20;
        $reasons[] = 'supports your "' . $features['fitness_goal'] . '" goal';
    }

    /* 4) Age suitability (0-10). */
    $age = $features['age'];
    $ageBonus = 0;
    if ($age >= 55) {
        if ($category === 'Senior Fitness' || $intensity === 'low') { $ageBonus = 10; }
    } elseif ($age <= 25) {
        if ($intensity === 'high' || $category === 'Community Challenge') { $ageBonus = 8; }
    } elseif ($age >= 26 && $age <= 54 && in_array($category, ['Fitness Camp','Zumba','Walking','Nutrition Workshop','Women Wellness'], true)) {
        $ageBonus = 6;
    }
    $score += $ageBonus;
    if ($ageBonus >= 8) $reasons[] = 'age-appropriate';

    /* 5) Variety bonus (0-10) — small boost for something new. */
    if (!in_array($category, $participatedCats, true)) {
        $score += 10;
        $reasons[] = 'a new activity type for you';
    }

    $score = max(0.0, min(100.0, round($score, 2)));
    $reason = 'Recommended because it ' . (count($reasons) ? implode('; ', $reasons) : 'fits your community profile') . '.';
    return [$score, $reason];
}

/**
 * Build the recommendation list for a community user.
 * Returns an array of recommendations sorted by score desc, each:
 *   [type, item_row, score, reason, event_id, challenge_id]
 */
function ai_recommendations_for($userId, $limit = 6) {
    $db  = db();
    $user = null;

    $stmt = $db->prepare("SELECT * FROM community_users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$user) return [];

    $features = ai_user_features($user);
    $participatedCats = ai_participation_features($userId);

    /* Collect candidates: upcoming events + active/upcoming challenges. */
    $candidates = [];
    $res = $db->query(
        "SELECT id, event_name AS name, category, event_date, status
           FROM community_events
          WHERE status IN ('Upcoming','Ongoing')
          ORDER BY event_date ASC LIMIT 40"
    );
    while ($ev = $res->fetch_assoc()) {
        $candidates[] = ['type' => 'event', 'row' => $ev];
    }
    $res = $db->query(
        "SELECT id, challenge_name AS name, category, start_date, status
           FROM fitness_challenges
          WHERE status IN ('Active','Upcoming')
          ORDER BY start_date ASC LIMIT 40"
    );
    while ($ch = $res->fetch_assoc()) {
        $candidates[] = ['type' => 'challenge', 'row' => $ch];
    }

    /* Skip items the user is already registered for / joined. */
    $alreadyEvent = [];
    $stmt = $db->prepare("SELECT event_id FROM event_registrations WHERE community_user_id = ? AND status <> 'Cancelled'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $alreadyEvent[] = (int)$r['event_id'];
    $stmt->close();

    $alreadyChal = [];
    $stmt = $db->prepare("SELECT challenge_id FROM challenge_participants WHERE community_user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $alreadyChal[] = (int)$r['challenge_id'];
    $stmt->close();

    $recs = [];
    foreach ($candidates as $c) {
        $row = $c['row'];
        if ($c['type'] === 'event' && in_array((int)$row['id'], $alreadyEvent, true)) continue;
        if ($c['type'] === 'challenge' && in_array((int)$row['id'], $alreadyChal, true)) continue;

        [$score, $reason] = ai_score_item($features, $row, $c['type'], $participatedCats);
        if ($score < 25) continue;   // don't recommend poor matches

        $recs[] = [
            'type'         => $c['type'],
            'item'         => $row,
            'score'        => $score,
            'reason'       => $reason,
            'event_id'     => $c['type'] === 'event' ? (int)$row['id'] : null,
            'challenge_id' => $c['type'] === 'challenge' ? (int)$row['id'] : null,
        ];
    }

    usort($recs, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_slice($recs, 0, $limit);
}

/**
 * Persist recommendations so they are auditable (table ai_recommendations).
 */
function ai_log_recommendations($userId, array $recs) {
    if (!$recs) return;
    $db = db();
    $db->query("DELETE FROM ai_recommendations WHERE community_user_id = " . (int)$userId);
    $stmt = $db->prepare(
        "INSERT INTO ai_recommendations
           (community_user_id, event_id, challenge_id, reason, score, engine_version)
         VALUES (?,?,?,?,?,'" . AI_ENGINE_VERSION . "')"
    );
    foreach ($recs as $r) {
        $eid = $r['event_id'];
        $cid = $r['challenge_id'];
        $stmt->bind_param('iissd', $userId, $eid, $cid, $r['reason'], $r['score']);
        $stmt->execute();
    }
    $stmt->close();
}
