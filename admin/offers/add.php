<?php
/**
 * admin/offers/add.php — create a new promotional offer.
 *
 * Fields: title, description, discount, poster/banner upload, start date,
 * end date, status. Validates input, handles the poster upload safely,
 * and inserts the offer inside a transaction.
 */
$PAGE_TITLE = 'Add Offer';
$PAGE_KEY   = 'offers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/offers.php';

$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Gather + sanitize input */
    $data = [
        'title'       => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'discount'    => $_POST['discount'] ?? '',
        'start_date'  => $_POST['start_date'] ?? '',
        'end_date'    => $_POST['end_date'] ?? '',
        'status'      => $_POST['status'] ?? 'Active',
        'created_by'  => $_SESSION['admin_id'] ?? 0,
    ];

    /* Handle optional poster upload */
    $posterErr = '';
    $posterPath = handleOfferPosterUpload($posterErr);
    if ($posterErr) {
        header('Location: add.php?err=' . urlencode($posterErr));
        exit;
    }
    $data['poster_path'] = $posterPath ?: '';

    /* Create the offer (validates + transactional) */
    $result = createOffer($data);
    if ($result['ok']) {
        header('Location: index.php?ok=' . urlencode('Offer "' . $data['title'] . '" created successfully. Click "Publish" to email it to active members.'));
    } else {
        /* Clean up the uploaded poster if the insert failed */
        if ($posterPath) { $f = __DIR__ . '/../../' . $posterPath; if (file_exists($f)) @unlink($f); }
        header('Location: add.php?err=' . urlencode($result['error']));
    }
    exit;
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Add New Offer</h2><p>Create a promotional offer. You can publish it as an email campaign after saving.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Offers</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" data-validate>
      <div class="form-grid">
        <div class="form-field full">
          <label>Offer Title <span class="req">*</span></label>
          <input type="text" name="title" required placeholder="e.g. New Year Fitness Challenge — 30% Off" value="<?= e($_POST['title'] ?? '') ?>">
        </div>

        <div class="form-field">
          <label>Discount / Promotion Details <span class="req">*</span></label>
          <input type="text" name="discount" required placeholder="e.g. 30% OFF all annual memberships" value="<?= e($_POST['discount'] ?? '') ?>">
          <span class="hint">A short headline of the savings/promotion shown in the email.</span>
        </div>

        <div class="form-field">
          <label>Status</label>
          <select name="status">
            <option value="Active" <?= ($_POST['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= ($_POST['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <div class="form-field">
          <label>Start Date <span class="req">*</span></label>
          <input type="date" name="start_date" required value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>">
        </div>

        <div class="form-field">
          <label>End Date <span class="req">*</span></label>
          <input type="date" name="end_date" required value="<?= e($_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days'))) ?>">
        </div>

        <div class="form-field full">
          <label>Description</label>
          <textarea name="description" rows="4" placeholder="Describe the offer, terms, and how members can claim it…"><?= e($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-field full">
          <label>Poster / Banner Image</label>
          <input type="file" name="poster" id="posterInput" accept="image/jpeg,image/png,image/gif,image/webp">
          <span class="hint">JPG, PNG, GIF or WebP — max 5 MB. Recommended 1200×630px. Embedded inline in the campaign email.</span>
          <div id="posterPreview" class="poster-preview" style="display:none">
            <img id="posterPreviewImg" alt="Poster preview">
            <button type="button" class="btn btn-ghost btn-sm" onclick="clearPosterPreview()">Remove preview</button>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">&#10003; Save Offer</button>
        <a href="index.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
  /* Live poster preview before upload */
  document.getElementById('posterInput').addEventListener('change', function (e) {
    var file = e.target.files[0];
    var box  = document.getElementById('posterPreview');
    var img  = document.getElementById('posterPreviewImg');
    if (file && file.type.match(/^image\//)) {
      img.src = URL.createObjectURL(file);
      box.style.display = 'flex';
    } else {
      box.style.display = 'none';
    }
  });
  function clearPosterPreview() {
    document.getElementById('posterInput').value = '';
    document.getElementById('posterPreview').style.display = 'none';
  }
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
