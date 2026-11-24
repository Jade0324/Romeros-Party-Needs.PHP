  <footer class="site-footer">
    <div class="container">
      <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo e(BUSINESS_NAME); ?>. All Rights Reserved.</p>
    </div>
  </footer>

  <script>
    // Values the page scripts need (API paths are built from this).
    window.RPN = {
      baseUrl: <?php echo json_encode(BASE_URL); ?>,
      currency: <?php echo json_encode(CURRENCY); ?>,
      deliveryFee: <?php echo json_encode(DELIVERY_FEE); ?>,
      downPaymentRate: <?php echo json_encode(DOWN_PAYMENT_RATE); ?>
    };
  </script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?php echo asset('assets/js/script.js'); ?>"></script>
  <?php foreach (($pageScripts ?? []) as $script): ?>
    <script src="<?php echo asset('assets/js/' . $script); ?>"></script>
  <?php endforeach; ?>
</body>
</html>
