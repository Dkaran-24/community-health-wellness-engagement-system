<?php
/**
 * admin/community/_challenge_form_fields.php — shared form fields for the
 * fitness challenge create/edit forms. Included from challenges.php.
 *
 * Expected: $CATS, $UNITS, $STATES (lists), $editChal (array|null), $v closure
 */
?>
      <div class="form-field">
        <label>Challenge Name <span class="req">*</span></label>
        <input type="text" name="challenge_name" maxlength="160" required value="<?= e($v('challenge_name')) ?>">
      </div>
      <div class="form-field">
        <label>Category <span class="req">*</span></label>
        <select name="category" required>
          <?php foreach ($CATS as $c): ?>
            <option value="<?= $c ?>" <?= $v('category', 'General Fitness') === $c ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Target Value <span class="req">*</span> <small class="muted">(e.g. 30 days, 10000 steps)</small></label>
        <input type="number" name="target_value" min="1" max="1000000" required value="<?= (int)$v('target_value', 30) ?>">
      </div>
      <div class="form-field">
        <label>Target Unit <span class="req">*</span></label>
        <select name="target_unit" required>
          <?php foreach ($UNITS as $u): ?>
            <option value="<?= $u ?>" <?= $v('target_unit', 'days') === $u ? 'selected' : '' ?>><?= $u ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field">
        <label>Start Date <span class="req">*</span></label>
        <input type="date" name="start_date" required value="<?= e($v('start_date')) ?>">
      </div>
      <div class="form-field">
        <label>End Date <span class="req">*</span></label>
        <input type="date" name="end_date" required value="<?= e($v('end_date')) ?>">
      </div>
      <div class="form-field">
        <label>Status</label>
        <select name="status">
          <?php foreach ($STATES as $s): ?>
            <option value="<?= $s ?>" <?= $v('status', 'Active') === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-field full">
        <label>Description <small class="muted">(shown on the challenge card in the community portal)</small></label>
        <textarea name="description" rows="4" placeholder="Describe the challenge, rules and prizes&hellip;"><?= e($v('description')) ?></textarea>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= $editChal ? 'Save Changes' : 'Create Challenge' ?></button>
        <?php if ($editChal): ?><a href="challenges.php" class="btn btn-ghost">Cancel</a><?php endif; ?>
      </div>
