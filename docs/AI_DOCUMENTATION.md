# 📄 AI Documentation — New Life Fitness Community Engagement Project (CEP)

> **Read this first — what the "AI" in this project is and is not.**

---

## 1. Honest summary (the one-paragraph version)

This project contains **two "AI-flavoured" features**. **Neither is a trained
machine-learning model**. Both are **transparent, deterministic prototypes**
built with classic rule-based techniques, chosen deliberately because a new
community platform has **no historical data to train a legitimate model on**.
Every claim in this document, in the UI, and in the code comments is written so
that a reviewer can verify exactly what each feature does — the same input
always produces the same output, and the reasons behind every recommendation
and classification are recorded and displayed to the user.

| Feature | Module file | Technique | Where you see it |
|---|---|---|---|
| Personalised recommendations | `includes/ai_recommendation.php` | Weighted rule-based scoring (`rule-v1`) | Community dashboard ("Recommended for you") |
| Feedback sentiment + topic analysis | `includes/ai_feedback.php` | Lexicon-based sentiment + keyword topics | Event feedback → admin analytics, impact dashboard |

**Labelling in the UI:** the dashboard recommendations section carries a
"Rule-Based Recommendation Prototype" label, and the feedback confirmation
message and admin pages describe the sentiment as "auto-classified by our
feedback analyser (prototype)". No claim of neural networks, deep learning, or
trained ML is made anywhere in the interface.

---

## 2. Why not a trained ML model? (the engineering justification)

A legitimate ML system needs data. For a recommender, that means hundreds to
thousands of historical participation decisions (who registered for what, who
attended, who cancelled) to learn from. A newly launched community platform
starts with **zero rows** of real participation history.

Training a model on the seed/demo data shipped with this project would produce
a statistically meaningless artefact — and **presenting it as a working ML
system would be dishonest**. The project brief explicitly required an honest
"AI" feature, so the engineering decision was:

1. **Build the feature correctly for the current stage** — a deterministic,
   explainable rule-based engine that works on day one, gives useful output,
   and fails transparently.
2. **Design the data layer for the future upgrade** — the feature vectors
   extracted today (user profile features, participation matrix) are exactly
   the inputs a supervised model would need later. The upgrade path is
   documented in §6.

This is the same reasoning used by real production teams: cold-start systems
begin with rule-based or popularity-based recommenders and graduate to learned
models as data accumulates.

---

## 3. Feature 1 — The Rule-Based Recommendation Prototype

### 3.1 Inputs (the user's feature vector)

For each logged-in community user, `ai_user_features()` extracts:

| Feature | Source column | Example |
|---|---|---|
| `fitness_level` | `community_users.fitness_level` | Beginner / Intermediate / Advanced |
| `fitness_goal` | `community_users.fitness_goal` | Weight Loss / Wellness / Strength / … |
| `age` | `community_users.age` | 34 |
| `preferences` | `community_users.preferred_activities` (CSV) | ["Yoga", "Walking"] |
| Participation history | `event_registrations` + `challenge_participants` | categories already attended |

### 3.2 Candidates

Every scoring run pulls **upcoming events** (status `Upcoming`) and **active
challenges** as candidates, then scores each one independently.

### 3.3 Scoring formula (weights are domain knowledge, and are documented)

```
score =  category-match    (0–40)   keyword match: preferred activity ↔ item category
       + level-match       (0–20)   intensity of category vs user fitness level
       + goal-match        (0–20)   goal → recommended categories mapping
       + age-suitability   (0–10)   senior programs for 55+, high-energy for ≤25, etc.
       + variety bonus     (0–10)   categories the user has NOT already tried
       ───────────────────────────
       capped to 0–100
```

Each component contributes an explainable reason fragment, e.g. *"matches your
"Yoga" interest; suitable for beginner level"*. The final reason string shown
to the user is assembled from the matched fragments. A higher weight signals a
stronger signal — preference (40) matters more than variety (10).

### 3.4 Goal map and intensity map (the domain knowledge tables)

`ai_goal_categories()` maps each fitness goal to recommended categories —
e.g. Weight Loss → Zumba / Running / Walking / Fitness Camp; Wellness → Yoga /
Wellness / Nutrition Workshop / Health Awareness / Women Wellness.

`ai_category_intensity()` assigns each of the 12 event categories a typical
intensity (Yoga/Walking/Senior Fitness/Wellness = low; Zumba/Fitness Camp/
Community Challenge = medium; Running = high). These tables are the "trainer
knowledge" encoded in code.

### 3.5 Auditability

Every recommendation set is logged to the `ai_recommendations` table with the
`reason`, `score`, and an `engine_version` tag (`rule-v1`). When the engine is
upgraded, old logged rows keep their version tag, so the history of what was
recommended — and why — remains auditable.

### 3.6 Determinism

For the same user profile and the same candidate set, the engine always
produces the same scores in the same order. There is no randomness, no
"temperature", no hidden state. This is a deliberate property: it makes the
feature testable and the output defensible in a viva/demo.

---

## 4. Feature 2 — Lexicon-Based Feedback Analysis Prototype

### 4.1 Pipeline

When a user submits event feedback (`community/event-feedback.php`), the text
(comments + suggestions) is analysed in the same request:

1. **Tokenise** — split on whitespace/punctuation, lowercase (mb_-safe).
2. **Lexicon lookup** — match against a domain-tuned opinion dictionary
   (positive ~40 terms, negative ~40 terms, e.g. *wonderful, energetic,*
   *friendly* ↔ *crowded, late, unhelpful*), tuned for fitness/community-event
   vocabulary.
3. **Negation handling** — a matched term is flipped when preceded by a
   negator (*not good*, *wasn't fun*). "not bad" counts as a weak positive.
4. **Intensifiers** — *very/really/extremely/absolutely …* multiply the matched
   term's weight by 1.5.
5. **Decision** — with a 1.0 neutral buffer: `Positive` if pos > neg + 0.99,
   `Negative` if neg > pos + 0.99, else `Neutral`.
6. **Topics** — six topic buckets (Timing, Location, Trainer, Facilities,
   Activity Quality, Program Preference) detected by keyword sets; all matches
   are stored as a comma-separated list on the feedback row.

### 4.2 Storage

`community_feedback.sentiment` (Positive/Neutral/Negative) and
`community_feedback.topics` (CSV). These power:

- the **admin feedback view** (`admin/community/feedback.php`) — sentiment
  distribution and topic trends per event,
- the **community impact dashboard** (`community/impact.php`) — sentiment chart,
- the **feedback confirmation message** the user sees after submitting.

### 4.3 Determinism and explainability

Same text → same sentiment + topics, every time. The `detail` array from
`ai_analyze_feedback()` records exactly which dictionary words were matched
(and which were negated), so any classification can be traced back to the
triggering words. No scores are hidden from the reviewer.

### 4.4 Known limitations (honest list)

- Purely lexical — **sarcasm/irony is misread** ("great, another cancelled
  session" scores positive on "great").
- **No aspect-level sentiment** — "trainer was great but venue was dusty"
  yields one overall label, not per-aspect scores (topics partially cover this).
- English-only lexicon; **mixed-language comments** (e.g. Hindi-English common
  in Indian communities) are under-detected.
- Multi-word phrase coverage is limited to the curated list; unusual synonyms
  ("the session was a banger") are missed.
- The neutral buffer (±1.0) is a heuristic, not learned from data.
- Topic detection is first-keyword-hit per bucket — it says *that* a topic was
  mentioned, not *how positively*.

---

## 5. How to verify these claims (for the evaluator)

1. Open `includes/ai_recommendation.php` and `includes/ai_feedback.php` — the
   disclosure headers are the first thing in each file.
2. On the community dashboard, the recommendation cards each show **score + reason**.
3. Submit the same feedback text twice (two events) — the sentiment is identical.
4. Check `admin/community/feedback.php` — sentiment distribution chart + the
   raw matched-word details for any row.
5. Query the audit trail: `SELECT user_id, item_type, item_id, score, reason,
   engine_version, created_at FROM ai_recommendations ORDER BY created_at DESC;`
6. The E2E test (`tests/e2e_demo.sh` step 10) asserts the classifier labels an
   obviously-positive comment as **Positive** and extracts its topics —
   the pipeline is verifiable in a one-command run.

---

## 6. Upgrade path to genuine ML (documented, not implemented)

Both modules were structured so an ML upgrade is a **drop-in replacement** of
one scoring/classification function — no app re-architecture:

| Stage | Trigger | Action |
|---|---|---|
| Now | 0 real participation rows | Rule-based prototype (this implementation) |
| Stage 1 | ≥ ~1,000 real event registrations | Train a pointwise relevance classifier (logistic regression / gradient-boosted trees) on the same feature vectors; A/B against `rule-v1` scores |
| Stage 2 | ≥ ~5,000 rows + repeat users | Item/content embeddings + collaborative filtering (users × categories matrix) |
| Stage 3 | Real feedback corpus ≥ ~2,000 comments | Swap the lexicon analyser for a fine-tuned small transformer classifier; keep the lexicon as a fallback for out-of-vocabulary text |

Feature extraction (`ai_user_features`, `ai_participation_features`) already
returns exactly the structure a training pipeline would consume, and the
`engine_version` column keeps the audit trail intact across the switch.

**What would be dishonest:** training on the demo seed rows and calling the
result machine learning. This project does not do that.

---

## 7. Ethics & transparency statement

The "AI" features in this CEP are labelled prototypes wherever they appear.
They make no automated decisions affecting users (no access gating, no scoring
of people), only informational output (recommendations and aggregate
sentiment). Sentiment labels are suggestions for organisers, not judgements of
individuals. All processing is local to the PHP application — **no external AI
APIs are called, and no user text leaves the server**.

---

*Engine versions documented here: `rule-v1` (recommendations), lexicon
prototype `v1` (feedback analysis). Files: `includes/ai_recommendation.php`,
`includes/ai_feedback.php`. Test coverage: `tests/e2e_demo.sh` (step 10) and
`tests/smoke_community.sh`.*
