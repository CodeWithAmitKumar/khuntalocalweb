<?php
/**
 * KhuntaLocal — Admin settings.
 */
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

$user = require_permission('settings.manage');

$languages = active_languages();
$locations = active_locations();
$langCodes = array_column($languages, 'code');
$locIds    = array_map('intval', array_column($locations, 'id'));

// Editable settings: key => [label, type, group, extra]
$spec = [
    'site_name'                  => ['Site name', 'string', 'General'],
    'site_tagline'               => ['Tagline', 'string', 'General'],
    'default_language'           => ['Default language', 'lang', 'General'],
    'default_location_id'        => ['Default location', 'loc', 'General'],
    'registration_enabled'       => ['Registration open', 'bool', 'General'],
    'maintenance_mode'           => ['Maintenance mode', 'bool', 'General'],
    'verification_min_minutes'   => ['Minimum review minutes', 'int', 'Verification'],
    'verification_max_minutes'   => ['Maximum review minutes', 'int', 'Verification'],
    'auto_publish_enabled'       => ['Automatic publishing', 'bool', 'Verification'],
    'auto_publish_low_risk_only' => ['Auto-publish only low-risk', 'bool', 'Verification'],
    'comment_moderation'         => ['Moderate comments before showing', 'bool', 'Moderation'],
    'max_image_size'             => ['Max image size (bytes)', 'int', 'Uploads'],
    'max_video_size'             => ['Max video size (bytes)', 'int', 'Uploads'],
];

if (is_post()) {
    csrf_check();
    $changed = [];
    foreach ($spec as $key => [$label, $type]) {
        if ($type === 'bool') {
            $new = isset($_POST[$key]) ? '1' : '0';
        } else {
            $new = trim((string) ($_POST[$key] ?? ''));
        }

        // Light validation per field.
        if ($type === 'int') {
            $new = (string) max(0, (int) $new);
        } elseif ($type === 'lang' && !in_array($new, $langCodes, true)) {
            continue;
        } elseif ($type === 'loc') {
            $new = (string) ((int) $new);
            if ((int) $new > 0 && !in_array((int) $new, $locIds, true)) { continue; }
        }

        $old = (string) setting($key, '');
        if ($old !== $new) {
            setting_set($key, $new, (int) $user['id']);
            $changed[] = $key;
        }
    }

    if ($changed) {
        audit_log('ADMIN_CHANGED_SETTINGS', [
            'entity_type' => 'settings',
            'reason' => 'Updated: ' . implode(', ', $changed),
            'admin_id' => (int) $user['id'],
        ]);
        flash_set('success', count($changed) . ' setting(s) updated.');
    } else {
        flash_set('info', 'No changes to save.');
    }
    redirect('admin/settings.php');
}

// Group for display.
$groups = [];
foreach ($spec as $key => [$label, $type, $group]) {
    $groups[$group][$key] = ['label' => $label, 'type' => $type];
}

$pageTitle   = 'Settings';
$activeAdmin = 'settings';
require KL_INCLUDES . '/partials/admin-head.php';
?>
<form method="post" action="<?= e_attr(base_url('admin/settings.php')) ?>">
  <?= csrf_field() ?>
  <?php foreach ($groups as $group => $fields): ?>
    <div class="kl-card p-3 p-md-4 mb-3" style="box-shadow:none">
      <h2 class="h6 mb-3"><?= e($group) ?></h2>
      <div class="row g-3">
        <?php foreach ($fields as $key => $f): $val = setting($key, ''); ?>
          <div class="col-12 col-md-6">
            <?php if ($f['type'] === 'bool'): ?>
              <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" role="switch" id="<?= e_attr($key) ?>" name="<?= e_attr($key) ?>" value="1" <?= setting_bool($key) ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= e_attr($key) ?>"><?= e($f['label']) ?></label>
              </div>
            <?php elseif ($f['type'] === 'lang'): ?>
              <label class="form-label" for="<?= e_attr($key) ?>"><?= e($f['label']) ?></label>
              <select class="form-select" id="<?= e_attr($key) ?>" name="<?= e_attr($key) ?>">
                <?php foreach ($languages as $lng): ?>
                  <option value="<?= e_attr($lng['code']) ?>" <?= (string) $val === $lng['code'] ? 'selected' : '' ?>><?= e($lng['native_name'] . ' (' . $lng['name'] . ')') ?></option>
                <?php endforeach; ?>
              </select>
            <?php elseif ($f['type'] === 'loc'): ?>
              <label class="form-label" for="<?= e_attr($key) ?>"><?= e($f['label']) ?></label>
              <select class="form-select" id="<?= e_attr($key) ?>" name="<?= e_attr($key) ?>">
                <option value="0">Not set</option>
                <?php foreach ($locations as $loc): ?>
                  <option value="<?= (int) $loc['id'] ?>" <?= (int) $val === (int) $loc['id'] ? 'selected' : '' ?>><?= e($loc['name']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <label class="form-label" for="<?= e_attr($key) ?>"><?= e($f['label']) ?></label>
              <input class="form-control" type="<?= $f['type'] === 'int' ? 'number' : 'text' ?>" id="<?= e_attr($key) ?>" name="<?= e_attr($key) ?>" value="<?= e_attr((string) $val) ?>" <?= $f['type'] === 'int' ? 'min="0"' : '' ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="alert alert-info small">
    Auto-publish never fires on a timer alone — the <code>auto-publish</code> cron re-runs final
    checks and only publishes low-risk items (when enabled). Changes are recorded in the audit log.
  </div>
  <button class="btn btn-emerald" type="submit">Save settings</button>
</form>
<?php require KL_INCLUDES . '/partials/admin-foot.php'; ?>
