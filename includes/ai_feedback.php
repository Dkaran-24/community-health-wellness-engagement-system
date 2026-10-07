<?php
/**
 * includes/ai_feedback.php — Community Feedback Analysis (CEP)
 * =====================================================================
 * HONEST AI DISCLOSURE:
 * ---------------------------------------------------------------------
 * This is a **LEXICON-BASED SENTIMENT & TOPIC ANALYSIS PROTOTYPE**, not a
 * trained NLP machine-learning model.
 *
 * It classifies each feedback comment as Positive / Neutral / Negative
 * using a curated dictionary of opinion words (with negation handling),
 * and detects discussion topics (timing, location, trainer, facilities,
 * activity quality, program preference) using keyword matching.
 *
 * It is deterministic, explainable and auditable: the same text always
 * produces the same classification, and the matched words are recorded.
 * See docs/AI_DOCUMENTATION.md for the full methodology, limitations and
 * the upgrade path to a trained ML classifier once real feedback volume
 * is sufficient.
 */

/**
 * Lexicons (domain-tuned: fitness / community events vocabulary).
 */
function ai_feedback_lexicons() {
    return [
        'positive' => [
            'wonderful','great','good','excellent','amazing','awesome','loved','love','best',
            'fantastic','enjoyed','helpful','useful','grateful','thank','thanks','perfect',
            'energetic','patient','friendly','fun','fresh','peaceful','motivating','inspiring',
            'well organised','well organized','well-organized','well-organized','comfortable',
            'clean','smooth','super','nice','brilliant','delightful','satisfying','worth',
            'recommend','appreciate','appreciated','professional','calm','relaxing','refreshing',
        ],
        'negative' => [
            'bad','poor','terrible','awful','worst','hate','disappointed','disappointing',
            'boring','waste','late','crowded','dusty','dirty','noisy','loud','unclear',
            'confusing','rude','unhelpful','uncomfortable','difficult','hard to','too long',
            'long queue','waiting queue','painful','cancelled without','poorly','issue',
            'problem','lacked','missing','not enough','cramped','hot','no water','no parking',
        ],
        'negators' => ['not','no','never','hardly','barely','wasn\'t','isn\'t','didn\'t','don\'t','cannot','can\'t'],
        'intensifiers' => ['very','really','so','extremely','totally','absolutely','super'],
    ];
}

/**
 * Topic keyword map — which words indicate which discussion topic.
 */
function ai_feedback_topics() {
    return [
        'Timing'            => ['timing','time','morning','evening','late','early','hour','schedule','sunday','saturday','weekday','weekend','pm','am','duration'],
        'Location'          => ['location','place','venue','ground','park','hall','far','distance','reach','route','area','sector'],
        'Trainer'           => ['trainer','instructor','coach','teacher','guide','marshal','organiser','organizer','staff','volunteer','doctor','nutritionist'],
        'Facilities'        => ['facilities','mat','mats','equipment','water','parking','toilet','washroom','sound','music','mic','speaker','seating','chair','queue','crowd'],
        'Activity Quality'  => ['session','activity','exercise','workout','yoga','zumba','walking','running','camp','workshop','quality','content','routine','program'],
        'Program Preference'=> ['more','next','please','wish','want','request','suggest','future','again','weekly','monthly','regular','prefer','would love','looking forward'],
    ];
}

/**
 * Classify one feedback text.
 * Returns [sentiment (Positive|Neutral|Negative), topicsCSV, detail]
 */
function ai_analyze_feedback($text) {
    $text = trim((string)$text);
    if ($text === '') return ['Neutral', '', ['note' => 'empty comment']];

    $lex = ai_feedback_lexicons();
    $words = preg_split('/[\s,.!?;:()\/]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $posHits = 0; $negHits = 0;
    $matched = [];

    /* Normalise the text once for phrase matching. */
    $low = ' ' . mb_strtolower($text) . ' ';

    foreach (['positive' => 1, 'negative' => -1] as $kind => $sign) {
        foreach ($lex[$kind] as $term) {
            if (mb_strpos($low, ' ' . $term . ' ') !== false) {
                /* Check the previous word for a negator ("not good"). */
                $negated = false;
                foreach ($lex['negators'] as $neg) {
                    if (mb_strpos($low, ' ' . $neg . ' ' . $term . ' ') !== false
                        || mb_strpos($low, ' ' . $neg . ' ' . $term) !== false) {
                        $negated = true; break;
                    }
                }
                /* Check for an intensifier ("very good"). */
                $amp = 1.0;
                foreach ($lex['intensifiers'] as $int) {
                    if (mb_strpos($low, ' ' . $int . ' ' . $term . ' ') !== false) { $amp = 1.5; break; }
                }
                if ($negated) {
                    /* "not good" flips polarity; "not bad" flips to mild positive */
                    if ($sign === 1)  { $negHits += 0; $matched[] = 'negated:' . $term; }
                    else              { $posHits += 0; $matched[] = 'negated:' . $term; }
                    /* Negation of a negative is neutral-ish; keep it simple:
                       treat "not bad" as weak positive, "not good" as weak negative */
                    if ($sign === 1)  { $negHits += 1; }
                    else              { $posHits += 0.5; }
                } else {
                    if ($sign === 1)  { $posHits += $amp; }
                    else              { $negHits += $amp; }
                    $matched[] = $term;
                }
            }
        }
    }

    /* Sentiment decision with a small buffer zone for Neutral. */
    if ($posHits > $negHits + 0.99)      $sentiment = 'Positive';
    elseif ($negHits > $posHits + 0.99)  $sentiment = 'Negative';
    else                                  $sentiment = 'Neutral';

    /* Topic detection. */
    $topics = [];
    foreach (ai_feedback_topics() as $topic => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($low, $kw) !== false) { $topics[] = $topic; break; }
        }
    }
    $topics = array_values(array_unique($topics));

    return [
        $sentiment,
        implode(',', array_map('strtolower', $topics)),
        ['pos' => $posHits, 'neg' => $negHits, 'matched' => array_unique($matched)],
    ];
}

/**
 * Analyse + store sentiment & topics for one feedback row.
 * Returns the computed sentiment.
 */
function ai_apply_feedback_analysis($feedbackId) {
    $db = db();
    $stmt = $db->prepare("SELECT id, comments, suggestions FROM community_feedback WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $feedbackId);
    $stmt->execute();
    $res = $stmt->get_result();
    $fb = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$fb) return null;

    $text = trim(($fb['comments'] ?? '') . ' ' . ($fb['suggestions'] ?? ''));
    [$sentiment, $topics, ] = ai_analyze_feedback($text);

    $stmt = $db->prepare("UPDATE community_feedback SET sentiment = ?, topics = ? WHERE id = ?");
    $stmt->bind_param('ssi', $sentiment, $topics, $feedbackId);
    $stmt->execute();
    $stmt->close();
    return $sentiment;
}

/**
 * Aggregate feedback analysis for an event (or all events if null).
 * Returns [avgRating, counts, percentages, topTopics]
 */
function ai_feedback_summary($eventId = null) {
    $db = db();
    $where = $eventId ? "WHERE event_id = " . (int)$eventId : '';
    $res = $db->query("SELECT sentiment, topics, rating FROM community_feedback $where");

    $counts = ['Positive' => 0, 'Neutral' => 0, 'Negative' => 0];
    $topicCount = [];
    $ratingSum = 0; $n = 0;
    while ($r = $res->fetch_assoc()) {
        $n++;
        $ratingSum += (int)$r['rating'];
        $s = $r['sentiment'] ?: 'Neutral';
        if (isset($counts[$s])) $counts[$s]++;
        foreach (array_filter(explode(',', (string)$r['topics'])) as $t) {
            $t = trim($t);
            if ($t !== '') $topicCount[$t] = ($topicCount[$t] ?? 0) + 1;
        }
    }
    arsort($topicCount);
    $pct = [];
    foreach ($counts as $k => $v) $pct[$k] = $n ? round($v * 100 / $n, 1) : 0.0;
    return [
        'count'      => $n,
        'avg_rating' => $n ? round($ratingSum / $n, 2) : 0,
        'counts'     => $counts,
        'percent'    => $pct,
        'topics'     => $topicCount,
    ];
}
