<?php
$pwaBaseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/';
?>
<script>
    window.CUTS_PWA_BASE_URL = <?php echo json_encode($pwaBaseUrl, JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="<?php echo htmlspecialchars($pwaBaseUrl . 'assets/js/pwa-register.js'); ?>"></script>
