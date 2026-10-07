<?php
/**
 * admin/community/_resource_form_fields.php — shared form fields for the
 * wellness resource create/edit forms. Included from resources.php.
 *
 * Expected: $CATS (list), $editRes (array|null)
 */
$v = function (string $key, $fallback = '') use ($editRes) {
    return $editRes[$key] ?? $fallback;
};
?>
      <div class="form-field">
        <label>Title <span class="req">*</span></label>
        <input type="text" name="title" maxlength="160" required value="<?= e($v('title')) ?>">
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
        <label>Summary <span class="req">*</span> <small class="muted">(card text, 20–255 characters)</small></label>
        <input type="text" name="summary" maxlength="255" required minlength="20" value="<?= e($v('summary')) ?>">
      </div>
      <div class="form-field full">
        <label>Content <span class="req">*</span> <small class="muted">(the full article shown when a resident opens the resource)</small></label>
        <textarea name="content" rows="8" required placeholder="Write the article content here&hellip;"><?= e($v('content')) ?></textarea>
      </div>
      <div class="form-field">
        <label>Status</label>
        <select name="status">
          <option value="Published" <?= $v('status', 'Published') === 'Published' ? 'selected' : '' ?>>Published (visible in portal)</option>
          <option value="Draft" <?= $v('status') === 'Draft' ? 'selected' : '' ?>>Draft (hidden)</option>
        </select>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editRes ? 'Save Changes' : 'Create Resource' ?></button>
        <?php if ($editRes): ?><a href="resources.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
