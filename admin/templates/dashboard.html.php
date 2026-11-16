<?php
/**
 * Admin system overview dashboard template.
 * Rendered by the `/admin/overview` controller with:
 * - `$stats`: associative array of summary metric labels => counts
 * - `$growth`: daily joke-creation rows (`day`, `total`) for the last 30 days
 *
 * @var array $stats
 * @var array $growth
 */
?>
<!-- Page header -->
<div class="page-heading">
  <span class="eyebrow">PHASE 5 · ADMIN</span>
  <h1>System Overview 👑</h1>
  <p>Live statistics from MySQL, not placeholder data.</p>
</div>

<!-- Summary KPI cards -->
<div class="cards">
  <?php foreach ($stats as $label => $count): // Render one metric card per statistic. ?>
    <div class="card">
      <div class="eyebrow"><?= e(strtoupper($label)) ?></div>
      <h1><?= number_format($count) ?></h1>
    </div>
  <?php endforeach; ?>
</div>

<!-- 30-day joke creation bar chart scaled to the maximum daily total -->
<div class="panel">
  <h2>Jokes created · Last 30 days</h2>
  <div class="bar-chart">
    <?php
    $max = max(1, ...array_map('intval', array_column($growth, 'total'))); // Peak daily count (minimum 1 to avoid division by zero).
    foreach ($growth as $row): // Render one bar column per day.
      $height = max(12, round(140 * (int) $row['total'] / $max)); // Scale bar height proportionally between 12px and 140px.
    ?>
      <div class="bar-col">
        <span class="bar-val"><?= (int) $row['total'] ?></span>
        <div class="bar" style="height:<?= $height ?>px" title="<?= e($row['day']) ?>: <?= (int) $row['total'] ?> jokes"></div>
        <small><?= e(substr($row['day'], 5)) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if (!$growth): ?>
    <p>No data in the past 30 days.</p>
  <?php endif; ?>
</div>

<!-- Quick navigation links to core admin modules -->
<div class="buttons">
  <a class="btn primary" href="<?= e(\App\Core\Url::to('/admin/users')) ?>">Manage Users</a>
  <a class="btn ghost" href="<?= e(\App\Core\Url::to('/admin/categories')) ?>">Categories</a>
  <a class="btn ghost" href="<?= e(\App\Core\Url::to('/admin/settings')) ?>">Settings</a>
  <a class="btn ghost" href="<?= e(\App\Core\Url::to('/moderator/queue')) ?>">Moderation</a>
</div>
