<?php
/**
 * admin/community/users.php
 *
 * Community user management - card based UI.
 *
 * Features:
 * - Search community users
 * - View engagement statistics
 * - Volunteer + account status always visible
 * - Edit profile and password via user_edit.php
 * - Block / unblock users
 * - Delete users
 */
$PAGE_TITLE = 'Community Users';
$PAGE_KEY   = 'community-users';

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../db_connect.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/community_auth.php';

require_login();
$db = db();

/* --------------------------------------------------------------- */
/* POST ACTIONS                                                    */
/* --------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = trim($_POST['action'] ?? '');
    $uid    = (int)($_POST['user_id'] ?? 0);

    if ($uid <= 0) {
        header('Location: users.php?err=' . urlencode('Invalid community user ID.'));
        exit;
    }

    $stmt = $db->prepare('SELECT id, full_name FROM community_users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$target) {
        header('Location: users.php?err=' . urlencode('Community user not found.'));
        exit;
    }

    if ($action === 'toggle') {
        $stmt = $db->prepare(
            "UPDATE community_users
             SET status = IF(status = 'Active', 'Blocked', 'Active')
             WHERE id = ?"
        );
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();

        header('Location: users.php?ok=' . urlencode('Account status updated for "' . $target['full_name'] . '".'));
        exit;
    }

    if ($action === 'delete') {
        try {
            $stmt = $db->prepare('DELETE FROM community_users WHERE id = ?');
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();

            header('Location: users.php?ok=' . urlencode('Community user "' . $target['full_name'] . '" deleted successfully.'));
            exit;
        } catch (mysqli_sql_exception $ex) {
            header('Location: users.php?err=' . urlencode('Unable to delete this user because related records prevented the deletion.'));
            exit;
        }
    }

    header('Location: users.php?err=' . urlencode('Unknown action.'));
    exit;
}

/* --------------------------------------------------------------- */
/* SEARCH / LIST QUERY                                             */
/* --------------------------------------------------------------- */
$q = trim($_GET['q'] ?? '');

$sql = "
    SELECT
        u.id,
        u.full_name,
        u.email,
        u.mobile,
        u.age,
        u.gender,
        u.address_area,
        u.fitness_level,
        u.fitness_goal,
        u.preferred_activities,
        u.status,
        u.created_at,

        (SELECT COUNT(*)
         FROM event_registrations r
         WHERE r.community_user_id = u.id
           AND r.status IN ('Registered', 'Attended')) AS registrations,

        (SELECT COUNT(*)
         FROM event_attendance a
         WHERE a.community_user_id = u.id
           AND a.status = 'Present') AS attendance_count,

        (SELECT COUNT(*)
         FROM community_feedback f
         WHERE f.community_user_id = u.id) AS feedback_count,

        (SELECT COUNT(*)
         FROM community_requests cr
         WHERE cr.community_user_id = u.id) AS request_count,

        (SELECT COUNT(*)
         FROM challenge_participants cp
         WHERE cp.community_user_id = u.id) AS challenge_count,

        CASE
            WHEN EXISTS (
                SELECT 1
                FROM volunteers v
                WHERE v.community_user_id = u.id
                  AND v.status = 'Active'
            ) THEN 1 ELSE 0
        END AS is_volunteer

    FROM community_users u
";

if ($q !== '') {
    $like = '%' . $q . '%';
    $sql .= "
        WHERE u.full_name LIKE ?
           OR u.email LIKE ?
           OR u.mobile LIKE ?
           OR u.address_area LIKE ?
        ORDER BY u.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $users = $stmt->get_result();
    $stmt->close();
} else {
    $sql .= ' ORDER BY u.created_at DESC';
    $users = $db->query($sql);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
/* ================================================================
   COMMUNITY USERS - MODERN CARD UI
   ================================================================ */
.community-users-page {
    --cu-ink: #15382d;
    --cu-ink-2: #31584a;
    --cu-muted: #70857c;
    --cu-green: #135b47;
    --cu-green-dark: #0d4636;
    --cu-gold: #d49a22;
    --cu-soft: #edf7f2;
    --cu-soft-2: #f7fbf9;
    --cu-line: #dce8e2;
    --cu-danger: #a8352d;
    --cu-danger-bg: #fff1ef;
    --cu-shadow: 0 12px 32px rgba(21,56,45,.07);
}

.community-users-page,
.community-users-page * { box-sizing:border-box; }

.cu-hero {
    position:relative;
    overflow:hidden;
    border-radius:22px;
    padding:26px 28px;
    margin-bottom:18px;
    color:#fff;
    background:linear-gradient(135deg,#0c3429 0%,#145d49 58%,#2b8167 100%);
    box-shadow:0 16px 38px rgba(12,52,41,.18);
}

.cu-hero::after {
    content:"";
    position:absolute;
    width:190px;
    height:190px;
    right:-55px;
    top:-70px;
    border-radius:50%;
    background:rgba(255,255,255,.08);
}

.cu-hero-top {
    position:relative;
    z-index:1;
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
}

.cu-eyebrow {
    display:inline-block;
    margin-bottom:7px;
    font-size:10px;
    font-weight:900;
    letter-spacing:.16em;
    text-transform:uppercase;
    color:rgba(255,255,255,.74);
}

.cu-hero h1 {
    margin:0;
    color:#fff;
    font-size:29px;
    line-height:1.1;
}

.cu-hero p {
    max-width:780px;
    margin:9px 0 0;
    color:rgba(255,255,255,.86);
    font-size:13px;
    line-height:1.55;
}

.cu-pill {
    flex:0 0 auto;
    padding:9px 13px;
    border:1px solid rgba(255,255,255,.18);
    border-radius:999px;
    background:rgba(255,255,255,.10);
    font-size:12px;
    font-weight:800;
    white-space:nowrap;
}

.cu-toolbar {
    display:flex;
    justify-content:space-between;
    gap:14px;
    align-items:center;
    margin-bottom:16px;
}

.cu-search-form {
    width:min(720px,100%);
    display:flex;
    gap:8px;
}

.cu-search-wrap { position:relative; flex:1; }

.cu-search-icon {
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    color:#8aa097;
    pointer-events:none;
}

.cu-search {
    width:100%;
    padding:12px 13px 12px 36px;
    border:1px solid #cadad2;
    border-radius:12px;
    background:#fff;
    color:var(--cu-ink);
    outline:none;
}

.cu-search:focus {
    border-color:#4c9078;
    box-shadow:0 0 0 3px rgba(76,144,120,.12);
}

.cu-btn {
    min-height:42px;
    padding:0 15px;
    border-radius:11px;
    border:1px solid transparent;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    text-decoration:none;
    font:800 12px/1 Arial,sans-serif;
    cursor:pointer;
    transition:all .15s ease;
}

.cu-btn-primary { background:var(--cu-green); color:#fff; border-color:var(--cu-green); }
.cu-btn-primary:hover { background:var(--cu-green-dark); }
.cu-btn-light { background:#fff; color:var(--cu-ink-2); border-color:var(--cu-line); }
.cu-btn-light:hover { background:var(--cu-soft-2); }

.cu-clear { white-space:nowrap; }

.cu-grid {
    display:grid;
    gap:14px;
}

.cu-user-card {
    background:#fff;
    border:1px solid var(--cu-line);
    border-radius:18px;
    overflow:hidden;
    box-shadow:var(--cu-shadow);
}

.cu-user-top {
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:16px;
    padding:18px 19px 15px;
    background:linear-gradient(180deg,#fbfdfc,#f7faf8);
    border-bottom:1px solid var(--cu-line);
}

.cu-user-main {
    min-width:0;
    display:flex;
    gap:12px;
    align-items:center;
}

.cu-avatar {
    width:46px;
    height:46px;
    flex:0 0 46px;
    display:grid;
    place-items:center;
    border-radius:14px;
    background:var(--cu-soft);
    color:var(--cu-green);
    font-size:16px;
    font-weight:900;
}

.cu-user-name {
    color:var(--cu-ink);
    font-size:16px;
    font-weight:900;
}

.cu-user-email {
    margin-top:3px;
    color:var(--cu-muted);
    font-size:11.5px;
    overflow-wrap:anywhere;
}

.cu-user-statuses {
    display:flex;
    flex-wrap:wrap;
    justify-content:flex-end;
    gap:7px;
}

.cu-badge {
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:6px 9px;
    border-radius:999px;
    border:1px solid transparent;
    font-size:10px;
    font-weight:900;
    white-space:nowrap;
}

.cu-badge-green { background:#ecf8f0; color:#1f7244; border-color:#cfe7d8; }
.cu-badge-gray { background:#f1f5f3; color:#65756e; border-color:#dce5e0; }
.cu-badge-red { background:#fff0ef; color:#a12f27; border-color:#edcfca; }
.cu-badge-gold { background:#fff7df; color:#936915; border-color:#eedca8; }

.cu-body { padding:16px 19px 18px; }

.cu-info-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
}

.cu-info {
    padding:11px 12px;
    border:1px solid #e3ece7;
    border-radius:12px;
    background:#fcfefd;
    min-height:74px;
}

.cu-info-label {
    color:#7a8b84;
    font-size:9px;
    font-weight:900;
    letter-spacing:.08em;
    text-transform:uppercase;
}

.cu-info-value {
    margin-top:5px;
    color:var(--cu-ink);
    font-size:12px;
    font-weight:800;
    line-height:1.4;
    overflow-wrap:anywhere;
}

.cu-engagement {
    margin-top:12px;
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:8px;
}

.cu-stat {
    padding:10px;
    text-align:center;
    border-radius:12px;
    background:var(--cu-soft-2);
    border:1px solid #e1ebe6;
}

.cu-stat-number {
    display:block;
    color:var(--cu-ink);
    font-size:17px;
    font-weight:900;
}

.cu-stat-label {
    display:block;
    margin-top:3px;
    color:#7b8c85;
    font-size:9px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.05em;
}

.cu-actions {
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:14px;
    padding-top:14px;
    border-top:1px solid #e7eee9;
}

.cu-action {
    min-height:38px;
    padding:0 13px;
    border-radius:10px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    text-decoration:none;
    font:800 11.5px/1 Arial,sans-serif;
    border:1px solid transparent;
    cursor:pointer;
}

.cu-action-edit { background:#143f31; color:#fff; }
.cu-action-edit:hover { background:#0d3026; }
.cu-action-block { background:#fff7df; color:#916613; border-color:#ead89d; }
.cu-action-delete { background:var(--cu-danger-bg); color:var(--cu-danger); border-color:#edc9c5; }

.cu-action form { margin:0; }

.cu-footer-note {
    margin-top:13px;
    color:#7b8c85;
    font-size:11px;
}

.cu-empty {
    padding:54px 20px;
    text-align:center;
    border:1px dashed #cddbd4;
    border-radius:18px;
    background:#fbfdfc;
    color:var(--cu-muted);
}

.cu-empty-icon {
    width:50px;
    height:50px;
    margin:0 auto 10px;
    display:grid;
    place-items:center;
    border-radius:15px;
    background:var(--cu-soft);
    color:var(--cu-green);
    font-size:20px;
}

@media (max-width: 950px) {
    .cu-info-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .cu-engagement { grid-template-columns:repeat(3,minmax(0,1fr)); }
}

@media (max-width: 680px) {
    .cu-hero-top,
    .cu-toolbar,
    .cu-user-top { flex-direction:column; align-items:stretch; }
    .cu-pill { width:max-content; }
    .cu-search-form { width:100%; }
    .cu-user-statuses { justify-content:flex-start; }
    .cu-info-grid { grid-template-columns:1fr; }
    .cu-engagement { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .cu-actions > * { flex:1 1 100%; }
    .cu-action,
    .cu-action form { width:100%; }
    .cu-action form .cu-action { width:100%; }
}
</style>

<main class="community-users-page" aria-labelledby="page-title">
    <section class="cu-hero">
        <div class="cu-hero-top">
            <div>
                <div class="cu-eyebrow">Community Administration</div>
                <h1 id="page-title">Community Users</h1>
                <p>
                    Manage local resident accounts, view engagement activity, check volunteer status,
                    and open a dedicated profile to update personal details or reset a login password.
                </p>
            </div>
            <div class="cu-pill"><?= (int)$users->num_rows ?> user(s) shown</div>
        </div>
    </section>

    <?= flash() ?>

    <div class="cu-toolbar">
        <form method="get" class="cu-search-form" role="search">
            <div class="cu-search-wrap">
                <span class="cu-search-icon" aria-hidden="true">🔎</span>
                <input
                    class="cu-search"
                    type="search"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Search by name, email, mobile or area..."
                    autocomplete="off"
                    aria-label="Search community users"
                >
            </div>
            <button class="cu-btn cu-btn-primary" type="submit">Search</button>
            <?php if ($q !== ''): ?>
                <a class="cu-btn cu-btn-light cu-clear" href="users.php">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <section class="cu-grid" aria-label="Community user list">
        <?php if ($users->num_rows === 0): ?>
            <div class="cu-empty">
                <div class="cu-empty-icon">⌕</div>
                <strong>No community users found</strong>
                <div style="margin-top:5px;">Try a different name, email, mobile number or area.</div>
            </div>
        <?php endif; ?>

        <?php while ($u = $users->fetch_assoc()): ?>
            <?php
                $initial = strtoupper(mb_substr(trim((string)$u['full_name']), 0, 1));
                $statusActive = ((string)$u['status'] === 'Active');
                $volunteerActive = ((int)$u['is_volunteer'] === 1);
            ?>

            <article class="cu-user-card">
                <header class="cu-user-top">
                    <div class="cu-user-main">
                        <div class="cu-avatar" aria-hidden="true"><?= e($initial ?: '?') ?></div>
                        <div>
                            <div class="cu-user-name"><?= e($u['full_name']) ?></div>
                            <div class="cu-user-email"><?= e($u['email']) ?></div>
                        </div>
                    </div>

                    <div class="cu-user-statuses">
                        <?php if ($volunteerActive): ?>
                            <span class="cu-badge cu-badge-green">● Volunteer</span>
                        <?php else: ?>
                            <span class="cu-badge cu-badge-gray">— No Volunteer Role</span>
                        <?php endif; ?>

                        <?php if ($statusActive): ?>
                            <span class="cu-badge cu-badge-green">● Active</span>
                        <?php else: ?>
                            <span class="cu-badge cu-badge-red">● Blocked</span>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="cu-body">
                    <div class="cu-info-grid">
                        <div class="cu-info">
                            <div class="cu-info-label">Contact</div>
                            <div class="cu-info-value"><?= e($u['mobile'] ?: 'Not provided') ?></div>
                        </div>
                        <div class="cu-info">
                            <div class="cu-info-label">Location</div>
                            <div class="cu-info-value"><?= e($u['address_area'] ?: 'Not provided') ?></div>
                        </div>
                        <div class="cu-info">
                            <div class="cu-info-label">Profile</div>
                            <div class="cu-info-value">
                                <?= e($u['age']) ?> yrs · <?= e($u['gender']) ?><br>
                                <?= e($u['fitness_level']) ?> · <?= e($u['fitness_goal']) ?>
                            </div>
                        </div>
                        <div class="cu-info">
                            <div class="cu-info-label">Interests</div>
                            <div class="cu-info-value"><?= e($u['preferred_activities'] ?: 'Not provided') ?></div>
                        </div>
                    </div>

                    <div class="cu-engagement" aria-label="Engagement statistics">
                        <div class="cu-stat">
                            <span class="cu-stat-number"><?= (int)$u['registrations'] ?></span>
                            <span class="cu-stat-label">Events</span>
                        </div>
                        <div class="cu-stat">
                            <span class="cu-stat-number"><?= (int)$u['attendance_count'] ?></span>
                            <span class="cu-stat-label">Attendance</span>
                        </div>
                        <div class="cu-stat">
                            <span class="cu-stat-number"><?= (int)$u['challenge_count'] ?></span>
                            <span class="cu-stat-label">Challenges</span>
                        </div>
                        <div class="cu-stat">
                            <span class="cu-stat-number"><?= (int)$u['feedback_count'] ?></span>
                            <span class="cu-stat-label">Feedback</span>
                        </div>
                        <div class="cu-stat">
                            <span class="cu-stat-number"><?= (int)$u['request_count'] ?></span>
                            <span class="cu-stat-label">Requests</span>
                        </div>
                    </div>

                    <div class="cu-actions">
                        <a
                            href="user_edit.php?id=<?= (int)$u['id'] ?>"
                            class="cu-action cu-action-edit"
                        >
                            ✎ Edit Profile
                        </a>

                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button class="cu-action cu-action-block" type="submit">
                                <?= $statusActive ? '⛔ Block Account' : '✓ Unblock Account' ?>
                            </button>
                        </form>

                        <form
                            method="post"
                            data-confirm="Delete community user <?= e($u['full_name']) ?>? Their related community records may also be deleted by database cascade rules."
                        >
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <button class="cu-action cu-action-delete" type="submit">
                                🗑 Delete User
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endwhile; ?>
    </section>

    <div class="cu-footer-note">
        <b>Edit Profile</b> opens the dedicated user editor where personal details and the login password can be changed.
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
