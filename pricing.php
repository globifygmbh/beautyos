<?php
$pageTitle = 'Preise';
require_once 'includes/header.php';

$db = getDB();
$plans = $db->query("SELECT * FROM subscription_plans ORDER BY sort_order")->fetchAll();
?>

<section class="section">
    <div class="container" style="max-width: 1000px;">
        <div class="text-center" style="margin-bottom: 48px;">
            <h1>Einfache, transparente Preise</h1>
            <p style="color: var(--gray-500); font-size: 1.1rem; margin-top: 12px;">Wähle den Plan, der zu deinem Business passt. Jederzeit kündbar.</p>
        </div>

        <div class="pricing-grid">
            <?php foreach ($plans as $i => $plan):
                $features = json_decode($plan['features'], true) ?: [];
                $isPopular = $i === 1;
            ?>
            <div class="pricing-card <?= $isPopular ? 'popular' : '' ?>">
                <h3><?= e($plan['name']) ?></h3>
                <p style="color: var(--gray-500); font-size: 0.9rem;">
                    <?php
                    echo match($plan['slug']) {
                        'starter' => 'Perfekt zum Einstieg',
                        'professional' => 'Für wachsende Salons',
                        'premium' => 'Maximale Sichtbarkeit',
                        default => '',
                    };
                    ?>
                </p>
                <div class="pricing-amount">
                    <?= number_format($plan['price_monthly'], 2, ',', '.') ?>&euro;
                    <span>/Monat</span>
                </div>
                <ul class="pricing-features">
                    <?php foreach ($features as $f): ?>
                    <li><i class="fas fa-check"></i> <?= e($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="/register.php?type=business" class="btn <?= $isPopular ? 'btn-primary' : 'btn-outline' ?> w-100 btn-lg">
                    Jetzt starten
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
