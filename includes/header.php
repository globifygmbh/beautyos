<?php require_once __DIR__ . '/../config/app.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | ' . APP_NAME : APP_NAME . ' - Dein Beauty Marktplatz' ?></title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Leaflet (Maps) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="navbar-inner">
            <a href="/" class="navbar-logo">
                <span class="logo-dot"></span>
                Beauty<span>OS</span>
            </a>

            <ul class="navbar-menu" id="navMenu">
                <li><a href="/" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' && dirname($_SERVER['PHP_SELF']) === '/' ? 'active' : '' ?>">Startseite</a></li>
                <li><a href="/marketplace.php" class="<?= basename($_SERVER['PHP_SELF']) === 'marketplace.php' ? 'active' : '' ?>">Marktplatz</a></li>
                <li><a href="/pricing.php" class="<?= basename($_SERVER['PHP_SELF']) === 'pricing.php' ? 'active' : '' ?>">Preise</a></li>
            </ul>

            <div class="navbar-actions">
                <?php if (isLoggedIn()): ?>
                    <?php $user = currentUser(); ?>
                    <?php if (isAdmin()): ?>
                        <a href="/admin/index.php" class="btn btn-ghost btn-sm"><i class="fas fa-shield-halved"></i> Admin</a>
                    <?php endif; ?>
                    <?php if (isBusiness()): ?>
                        <a href="/dashboard/index.php" class="btn btn-ghost btn-sm"><i class="fas fa-store"></i> Dashboard</a>
                    <?php endif; ?>
                    <a href="/profile.php" class="btn btn-ghost btn-sm"><i class="fas fa-user"></i> <?= e($user['first_name']) ?></a>
                    <a href="/logout.php" class="btn btn-outline btn-sm">Abmelden</a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-ghost btn-sm">Anmelden</a>
                    <a href="/register.php" class="btn btn-primary btn-sm">Registrieren</a>
                <?php endif; ?>

                <button class="menu-toggle" onclick="document.getElementById('navMenu').classList.toggle('open')">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>

    <?php $flash = getFlash(); if ($flash): ?>
    <div class="container" style="margin-top: 16px;">
        <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    </div>
    <?php endif; ?>

    <main>
