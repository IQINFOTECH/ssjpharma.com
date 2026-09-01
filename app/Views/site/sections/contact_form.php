<?php
/**
 * Contact / lead capture form. Server-validated; CSRF + honeypot + optional
 * Turnstile. Errors and old input come from a failed POST via $form.
 * @var array $form  keys: form_key, product, errors, old, captcha_enabled, captcha_site_key
 * @var App\Services\SettingsService $settings
 */
$errors = (array) ($form['errors'] ?? []);
$old = (array) ($form['old'] ?? []);
$val = static fn (string $k): string => e((string) ($old[$k] ?? ''));
$hasErr = static fn (string $k): string => isset($errors[$k]) ? ' field-error' : '';
$errId = static fn (string $k): string => 'cf-' . str_replace('_', '-', $k) . '-err';
// Screen-reader wiring: flag the invalid field and point it at its error text.
$aria = static function (string $k) use ($errors, $errId): string {
    return isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="' . $errId($k) . '"' : '';
};
$errText = static function (string $k) use ($errors, $errId): string {
    return isset($errors[$k]) ? '<p class="error-text" id="' . $errId($k) . '">' . e($errors[$k]) . '</p>' : '';
};
?>
<section class="section-pad pt-0">
  <div class="container-x">
    <?php if (!empty($errors['_form'])): ?>
      <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($errors['_form']) ?></div>
    <?php endif; ?>

    <div class="grid gap-10 lg:grid-cols-[2fr_3fr]">
    <!-- Editorial info rail (Concept 1): company contact facts from Settings. -->
    <aside class="lg:border-r lg:border-slate-200 lg:pr-10">
      <?php $addr = $settings->fullAddress(); if ($addr !== ''): ?>
      <div class="mb-5">
        <p class="field-label mb-1">Registered office</p>
        <p class="text-sm leading-relaxed text-slate-600"><?= nl2br(e($addr)) ?></p>
      </div>
      <?php endif; ?>
      <?php $phone = $settings->get('company_phone'); if ($phone !== ''): ?>
      <div class="mb-5">
        <p class="field-label mb-1">Phone</p>
        <a href="tel:<?= e(preg_replace('/[^+\d]/', '', $phone)) ?>" class="text-sm font-medium text-brand-600 hover:text-brand-700"><?= e($phone) ?></a>
      </div>
      <?php endif; ?>
      <?php $email = $settings->get('company_email'); if ($email !== ''): ?>
      <div class="mb-5">
        <p class="field-label mb-1">Email</p>
        <a href="mailto:<?= e($email) ?>" class="text-sm font-medium text-brand-600 hover:text-brand-700"><?= e($email) ?></a>
      </div>
      <?php endif; ?>
      <?php if (!empty($whatsappLink)): ?>
      <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" class="btn btn-whatsapp" data-wa-context="contact">Chat on WhatsApp</a>
      <?php endif; ?>
      <p class="mt-6 border-t border-slate-200 pt-4 text-xs text-slate-400">We respond to business enquiries within one business day.</p>
    </aside>

    <form method="post" action="/contact" class="js-contact-form space-y-5" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="form_key" value="<?= e((string) $form['form_key']) ?>">
      <?php if (!empty($form['product'])): ?>
        <input type="hidden" name="product_id" value="<?= (int) $form['product']['id'] ?>">
        <div class="rounded-lg border border-brand-100 bg-brand-50/70 px-4 py-3 text-sm text-brand-800">
          Enquiry about: <strong><?= e($form['product']['name']) ?></strong>
        </div>
      <?php endif; ?>
      <input type="hidden" name="landing_page" value="" class="js-landing">
      <input type="hidden" name="utm_source" value="" class="js-utm" data-utm="utm_source">
      <input type="hidden" name="utm_medium" value="" class="js-utm" data-utm="utm_medium">
      <input type="hidden" name="utm_campaign" value="" class="js-utm" data-utm="utm_campaign">
      <input type="hidden" name="utm_term" value="" class="js-utm" data-utm="utm_term">
      <input type="hidden" name="utm_content" value="" class="js-utm" data-utm="utm_content">

      <!-- Honeypot: keep empty. Hidden from users, tempting to bots. -->
      <div class="absolute left-[-9999px]" aria-hidden="true">
        <label>Company Website <input type="text" name="company_website" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="grid gap-5 sm:grid-cols-2">
        <div>
          <label class="field-label" for="cf-name">Name <span class="text-red-500">*</span></label>
          <input id="cf-name" name="name" class="field<?= $hasErr('name') ?>"<?= $aria('name') ?> value="<?= $val('name') ?>" required maxlength="150" autocomplete="name">
          <?= $errText('name') ?>
        </div>
        <div>
          <label class="field-label" for="cf-company">Company</label>
          <input id="cf-company" name="company" class="field<?= $hasErr('company') ?>"<?= $aria('company') ?> value="<?= $val('company') ?>" maxlength="180" autocomplete="organization">
          <?= $errText('company') ?>
        </div>
        <div>
          <label class="field-label" for="cf-email">Email <span class="text-red-500">*</span></label>
          <input id="cf-email" type="email" name="email" class="field<?= $hasErr('email') ?>"<?= $aria('email') ?> value="<?= $val('email') ?>" required maxlength="190" autocomplete="email">
          <?= $errText('email') ?>
        </div>
        <div>
          <label class="field-label" for="cf-phone">Phone <span class="text-red-500">*</span></label>
          <input id="cf-phone" name="phone" class="field<?= $hasErr('phone') ?>"<?= $aria('phone') ?> value="<?= $val('phone') ?>" required maxlength="40" autocomplete="tel">
          <?= $errText('phone') ?>
        </div>
        <div>
          <label class="field-label" for="cf-whatsapp">WhatsApp</label>
          <input id="cf-whatsapp" name="whatsapp" class="field<?= $hasErr('whatsapp') ?>"<?= $aria('whatsapp') ?> value="<?= $val('whatsapp') ?>" maxlength="40">
          <?= $errText('whatsapp') ?>
        </div>
        <div>
          <label class="field-label" for="cf-country">Country</label>
          <input id="cf-country" name="country" class="field<?= $hasErr('country') ?>" value="<?= $val('country') ?>" maxlength="100" autocomplete="country-name">
        </div>
        <div>
          <label class="field-label" for="cf-state">State / Province</label>
          <input id="cf-state" name="state" class="field<?= $hasErr('state') ?>" value="<?= $val('state') ?>" maxlength="100">
        </div>
        <div>
          <label class="field-label" for="cf-city">City</label>
          <input id="cf-city" name="city" class="field<?= $hasErr('city') ?>" value="<?= $val('city') ?>" maxlength="100">
        </div>
        <div>
          <label class="field-label" for="cf-business">Business Type</label>
          <input id="cf-business" name="business_type" class="field<?= $hasErr('business_type') ?>" value="<?= $val('business_type') ?>" maxlength="100" placeholder="e.g. Distributor, Hospital, Pharmacy">
        </div>
        <?php if (!empty($form['product'])): ?>
        <div class="sm:col-span-2">
          <label class="field-label" for="cf-req">Quantity / Requirement</label>
          <input id="cf-req" name="requirement" class="field<?= $hasErr('requirement') ?>"<?= $aria('requirement') ?> value="<?= $val('requirement') ?>" maxlength="255" placeholder="e.g. quantity, pack size, target market">
        </div>
        <?php endif; ?>
      </div>

      <div>
        <label class="field-label" for="cf-message">Message</label>
        <textarea id="cf-message" name="message" rows="5" class="field<?= $hasErr('message') ?>"<?= $aria('message') ?> maxlength="5000"><?= $val('message') ?></textarea>
        <?= $errText('message') ?>
      </div>

      <div>
        <span class="field-label">Preferred Contact Method</span>
        <div class="flex flex-wrap gap-4 pt-1">
          <?php foreach (['email' => 'Email', 'phone' => 'Phone', 'whatsapp' => 'WhatsApp'] as $pk => $plabel):
              $checked = ($old['preferred_contact'] ?? 'email') === $pk ? 'checked' : ''; ?>
          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="radio" name="preferred_contact" value="<?= e($pk) ?>" <?= $checked ?> class="text-brand-600 focus:ring-brand-500"> <?= e($plabel) ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <label class="flex items-start gap-3 text-sm text-slate-600">
        <input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"<?= $aria('consent') ?> <?= !empty($old['consent']) ? 'checked' : '' ?> required>
        <span>I consent to being contacted regarding my enquiry. <span class="text-red-500">*</span></span>
      </label>
      <?= $errText('consent') ?>

      <?php if (!empty($form['captcha_enabled']) && !empty($form['captcha_site_key'])): ?>
        <div class="cf-turnstile" data-sitekey="<?= e((string) $form['captcha_site_key']) ?>"></div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
      <?php endif; ?>

      <div class="flex items-center gap-4">
        <button type="submit" class="btn btn-primary js-submit">
          <span class="js-submit-label">Send Enquiry</span>
          <span class="js-submit-spinner hidden">Sending…</span>
        </button>
        <p class="text-xs text-slate-400">Your details are used only to respond to your enquiry.</p>
      </div>
    </form>
    </div>
  </div>
</section>
