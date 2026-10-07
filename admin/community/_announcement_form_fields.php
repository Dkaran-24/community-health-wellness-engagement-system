<?php
/**
 * admin/community/_announcement_form_fields.php — shared form fields for the
 * announcement create/edit forms. Included from announcements.php.
 *
 * Expected: $TYPES (list), $editAnn (array|null)
 */
$v = function (string $key, $fallback = '') use ($editAnn) {
    return $editAnn[$key] ?? $fallback;
};
?>
      <div class="form-field">
        <label>Title <span class="req">*</span></label>
        <input type="text" name="title" maxlength="160" required value="<?= e($v('title')) ?>">
      </div>
      <div class="form-field">
        <label>Type <span class="req">*</span></label>
        <select name="type" required>
          <?php foreach ($TYPES as $t): ?>
            <option value="<?= $t ?>" <?= $v('type', 'Notice') === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field full">
        <label>Message <span class="req">*</span> <small class="muted">(shown in the community portal announcement feed, max 2000 characters)</small></label>
        <textarea name="message" rows="6" required maxlength="2000" placeholder="Write the announcement message&hellip;"><?= e($v('message')) ?></textarea>
      </div>
      <div class="form-field">
        <label>Status</label>
        <select name="status">
          <option value="Active" <?= $v('status', 'Active') === 'Active' ? 'selected' : '' ?>>Active (visible in portal)</option>
          <option value="Archived" <?= $v('status') === 'Archived' ? 'selected' : '' ?>>Archived (hidden)</option>
        </select>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editAnn ? 'Save Changes' : 'Post Announcement' ?></button>
        <?php if ($editAnn): ?><a href="announcements.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
