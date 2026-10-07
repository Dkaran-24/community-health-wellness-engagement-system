<?php
/**
 * admin/community/events.php — Community Events CRUD
 * Clean replacement for the broken/duplicated version.
 */

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$PAGE_TITLE = 'Community Events';
$PAGE_KEY   = 'community-events';

require_once __DIR__ . '/../../includes/header.php';

$db = db();

$CATS = [
    'Fitness Camp',
    'Yoga',
    'Zumba',
    'Walking',
    'Running',
    'Health Awareness',
    'Nutrition Workshop',
    'Wellness',
    'Senior Fitness',
    'Women Wellness',
    'Community Challenge',
    'Other'
];

$STATUSES = ['Upcoming', 'Ongoing', 'Completed', 'Cancelled'];

/** Build a safe events.php URL for filters. */
function event_filter_url(string $cat = '', string $status = ''): string
{
    $params = [];

    if ($cat !== '') {
        $params['cat'] = $cat;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

    return 'events.php' . ($params ? '?' . http_build_query($params) : '');
}

/** Redirect back to events page with a flash message. */
function event_redirect(string $type, string $message): void
{
    header('Location: events.php?' . $type . '=' . urlencode($message));
    exit;
}

/* ------------------------------------------------------------------ */
/* POST actions                                                       */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = $_POST['action'] ?? '';

    if ($action === 'set_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';

        if ($id < 1 || !in_array($status, $STATUSES, true)) {
            event_redirect('err', 'Invalid status change request.');
        }

        $stmt = $db->prepare('UPDATE community_events SET status = ? WHERE id = ?');
        if (!$stmt) {
            event_redirect('err', 'Unable to prepare status update.');
        }

        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();

        event_redirect('ok', "Event #{$id} marked {$status}.");
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id < 1) {
            event_redirect('err', 'Invalid event ID.');
        }

        try {
            $stmt = $db->prepare('DELETE FROM community_events WHERE id = ?');
            if (!$stmt) {
                event_redirect('err', 'Unable to prepare event deletion.');
            }

            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            event_redirect('ok', "Event #{$id} deleted successfully.");
        } catch (mysqli_sql_exception $ex) {
            event_redirect('err', 'Could not delete the event because related records prevent deletion.');
        }
    }

    /* Shared create/update input. */
    $id = (int)($_POST['id'] ?? 0);
    $event_name = trim($_POST['event_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'Other';
    $event_date = trim($_POST['event_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '06:00');
    $end_time = trim($_POST['end_time'] ?? '08:00');
    $location = trim($_POST['location'] ?? '');
    $organizer = trim($_POST['organizer'] ?? '');
    $trainer_id = (int)($_POST['trainer_id'] ?? 0);
    $max_participants = (int)($_POST['max_participants'] ?? 50);
    $reg_deadline = trim($_POST['reg_deadline'] ?? '');
    $status = $_POST['status'] ?? 'Upcoming';

    if ($event_name === '') {
        event_redirect('err', 'Event name is required.');
    }

    if (mb_strlen($event_name) > 160) {
        event_redirect('err', 'Event name cannot exceed 160 characters.');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $event_date)) {
        event_redirect('err', 'A valid event date is required.');
    }

    if ($location === '') {
        event_redirect('err', 'Location is required.');
    }

    if ($organizer === '') {
        event_redirect('err', 'Organizer is required.');
    }

    if (!in_array($category, $CATS, true)) {
        $category = 'Other';
    }

    if (!in_array($status, $STATUSES, true)) {
        $status = 'Upcoming';
    }

    if (!preg_match('/^\d{2}:\d{2}$/', substr($start_time, 0, 5)) ||
        !preg_match('/^\d{2}:\d{2}$/', substr($end_time, 0, 5))) {
        event_redirect('err', 'Valid start and end times are required.');
    }

    $start_time = substr($start_time, 0, 5) . ':00';
    $end_time = substr($end_time, 0, 5) . ':00';

    if ($start_time >= $end_time) {
        event_redirect('err', 'End time must be after start time.');
    }

    if ($max_participants < 1 || $max_participants > 10000) {
        event_redirect('err', 'Maximum participants must be between 1 and 10,000.');
    }

    if ($reg_deadline !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $reg_deadline)) {
        event_redirect('err', 'Registration deadline must be a valid date.');
    }

    if ($reg_deadline !== '' && $reg_deadline > $event_date) {
        event_redirect('err', 'Registration deadline cannot be after the event date.');
    }

    $description = mb_substr($description, 0, 5000);
    $location = mb_substr($location, 0, 200);
    $organizer = mb_substr($organizer, 0, 120);
    $reg_deadline_db = $reg_deadline !== '' ? $reg_deadline : null;
    $trainer_id_db = $trainer_id > 0 ? $trainer_id : null;

    if ($action === 'update') {
        if ($id < 1) {
            event_redirect('err', 'Invalid event ID.');
        }

        $stmt = $db->prepare(
            'UPDATE community_events SET
                event_name = ?,
                description = ?,
                category = ?,
                event_date = ?,
                start_time = ?,
                end_time = ?,
                location = ?,
                organizer = ?,
                trainer_id = ?,
                max_participants = ?,
                reg_deadline = ?,
                status = ?
             WHERE id = ?'
        );

        if (!$stmt) {
            event_redirect('err', 'Unable to prepare event update.');
        }

        $stmt->bind_param(
            'ssssssssiissi',
            $event_name,
            $description,
            $category,
            $event_date,
            $start_time,
            $end_time,
            $location,
            $organizer,
            $trainer_id_db,
            $max_participants,
            $reg_deadline_db,
            $status,
            $id
        );

        $stmt->execute();
        $stmt->close();

        event_redirect('ok', "Event '{$event_name}' updated successfully.");
    }

    /* Correct create branch, using the proper type string. */
    if ($action === 'create') {
        $stmt = $db->prepare(
            'INSERT INTO community_events
            (event_name, description, category, event_date, start_time, end_time,
             location, organizer, trainer_id, max_participants, reg_deadline, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            event_redirect('err', 'Unable to prepare event creation.');
        }

        $stmt->bind_param(
            'ssssssssiiss',
            $event_name,
            $description,
            $category,
            $event_date,
            $start_time,
            $end_time,
            $location,
            $organizer,
            $trainer_id_db,
            $max_participants,
            $reg_deadline_db,
            $status
        );

        $stmt->execute();
        $new_id = $stmt->insert_id;
        $stmt->close();

        event_redirect('ok', "Event '{$event_name}' created (#{$new_id}).");
    }

    event_redirect('err', 'Unknown action.');
}

/* ------------------------------------------------------------------ */
/* GET page                                                           */
/* ------------------------------------------------------------------ */
$curCat = $_GET['cat'] ?? '';
$curStatus = $_GET['status'] ?? '';

$where = [];
$params = [];
$types = '';

if (in_array($curCat, $CATS, true)) {
    $where[] = 'e.category = ?';
    $params[] = $curCat;
    $types .= 's';
} else {
    $curCat = '';
}

if (in_array($curStatus, $STATUSES, true)) {
    $where[] = 'e.status = ?';
    $params[] = $curStatus;
    $types .= 's';
} else {
    $curStatus = '';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare(
    "SELECT e.*,
        (SELECT COUNT(*)
         FROM event_registrations r
         WHERE r.event_id = e.id
           AND r.status IN ('Registered','Attended')) AS regs,
        (SELECT COUNT(*)
         FROM event_attendance a
         WHERE a.event_id = e.id
           AND a.status = 'Present') AS attended
     FROM community_events e
     {$whereSql}
     ORDER BY e.event_date DESC, e.id DESC"
);

if (!$stmt) {
    die('Could not prepare the events query.');
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$events_result = $stmt->get_result();
$event_rows = $events_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$editEvent = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];

    if ($edit_id > 0) {
        $stmt = $db->prepare('SELECT * FROM community_events WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $editEvent = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
    }
}

/* Load trainers into an array so the list can be used safely. */
$trainer_rows = [];
$trainer_result = $db->query('SELECT id, name FROM trainers ORDER BY name');
if ($trainer_result) {
    while ($trainer = $trainer_result->fetch_assoc()) {
        $trainer_rows[] = $trainer;
    }
}

function event_value(?array $row, string $key, string $default = ''): string
{
    if (!$row || !array_key_exists($key, $row) || $row[$key] === null) {
        return $default;
    }

    return (string)$row[$key];
}

function event_date_value(?array $row, string $key): string
{
    $value = event_value($row, $key);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('Y-m-d', $timestamp) : '';
}

function event_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<?= flash() ?>

<div class="page-head">
  <div>
    <h2>Community Events</h2>
    <p>Create and manage community events across 12 programme categories. <?= count($event_rows) ?> shown.</p>
  </div>
  <a href="attendance.php" class="btn btn-navy">Mark Attendance &rarr;</a>
</div>

<?php include __DIR__ . '/_nav.php'; ?>

<!-- Category and status filters -->
<div class="toolbar">
  <div style="width:100%;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start">

    <div class="search" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;max-width:none;flex:1">
      <b style="font-size:12px;color:var(--muted);margin-right:4px">CATEGORY:</b>

      <a class="btn btn-ghost btn-sm <?= $curCat === '' ? 'btn-navy' : '' ?>"
         href="<?= event_h(event_filter_url('', $curStatus)) ?>">All</a>

      <?php foreach ($CATS as $cat): ?>
        <a class="btn btn-ghost btn-sm <?= $curCat === $cat ? 'btn-navy' : '' ?>"
           href="<?= event_h(event_filter_url($cat, $curStatus)) ?>">
          <?= event_h($cat) ?>
        </a>
      <?php endforeach; ?>
    </div>

  </div>

  <div style="width:100%;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-start">

    <div class="search" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;max-width:none;flex:1">
      <b style="font-size:12px;color:var(--muted);margin-right:4px">STATUS:</b>

      <a class="btn btn-ghost btn-sm <?= $curStatus === '' ? 'btn-navy' : '' ?>"
         href="<?= event_h(event_filter_url($curCat, '')) ?>">All</a>

      <?php foreach ($STATUSES as $status): ?>
        <a class="btn btn-ghost btn-sm <?= $curStatus === $status ? 'btn-navy' : '' ?>"
           href="<?= event_h(event_filter_url($curCat, $status)) ?>">
          <?= event_h($status) ?>
        </a>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<?php if ($editEvent): ?>
<div class="card" style="margin-bottom:18px">
  <div class="card-head">
    <h3>Edit Event #<?= (int)$editEvent['id'] ?></h3>
    <a href="events.php" class="btn btn-ghost btn-sm">Cancel edit</a>
  </div>

  <div class="card-body">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editEvent['id'] ?>">

      <div class="form-field">
        <label>Event Name <span class="req">*</span></label>
        <input type="text" name="event_name" maxlength="160" required value="<?= event_h(event_value($editEvent, 'event_name')) ?>">
      </div>

      <div class="form-field">
        <label>Category <span class="req">*</span></label>
        <select name="category" required>
          <?php foreach ($CATS as $cat): ?>
            <option value="<?= event_h($cat) ?>" <?= event_value($editEvent, 'category') === $cat ? 'selected' : '' ?>><?= event_h($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-field full">
        <label>Description</label>
        <textarea name="description" maxlength="5000" rows="4"><?= event_h(event_value($editEvent, 'description')) ?></textarea>
      </div>

      <div class="form-field">
        <label>Event Date <span class="req">*</span></label>
        <input type="date" name="event_date" required value="<?= event_h(event_date_value($editEvent, 'event_date')) ?>">
      </div>

      <div class="form-field">
        <label>Registration Deadline</label>
        <input type="date" name="reg_deadline" value="<?= event_h(event_date_value($editEvent, 'reg_deadline')) ?>">
      </div>

      <div class="form-field">
        <label>Start Time <span class="req">*</span></label>
        <input type="time" name="start_time" required value="<?= event_h(substr(event_value($editEvent, 'start_time', '06:00:00'), 0, 5)) ?>">
      </div>

      <div class="form-field">
        <label>End Time <span class="req">*</span></label>
        <input type="time" name="end_time" required value="<?= event_h(substr(event_value($editEvent, 'end_time', '08:00:00'), 0, 5)) ?>">
      </div>

      <div class="form-field">
        <label>Location <span class="req">*</span></label>
        <input type="text" name="location" maxlength="200" required value="<?= event_h(event_value($editEvent, 'location')) ?>">
      </div>

      <div class="form-field">
        <label>Organizer <span class="req">*</span></label>
        <input type="text" name="organizer" maxlength="120" required value="<?= event_h(event_value($editEvent, 'organizer')) ?>">
      </div>

      <div class="form-field">
        <label>Lead Trainer</label>
        <select name="trainer_id">
          <option value="0">— None (community-led) —</option>
          <?php foreach ($trainer_rows as $trainer): ?>
            <option value="<?= (int)$trainer['id'] ?>" <?= (int)event_value($editEvent, 'trainer_id', '0') === (int)$trainer['id'] ? 'selected' : '' ?>>
              <?= event_h($trainer['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-field">
        <label>Max Participants <span class="req">*</span></label>
        <input type="number" name="max_participants" min="1" max="10000" required value="<?= (int)event_value($editEvent, 'max_participants', '50') ?>">
      </div>

      <div class="form-field">
        <label>Status <span class="req">*</span></label>
        <select name="status" required>
          <?php foreach ($STATUSES as $status): ?>
            <option value="<?= event_h($status) ?>" <?= event_value($editEvent, 'status', 'Upcoming') === $status ? 'selected' : '' ?>><?= event_h($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="events.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Create event -->
<div class="card" style="margin-bottom:18px">
  <div class="card-head">
    <h3><?= $editEvent ? 'Create Another Event' : 'Create New Event' ?></h3>
  </div>

  <div class="card-body">
    <form method="post" class="form-grid">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <div class="form-field">
        <label>Event Name <span class="req">*</span></label>
        <input type="text" name="event_name" maxlength="160" required placeholder="e.g. Community Morning Yoga">
      </div>

      <div class="form-field">
        <label>Category <span class="req">*</span></label>
        <select name="category" required>
          <?php foreach ($CATS as $cat): ?>
            <option value="<?= event_h($cat) ?>"><?= event_h($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-field full">
        <label>Description</label>
        <textarea name="description" maxlength="5000" rows="4" placeholder="Describe the community activity, what participants should bring, and what they will learn."></textarea>
      </div>

      <div class="form-field">
        <label>Event Date <span class="req">*</span></label>
        <input type="date" name="event_date" required>
      </div>

      <div class="form-field">
        <label>Registration Deadline</label>
        <input type="date" name="reg_deadline">
      </div>

      <div class="form-field">
        <label>Start Time <span class="req">*</span></label>
        <input type="time" name="start_time" required value="07:00">
      </div>

      <div class="form-field">
        <label>End Time <span class="req">*</span></label>
        <input type="time" name="end_time" required value="08:30">
      </div>

      <div class="form-field">
        <label>Location <span class="req">*</span></label>
        <input type="text" name="location" maxlength="200" required placeholder="e.g. Community Park">
      </div>

      <div class="form-field">
        <label>Organizer <span class="req">*</span></label>
        <input type="text" name="organizer" maxlength="120" required value="New Life Fitness Community Team">
      </div>

      <div class="form-field">
        <label>Lead Trainer</label>
        <select name="trainer_id">
          <option value="0">— None (community-led) —</option>
          <?php foreach ($trainer_rows as $trainer): ?>
            <option value="<?= (int)$trainer['id'] ?>"><?= event_h($trainer['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-field">
        <label>Max Participants <span class="req">*</span></label>
        <input type="number" name="max_participants" min="1" max="10000" required value="50">
      </div>

      <div class="form-field">
        <label>Status <span class="req">*</span></label>
        <select name="status" required>
          <?php foreach ($STATUSES as $status): ?>
            <option value="<?= event_h($status) ?>" <?= $status === 'Upcoming' ? 'selected' : '' ?>><?= event_h($status) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create Event</button>
      </div>
    </form>
  </div>
</div>

<!-- All events -->
<div class="card">
  <div class="card-head">
    <h3>All Events</h3>
  </div>

  <div class="card-body" style="padding:0">
    <div class="table-wrap">
      <table class="data">
        <thead>
          <tr>
            <th>ID</th>
            <th>Event</th>
            <th>Category</th>
            <th>Date / Time</th>
            <th>Location</th>
            <th>Capacity</th>
            <th>Attendance</th>
            <th>Status</th>
            <th class="no-sort">Actions</th>
          </tr>
        </thead>

        <tbody>
        <?php if (!$event_rows): ?>
          <tr>
            <td colspan="9" class="empty">
              <span class="ico">&#128197;</span>
              No events match these filters.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($event_rows as $event): ?>
            <?php
              $cap = (int)$event['max_participants'];
              $regs = (int)$event['regs'];
              $attended = (int)$event['attended'];
              $pct = $cap > 0 ? min(100, (int)round(($regs / $cap) * 100)) : 0;
              $badgeClass = [
                  'Upcoming' => 'gold',
                  'Ongoing' => 'steel',
                  'Completed' => 'green',
                  'Cancelled' => 'red'
              ][$event['status']] ?? 'gray';
            ?>
            <tr>
              <td>#<?= (int)$event['id'] ?></td>

              <td>
                <b><?= event_h($event['event_name']) ?></b>
                <?php if (!empty($event['description'])): ?>
                  <br>
                  <small class="muted">
                    <?= event_h(mb_substr($event['description'], 0, 80)) ?><?= mb_strlen($event['description']) > 80 ? '…' : '' ?>
                  </small>
                <?php endif; ?>
              </td>

              <td>
                <span class="badge steel"><?= event_h($event['category']) ?></span>
              </td>

              <td>
                <?= event_h(fmtDate($event['event_date'])) ?><br>
                <small class="muted">
                  <?= event_h(substr((string)$event['start_time'], 0, 5)) ?>
                  &ndash;
                  <?= event_h(substr((string)$event['end_time'], 0, 5)) ?>
                </small>
              </td>

              <td><?= event_h($event['location']) ?></td>

              <td>
                <div class="comm-cap">
                  <div class="track">
                    <div class="fill" style="width: <?= $pct ?>%"></div>
                  </div>
                  <div class="txt"><?= $regs ?>/<?= $cap ?></div>
                </div>
              </td>

              <td>
                <?php if ($event['status'] === 'Completed'): ?>
                  <?= $attended ?> present
                <?php else: ?>
                  <span class="badge gray">Not yet</span>
                <?php endif; ?>
              </td>

              <td>
                <span class="badge <?= event_h($badgeClass) ?>">
                  <?= event_h($event['status']) ?>
                </span>

                <form method="post" style="margin-top:6px">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
                  <select name="status" onchange="this.form.submit()" style="padding:4px 6px;border:1px solid var(--line);border-radius:7px;font-size:12.5px">
                    <?php foreach ($STATUSES as $status): ?>
                      <option value="<?= event_h($status) ?>" <?= $event['status'] === $status ? 'selected' : '' ?>><?= event_h($status) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>

              <td class="row-actions">
                <a href="attendance.php?event=<?= (int)$event['id'] ?>" class="btn btn-ghost btn-sm">Attendance</a>
                <a href="events.php?edit=<?= (int)$event['id'] ?>" class="btn btn-navy btn-sm">Edit</a>

                <form method="post" style="display:inline" data-confirm="Delete this event? Related registrations, attendance and feedback may also be removed.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$event['id'] ?>">
                  <button class="btn btn-danger btn-sm" type="submit">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
