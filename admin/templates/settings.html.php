<?php
/**
 * Admin settings and AI API status template.
 * Rendered by the `/admin/settings` controller with `$settings` (key/value rows).
 * Displays editable non-sensitive settings and live Google Gemini API diagnostics.
 *
 * @var array $settings
 */
?>
<!-- Page header -->
<div class="page-heading">
  <span class="eyebrow">ADMIN · CONFIGURATION</span>
  <h1>Settings</h1>
  <p>Store non-sensitive settings only. API keys must be configured using server environment variables.</p>
</div>

<!-- Editable application settings stored in MySQL -->
<div class="panel form-panel">
  <form method="post" action="<?= e(\App\Core\Url::to('/admin/settings')) ?>" class="stacked-form">
    <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
    <?php foreach ($settings as $setting): // Render one labeled input per setting key. ?>
      <label>
        <?= e(str_replace('_', ' ', ucwords($setting['setting_key'], '_'))) ?>
        <input name="<?= e($setting['setting_key']) ?>" value="<?= e($setting['setting_value']) ?>" required>
      </label>
    <?php endforeach; ?>
    <button class="btn primary">Save Settings</button>
  </form>
</div>

<!-- Read-only AI integration diagnostics and connection test trigger -->
<div class="panel">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
    <div>
      <h2 style="margin:0 0 4px;">AI API Status</h2>
      <p style="margin:0;color:var(--muted);font-size:14px;">Google Gemini Integration &amp; Playground Service Status</p>
    </div>
    <?php if (\App\Services\JokeBattleEngine::configured()): ?>
      <span class="pill" style="background:#ddf5ed;color:#277b6c;font-size:13px;padding:6px 14px;border-radius:99px;font-weight:800;display:inline-flex;align-items:center;gap:7px;">
        <span style="width:9px;height:9px;border-radius:50%;background:#277b6c;display:inline-block;"></span>
        Operational (Connected)
      </span>
    <?php else: ?>
      <span class="pill" style="background:#fff2f2;color:#b33c40;font-size:13px;padding:6px 14px;border-radius:99px;font-weight:800;display:inline-flex;align-items:center;gap:7px;">
        <span style="width:9px;height:9px;border-radius:50%;background:#b33c40;display:inline-block;"></span>
        Not Configured
      </span>
    <?php endif; ?>
  </div>

  <table class="data-table" style="min-width:auto;margin:12px 0 20px;">
    <tbody>
      <tr>
        <td style="width:200px;font-weight:700;color:var(--coffee);">Provider / Service</td>
        <td><strong>Google Gemini AI</strong> (Generative Language REST API)</td>
      </tr>
      <tr>
        <td style="font-weight:700;color:var(--coffee);">AI Model</td>
        <td><code><?= e(getenv('JOKE_AI_MODEL') ?: 'gemini-3.1-flash-lite') ?></code></td>
      </tr>
      <tr>
        <td style="font-weight:700;color:var(--coffee);">API Key Status</td>
        <td>
          <?php if (!empty(getenv('JOKE_AI_API_KEY'))): ?>
            <span style="color:#277b6c;font-weight:700;">● Active</span>
            <span style="color:var(--muted);font-size:12px;margin-left:8px;">(••••••••<?= e(substr((string) getenv('JOKE_AI_API_KEY'), -4)) ?>)</span>
          <?php else: ?>
            <span style="color:#b33c40;font-weight:700;">● Missing JOKE_AI_API_KEY</span>
          <?php endif; ?>
        </td>
      </tr>
      <tr>
        <td style="font-weight:700;color:var(--coffee);">PHP cURL Extension</td>
        <td>
          <?= function_exists('curl_init') ? '<span style="color:#277b6c;font-weight:700;">● Enabled</span>' : '<span style="color:#b33c40;font-weight:700;">● Disabled</span>' ?>
        </td>
      </tr>
      <tr>
        <td style="font-weight:700;color:var(--coffee);">Service Endpoint</td>
        <td><small style="color:var(--muted);">https://generativelanguage.googleapis.com/v1beta/models/...</small></td>
      </tr>
      <tr>
        <td style="font-weight:700;color:var(--coffee);">Playground Features</td>
        <td>Joke Battle, Punchline Guess, Word Chain Engine</td>
      </tr>
    </tbody>
  </table>

  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:14px;">
    <form method="post" action="<?= e(\App\Core\Url::to('/admin/settings')) ?>" style="display:inline-block;margin:0;">
      <input type="hidden" name="_csrf" value="<?= e(\App\Core\Csrf::token()) ?>">
      <input type="hidden" name="action" value="test_ai">
      <button class="btn primary" type="submit">⚡ Test AI API Connection</button>
    </form>
    <a class="btn ghost" href="<?= e(\App\Core\Url::to('/playground')) ?>">Open AI Playground →</a>
  </div>

  <p class="note" style="margin:0;">
    Security Note: API keys are securely managed via server environment variables (never committed or stored in public database tables).
  </p>
</div>
