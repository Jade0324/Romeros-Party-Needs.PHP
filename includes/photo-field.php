<?php
// Shared "Photo" field for admin forms (categories, items, services).
// Expects $form['image'] (current photo path or null). The form must have
// enctype="multipart/form-data".
?>
<div class="form-row">
  <label>Photo <span class="label-hint">(JPG, PNG or WebP, up to <?php echo UPLOAD_MAX_BYTES / 1024 / 1024; ?> MB)</span></label>
  <?php if (!empty($form['image'])): ?>
    <div class="photo-current">
      <img src="<?php echo e(url($form['image'])); ?>" alt="Current photo" />
      <label class="check-row"><input type="checkbox" name="remove_photo" value="1" /> Remove photo</label>
    </div>
  <?php endif; ?>
  <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="photo-input" />
  <img class="photo-preview" alt="" hidden />
</div>
