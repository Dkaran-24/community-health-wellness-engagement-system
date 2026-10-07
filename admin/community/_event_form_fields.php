<?php
/**
 * admin/community/_event_form_fields.php — shared form fields for event
 * create/edit. Included from events.php.
 *
 * Expected variables:
 *   $CATS, $STATUSES, $trainers       (from events.php)
 *   $editEvent  (array|null)          (from events.php; null = create mode)
 */
$v = function (string $key, $fallback = '') use ($editEvent) {
    return $editEvent[$key] ?? $fallback;
};
$fDate = function ($d) { return $d ? date('Y-m-d', strtotime($d)) : ''; };
?>
      <div class="form-field">
        <label>Event Name <span class="req">*</span></label>
        <input type="text" name="event_name" maxlength="160" required value="<?= e($v('event_name')) ?>">
      </div>
      <div class="form-field">
        <label>Category <span class="req">*</span></label>
        <select name="category" required>
          <?php foreach ($CATS as $c): ?>
            <option value="<?= $c ?>" <?= $v('category') === $c ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field full">
        <label>Description</label>
        <textarea name="description" maxlength="5000" rows="4" placeholder="What happens at this event, what to bring&hellip;"><?= e($v('description')) ?></textarea>
      </div>
      <div class="form-field">
        <label>Event Date <span class="req">*</span></label>
        <input type="date" name="event_date" required value="<?= e($fDate($v('event_date'))) ?>">
      </div>
      <div class="form-field">
        <label>Registration Deadline</label>
        <input type="date" name="reg_deadline" value="<?= e($fDate($v('reg_deadline'))) ?>">
        <span class="hint">Optional — registration closes after this date (must be on or before the event date).</span>
      </div>
      <div class="form-field">
        <label>Start Time <span class="req">*</span></label>
        <input type="time" name="start_time" required value="<?= e(substr($v('start_time', '06:00'), 0, 5)) ?>">
      </div>
      <div class="form-field">
        <label>End Time <span class="req">*</span></label>
        <input type="time" name="end_time" required value="<?= e(substr($v('end_time', '08:00'), 0, 5)) ?>">
      </div>
      <div class="form-field">
        <label>Location <span class="req">*</span></label>
        <input type="text" name="location" maxlength="200" required value="<?= e($v('location')) ?>">
      </div>
      <div class="form-field">
        <label>Organizer <span class="req">*</span></label>
        <input type="text" name="organizer" maxlength="120" required value="<?= e($v('organizer')) ?>">
        <span class="hint">Shown to the community as the event host.</span>
      </div>
      <div class="form-field">
        <label>Lead Trainer</label>
        <select name="trainer_id">
          <option value="0">— None (community-led) —</option>
          <?php if ($trainers): while ($t = $trainers->fetch_assoc()): ?>
            <option value="<?= $t['id'] ?>" <?= (int)$v('trainer_id') === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
          <?php endwhile; endif; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Max Participants <span class="req">*</span></label>
        <input type="number" name="max_participants" min="1" max="10000" required value="<?= (int)$v('max_participants', 50) ?>">
        <span class="hint">Capacity limit — registration closes when full.</span>
  </div>
      <div class="form-field">
        <label>Status <span class="req">*</span></label>
        <select name="status" required>
          <?php foreach ($STATUSES as $s): ?>
            <option value="<?= $s ?>" <?= $v('status', 'Upcoming') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editEvent ? 'Save Changes' : 'Create Event' ?></button>
        <?php if ($editEvent): ?><a href="events.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
