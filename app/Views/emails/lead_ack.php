<?php
/**
 * Customer acknowledgement / auto-reply (§13). OFF by default; message is
 * admin-configured. No response-time promises, pricing, availability, or medical
 * claims unless the owner explicitly writes them into the setting.
 * @var array $lead @var string $message
 */
$settings = app(\App\Services\SettingsService::class);
$company = $settings->companyName();
$site = $settings->websiteUrl();
$email = $settings->get('company_email');
$phone = $settings->get('company_phone');
?>
<div style="font-family:Arial,Helvetica,sans-serif;background:#f1f5f9;padding:24px">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="background:#0b2a4a;padding:18px 24px;color:#fff;font-size:16px;font-weight:bold"><?= e($company) ?></div>
    <div style="padding:24px">
      <p style="color:#0b1f3a;font-size:15px">Dear <?= e($lead['name'] ?? 'Sir/Madam') ?>,</p>
      <p style="color:#334155;font-size:14px;line-height:1.6"><?= nl2br(e($message)) ?></p>
      <?php if (!empty($lead['reference'])): ?>
      <p style="color:#64748b;font-size:13px">Your reference: <strong><?= e($lead['reference']) ?></strong></p>
      <?php endif; ?>
      <p style="color:#334155;font-size:14px;margin-top:20px">Regards,<br><?= e($company) ?></p>
    </div>
    <div style="padding:14px 24px;border-top:1px solid #e2e8f0;color:#94a3b8;font-size:12px">
      <?= e($site) ?><?php if ($email !== ''): ?> · <?= e($email) ?><?php endif; ?><?php if ($phone !== ''): ?> · <?= e($phone) ?><?php endif; ?><br>
      This is an automated message; please do not share sensitive information by email.
    </div>
  </div>
</div>
