<?php
if (!isset($pageTitle)) {
    $pageTitle = 'ULT Payment System';
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="<?php echo isset($themePrimary) ? htmlspecialchars($themePrimary) : '#2563eb'; ?>">
<title><?php echo htmlspecialchars($pageTitle); ?></title>
<?php if (function_exists('loadTheme') && isset($bdd)) { loadTheme($bdd); } ?>
<link rel="stylesheet" href="/payment/assets/css/app.css?v=1.0">
<link rel="stylesheet" href="/payment/assets/css/responsive.css?v=1.0">
