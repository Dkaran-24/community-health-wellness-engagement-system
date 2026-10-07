<?php
/**
 * admin/offers/edit.php — edit an existing offer.
 *
 * Loads the offer, presents the same form as add.php (pre-filled), handles
 * an optional new poster upload (replacing the old one), and updates the
 * offer inside a transaction. Supports "remove current poster".
 */
$PAGE_TITLE = 'Edit Offer';
$PAGE_KEY   = 'offers';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/offers.php';

$db = db();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php?err=' . urlencode('Invalid offer id.')); exit; }

$offer = getOffer($id);
if (!$offer) { header('Location: index.php?err=' . urlencode('Offer not found.')); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'title'       => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'discount'    => $_POST['discount'] ?? '',
        'start_date'  => $_POST['start_date'] ?? '',
        'end_date'    => $_POST['end_date'] ?? '',
        'status'      => $_POST['status'] ?? 'Active',
    ];

    /* Poster handling:
       - "remove_poster" checked -> clear the poster
       - new file uploaded      -> replace (delete old file)
       - otherwise              -> keep the existing path */
    $removePoster = isset($_POST['remove_poster']);
    if ($removePoster) {
        $data['poster_path'] = '';
        if (!empty($offer['poster_path'])) {
            $old = __DIR__ . '/../../' . $offer['poster_path'];
            if (file_exists($old)) @unlink($old);
        }
    } else {
        $posterErr = '';
        $newPoster = handleOfferPosterUpload($posterErr);
        if ($posterErr) {
            header('Location: edit.php?id=' . $id . '&err=' . urlencode($posterErr));
            exit;
        }
        if ($newPoster) {
            $data['poster_path'] = $newPoster;
            /* delete the previous poster */
            if (!empty($offer['poster_path']) && $offer['poster_path'] !== $newPoster) {
                $old = __DIR__ . '/../../' . $offer['poster_path'];
                if (file_exists($old)) @unlink($old);
            }
        } else {
            $data['poster_path'] = $offer['poster_path'];   /* keep existing */
        }
    }

    $result = updateOffer($id, $data);
    if ($result['ok']) {
        header('Location: index.php?ok=' . urlencode('Offer "' . $data['title'] . '" updated successfully.'));
    } else {
        if (!empty($newPoster)) { $f = __DIR__ . '/../../' . $newPoster; if (file_exists($f)) @unlink($f); }
        header('Location: edit.php?id=' . $id . '&err=' . urlencode($result['error']));
    }
    exit;
}
?>
<?= flash() ?>
<div class="page-head">
  <div><h2>Edit Offer</h2><p>Update offer &ldquo;<?= e($offer['title']) ?>&rdquo;.</p></div>
  <a href="index.php" class="btn btn-ghost">&larr; Back to Offers</a>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" data-validate>
      <div class="form-grid">
        <div class="form-field full">
          <label>Offer Title <span class="req">*</span></label>
          <input type="text" name="title" required value="<?= e($offer['title']) ?>">
        </div>

        <div class="form-field">
          <label>Discount / Promotion Details <span class="req">*</span></label>
          <input type="text" name="discount" required value="<?= e($offer['discount']) ?>">
        </div>

        <div class="form-field">
          <label>Status</label>
          <select name="status">
            <option value="Active" <?= $offer['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $offer['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>

        <div class="form-field">
          <label>Start Date <span class="req">*</span></label>
          <input type="date" name="start_date" required value="<?= e($offer['start_date']) ?>">
        </div>

        <div class="form-field">
          <label>End Date <span class="req">*</span></label>
          <input type="date" name="end_date" required value="<?= e($offer['end_date']) ?>">
        </div>

        <div class="form-field full">
          <label>Description</label>
          <textarea name="description" rows="4"><?= e($offer['description']) ?></textarea>
        </div>

        <div class="form-field full">
          <label>Poster / Banner Image</label>
          <?php if (!empty($offer['poster_path'])): ?>
            <div class="photo-current" style="margin-bottom:12px">
              <?= offerPosterImg($offer['poster_path'], $offer['title'], 'offer-poster-sm') ?>
              <label class="photo-remove" style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;cursor:pointer">
                <input type="checkbox" name="remove_poster" value="1"> Remove current poster
              </label>
            </div>
          <?php endif; ?>
          <input type="file" name="poster" id="posterInput" accept="image/jpeg,image/png,image/gif,image/webp">
          <span class="hint">Upload a new image to replace the current poster. JPG, PNG, GIF or WebP — max 5 MB.</span>
          <div id="posterPreview" class="poster-preview" style="display:none">
            <img id="posterPreviewImg" alt="New poster preview">
            <button type="button" class="btn btn-ghost btn-sm" onclick="clearPosterPreview()">Remove preview</button>
          </div>
        </div>
      </div>

      <div class="form-actions">
        <button type="submit" class="btn btn-primary">&#10003; Save Changes</button>
        <a href="index.php" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
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
