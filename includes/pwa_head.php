<?php
$pwaBaseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/' : '/';
?>
<link rel="manifest" href="<?php echo htmlspecialchars($pwaBaseUrl . 'manifest.webmanifest'); ?>">
<meta name="theme-color" content="#1a1a2e">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Cuts & Styles">
<meta name="application-name" content="Cuts & Styles">
<link rel="icon" type="image/svg+xml" href="<?php echo htmlspecialchars($pwaBaseUrl . 'assets/icons/icon.svg'); ?>">
<link rel="apple-touch-icon" href="<?php echo htmlspecialchars($pwaBaseUrl . 'assets/icons/apple-touch-icon.png'); ?>">
<link rel="stylesheet" href="<?php echo htmlspecialchars($pwaBaseUrl . 'assets/css/pwa-responsive.css'); ?>">
