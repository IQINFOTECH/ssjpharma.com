<?php
/**
 * Internal new-lead notification (§12). Self-contained HTML email. All values
 * escaped. No secrets. Branding from CMS settings.
 * @var array $lead  (LeadRepository::findById row) @var array $enquiry @var bool $isRepeat
 */
$settings = app(\App\Services\SettingsService::class);
$company = $settings->companyName();
$site = $settings->websiteUrl();
$row = static function (string $label, ?string $value) {
    if ($value === null || trim((string) $value) === '') return '';
    return '<tr><td style="padding:6px 12px;color:#64748b;font-size:13px;white-space:nowrap;vertical-align:top">' . e($label)
        . '</td><td style="padding:6px 12px;color:#0b1f3a;font-size:14px">' . nl2br(e($value)) . '</td></tr>';
};
$location = trim(implode(', ', array_filter([$lead['city'] ?? '', $lead['state'] ?? '', $lead['country'] ?? ''])));
$utms = trim(implode(' · ', array_filter([
    $lead['utm_source'] ? 'source=' . $lead['utm_source'] : '',
    $lead['utm_medium'] ? 'medium=' . $lead['utm_medium'] : '',
    $lead['utm_campaign'] ? 'campaign=' . $lead['utm_campaign'] : '',
])));
?>
<div style="font-family:Arial,Helvetica,sans-serif;background:#f1f5f9;padding:24px">
  <div style="max-width:600px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="background:#0b2a4a;padding:18px 24px;color:#fff">
      <div style="font-size:16px;font-weight:bold"><?= e($company) ?></div>
      <div style="font-size:13px;opacity:.8">New Website Enquiry<?= $isRepeat ? ' (repeat)' : '' ?></div>
    </div>
    <div style="padding:20px 12px">
      <table style="width:100%;border-collapse:collapse">
        <?= $row('Reference', $lead['reference'] ?? '') ?>
        <?= $row('Enquiry Type', $enquiry['label'] ?? '') ?>
        <?= $row('Name', $lead['name'] ?? '') ?>
        <?= $row('Company', $lead['company'] ?? '') ?>
        <?= $row('Email', $lead['email'] ?? '') ?>
        <?= $row('Phone', $lead['phone'] ?? '') ?>
        <?= $row('WhatsApp', $lead['whatsapp'] ?? '') ?>
        <?= $row('Location', $location) ?>
        <?= $row('Business Type', $lead['business_type'] ?? '') ?>
        <?= $row('Product', $lead['product_name'] ?? ($lead['product_name_snapshot'] ?? '')) ?>
        <?= $row('Requirement', $lead['requirement'] ?? '') ?>
        <?= $row('Preferred Contact', $lead['preferred_contact'] ?? '') ?>
        <?= $row('Source', $lead['source_name'] ?? '') ?>
        <?= $row('UTM', $utms) ?>
        <?= $row('Landing Page', $lead['landing_page'] ?? '') ?>
        <?= $row('Message', $lead['message'] ?? '') ?>
        <?= $row('Received', $lead['created_at'] ?? '') ?>
      </table>
    </div>
    <div style="padding:14px 24px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px">
      This is an automated internal notification from <?= e($site) ?>. Reply to reach the enquirer.
    </div>
  </div>
</div>
