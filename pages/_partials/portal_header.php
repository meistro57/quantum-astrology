<?php
declare(strict_types=1);

$activeNav = isset($activeNav) ? strtolower(trim((string)$activeNav)) : 'portal';
$showAdminLink = isset($showAdminLink) ? (bool)$showAdminLink : false;

$navItems = [
    ['key' => 'portal', 'href' => '/', 'label' => 'Portal'],
    ['key' => 'charts', 'href' => '/charts', 'label' => 'Charts'],
    ['key' => 'reports', 'href' => '/reports', 'label' => 'Reports'],
    ['key' => 'forecasting', 'href' => '/forecasting', 'label' => 'Forecasting'],
    ['key' => 'analytics', 'href' => '/analytics', 'label' => 'Analytics'],
];
?>
<header class="portal-header">
    <div class="portal-brand">
        <div class="portal-brand-dot"></div>
        Quantum Astrology
        <span style="opacity:.6;font-weight:500;margin-left:8px">· Quantum Minds United</span>
    </div>
    <nav class="portal-nav">
        <?php foreach ($navItems as $item): ?>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= $activeNav === $item['key'] ? 'active' : '' ?>">
                <?= htmlspecialchars($item['label']) ?>
            </a>
        <?php endforeach; ?>
        <?php if ($showAdminLink): ?>
            <a href="/admin" class="<?= $activeNav === 'admin' ? 'active' : '' ?>">Admin</a>
        <?php endif; ?>
        <a href="/profile" class="<?= $activeNav === 'profile' ? 'active' : '' ?>">Profile</a>
        <a href="/logout">Logout</a>
    </nav>
</header>
