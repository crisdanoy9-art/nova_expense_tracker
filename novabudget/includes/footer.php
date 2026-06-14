  </div><!-- /page-content -->
</div><!-- /main-wrap -->

<!-- Toast container -->
<div id="toast-box"></div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= APP_BASE ?>/assets/js/app.js"></script>

<?php if (!empty($extraJs)): ?>
<script>
<?= $extraJs ?>
</script>
<?php endif; ?>

</body>
</html>
