<?php
/**
 * Contact / lead capture — premium two-card layout (reference design).
 * Server-validated; CSRF + honeypot + optional Turnstile. Errors and old input
 * come from a failed POST via $form.
 * @var array $form  keys: form_key, page_slug, product, products, legal, errors, old, captcha_enabled, captcha_site_key
 * @var App\Services\SettingsService $settings
 * @var string $whatsappLink
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

$pageSlug = (string) ($form['page_slug'] ?? $form['form_key']);
$isContactPage = $pageSlug === 'contact-us';
$hasProductCtx = !empty($form['product']);
$products = (array) ($form['products'] ?? []);
$legal = (array) ($form['legal'] ?? []);
$phone = $settings->get('company_phone');
$email = $settings->get('company_email');
$addr  = $settings->fullAddress();
$addr2 = $settings->get('company_address2');
$hours = $settings->get('company_hours');
$mapsUrl = $addr !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(str_replace(["\r", "\n"], ', ', $addr)) : '';
// Enquiry-type select (contact page only): posts form_key; the server classifies
// via EnquiryType from this whitelist and never trusts anything else.
$typeOptions = [
    'contact-us'           => 'General Enquiry',
    'product-enquiry'      => 'Product Enquiry',
    'become-a-distributor' => 'Distributor Enquiry',
    'partnership'          => 'Partnership Enquiry',
];
?>
<section class="section-pad">
  <div class="container-x">
    <?php if (!empty($errors['_form'])): ?>
      <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?= e($errors['_form']) ?></div>
    <?php endif; ?>

    <div class="grid gap-7 lg:grid-cols-[3fr_2fr]">
      <!-- ============ Get in Touch (form card) ============ -->
      <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card sm:p-8">
        <div class="mb-6 flex items-start gap-4">
          <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[#0757B8] text-white">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-8 8H4l2.4-2.4A8 8 0 1 1 21 12z"/><path d="M8 11h8M8 14h5"/></svg>
          </span>
          <div>
            <h2 class="font-sans text-xl font-extrabold text-[#0b1f45]">Get in Touch</h2>
            <p class="mt-1 text-sm text-slate-500">Fill out the form and our team will get back to you as soon as possible.</p>
          </div>
        </div>

        <form method="post" action="/contact" class="js-contact-form space-y-5" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="origin_slug" value="<?= e($pageSlug) ?>">
          <?php if ($hasProductCtx): ?>
            <input type="hidden" name="form_key" value="<?= e((string) $form['form_key']) ?>">
            <input type="hidden" name="product_id" value="<?= (int) $form['product']['id'] ?>">
            <div class="rounded-lg border border-brand-100 bg-brand-50/70 px-4 py-3 text-sm text-brand-800">
              Enquiry about: <strong><?= e($form['product']['name']) ?></strong>
            </div>
          <?php elseif (!$isContactPage): ?>
            <input type="hidden" name="form_key" value="<?= e((string) $form['form_key']) ?>">
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
              <label class="field-label" for="cf-name">Full Name <span class="text-red-500">*</span></label>
              <input id="cf-name" name="name" class="field<?= $hasErr('name') ?>"<?= $aria('name') ?> value="<?= $val('name') ?>" placeholder="Enter your full name" required maxlength="150" autocomplete="name">
              <?= $errText('name') ?>
            </div>
            <div>
              <label class="field-label" for="cf-company">Company Name</label>
              <input id="cf-company" name="company" class="field<?= $hasErr('company') ?>"<?= $aria('company') ?> value="<?= $val('company') ?>" placeholder="Enter company name" maxlength="180" autocomplete="organization">
              <?= $errText('company') ?>
            </div>
            <div>
              <label class="field-label" for="cf-email">Email Address <span class="text-red-500">*</span></label>
              <input id="cf-email" type="email" name="email" class="field<?= $hasErr('email') ?>"<?= $aria('email') ?> value="<?= $val('email') ?>" placeholder="Enter your email" required maxlength="190" autocomplete="email">
              <?= $errText('email') ?>
            </div>
            <div>
              <label class="field-label" for="cf-phone">Phone Number <span class="text-red-500">*</span></label>
              <input id="cf-phone" name="phone" class="field<?= $hasErr('phone') ?>"<?= $aria('phone') ?> value="<?= $val('phone') ?>" placeholder="Enter phone number" required maxlength="40" autocomplete="tel">
              <?= $errText('phone') ?>
            </div>
            <div>
              <label class="field-label" for="cf-country">Country</label>
              <input id="cf-country" name="country" class="field<?= $hasErr('country') ?>" value="<?= $val('country') ?>" placeholder="Your country" maxlength="100" autocomplete="country-name">
            </div>
            <?php if ($isContactPage && !$hasProductCtx): ?>
            <div>
              <label class="field-label" for="cf-type">Enquiry Type</label>
              <select id="cf-type" name="form_key" class="field">
                <?php foreach ($typeOptions as $key => $label): ?>
                <option value="<?= e($key) ?>" <?= $key === (string) $form['form_key'] ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php if ($products !== [] && !$hasProductCtx): ?>
            <div class="sm:col-span-2">
              <label class="field-label" for="cf-product">Product of Interest</label>
              <select id="cf-product" name="product_id" class="field">
                <option value="0">Select a product (optional)</option>
                <?php foreach ($products as $p): ?>
                <option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <?php if ($hasProductCtx): ?>
            <div class="sm:col-span-2">
              <label class="field-label" for="cf-req">Quantity / Requirement</label>
              <input id="cf-req" name="requirement" class="field<?= $hasErr('requirement') ?>"<?= $aria('requirement') ?> value="<?= $val('requirement') ?>" maxlength="255" placeholder="e.g. quantity, pack size, target market">
            </div>
            <?php endif; ?>
          </div>

          <div>
            <label class="field-label" for="cf-message">Your Message</label>
            <textarea id="cf-message" name="message" rows="5" class="js-count field<?= $hasErr('message') ?>"<?= $aria('message') ?> maxlength="5000" data-count-out="cf-message-count" placeholder="Write your message here…"><?= $val('message') ?></textarea>
            <div class="mt-1 flex items-center justify-between">
              <?= $errText('message') ?: '<span></span>' ?>
              <span id="cf-message-count" class="text-xs text-slate-400" aria-hidden="true"></span>
            </div>
          </div>

          <label class="flex items-start gap-3 text-sm text-slate-600">
            <input type="checkbox" name="consent" value="1" class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500"<?= $aria('consent') ?> <?= !empty($old['consent']) ? 'checked' : '' ?> required>
            <span>
              <?php if ($legal !== []): ?>
                I agree to the <?php $li = 0; foreach ($legal as $url => $label): ?><?= $li++ > 0 ? ' and ' : '' ?><a href="<?= e($url) ?>" class="font-medium text-[#0757B8] hover:underline" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?> and consent to being contacted regarding my enquiry. <span class="text-red-500">*</span>
              <?php else: ?>
                I consent to being contacted regarding my enquiry. <span class="text-red-500">*</span>
              <?php endif; ?>
            </span>
          </label>
          <?= $errText('consent') ?>

          <?php if (!empty($form['captcha_enabled']) && !empty($form['captcha_site_key'])): ?>
            <div class="cf-turnstile" data-sitekey="<?= e((string) $form['captcha_site_key']) ?>"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
          <?php endif; ?>

          <div class="flex items-center gap-4">
            <button type="submit" class="btn js-submit min-h-[48px] bg-[#E31B23] px-7 font-bold text-white shadow-[0_12px_24px_-14px_rgba(227,27,35,.8)] hover:-translate-y-px hover:bg-[#c4141b] focus-visible:ring-[#E31B23]">
              <span class="js-submit-label">Send Enquiry</span>
              <span class="js-submit-spinner hidden">Sending…</span>
              <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <p class="text-xs text-slate-400">Your details are used only to respond to your enquiry.</p>
          </div>
        </form>
      </div>

      <!-- ============ Contact Information card ============ -->
      <!-- Stretches to the form's height; rows spread evenly so the column never
           shows dead space below the card. -->
      <aside class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-card sm:p-8">
        <h2 class="font-sans text-xl font-extrabold text-[#0b1f45]">Contact Information</h2>
        <p class="mt-1 text-sm text-slate-500">We are here to help you with your business and product enquiries.</p>
        <ul class="mt-4 flex flex-1 flex-col divide-y divide-slate-100">
          <?php if ($phone !== ''): ?>
          <li class="flex flex-1 items-center gap-4 py-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0757B8]/10 text-[#0757B8]">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.13.97.36 1.9.7 2.8a2 2 0 0 1-.45 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.45c.9.34 1.84.57 2.8.7a2 2 0 0 1 1.7 2.05z"/></svg>
            </span>
            <div><p class="text-sm font-bold text-[#0b1f45]">Call Us</p>
              <a href="tel:<?= e(preg_replace('/[^+\d]/', '', $phone)) ?>" class="text-sm font-medium text-[#0757B8] hover:underline"><?= e($phone) ?></a>
              <?php if ($hours !== ''): ?><p class="mt-0.5 text-xs text-slate-400"><?= e($hours) ?></p><?php endif; ?>
            </div>
          </li>
          <?php endif; ?>
          <?php if ($email !== ''): ?>
          <li class="flex flex-1 items-center gap-4 py-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0757B8]/10 text-[#0757B8]">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            </span>
            <div><p class="text-sm font-bold text-[#0b1f45]">Email Us</p>
              <a href="mailto:<?= e($email) ?>" class="text-sm font-medium text-[#0757B8] hover:underline"><?= e($email) ?></a>
              <p class="mt-0.5 text-xs text-slate-400">We reply within one business day</p>
            </div>
          </li>
          <?php endif; ?>
          <?php if ($addr !== ''): ?>
          <li class="flex flex-1 items-center gap-4 py-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0757B8]/10 text-[#0757B8]">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6.5-5.4-6.5-10.3A6.5 6.5 0 0 1 12 4a6.5 6.5 0 0 1 6.5 6.7C18.5 15.6 12 21 12 21z"/><circle cx="12" cy="10.6" r="2.3"/></svg>
            </span>
            <div><p class="text-sm font-bold text-[#0b1f45]">Our Address</p>
              <p class="text-sm leading-relaxed text-slate-600"><?= nl2br(e($addr)) ?></p>
            </div>
          </li>
          <?php endif; ?>
          <?php if ($hours !== ''): ?>
          <li class="flex flex-1 items-center gap-4 py-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0757B8]/10 text-[#0757B8]">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            </span>
            <div><p class="text-sm font-bold text-[#0b1f45]">Business Hours</p>
              <p class="text-sm leading-relaxed text-slate-600"><?= nl2br(e($hours)) ?></p>
            </div>
          </li>
          <?php endif; ?>
          <?php if (!empty($whatsappLink)): ?>
          <li class="flex flex-1 items-center gap-4 py-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#25D366]/15 text-[#1da851]">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1-.4-.1-.9-.3-1.6-.6-2.8-1.2-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.8 0-1.3.7-2 .9-2.2.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.1.1.3 0 .5l-.4.5-.3.3c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1l.7-.9c.2-.3.4-.2.6-.1l1.9.9c.2.1.4.2.5.3.1.2.1.6-.1 1.1Z"/></svg>
            </span>
            <div><p class="text-sm font-bold text-[#0b1f45]">WhatsApp Us</p>
              <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" data-wa-context="contact" class="text-sm font-medium text-[#1da851] hover:underline"><?= e($phone !== '' ? $phone : 'Chat on WhatsApp') ?></a>
              <p class="mt-0.5 text-xs text-slate-400">Chat with us on WhatsApp</p>
            </div>
          </li>
          <?php endif; ?>
        </ul>
      </aside>
    </div>

    <?php if ($isContactPage && $addr !== ''): ?>
    <!-- ============ Our Locations ============ -->
    <div class="mt-7 grid overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card lg:grid-cols-[2fr_3fr]">
      <div class="p-6 sm:p-8">
        <h2 class="font-sans text-xl font-extrabold text-[#0b1f45]">Our Locations</h2>
        <div class="mt-5 space-y-6">
          <div class="flex items-start gap-3 border-b border-slate-100 pb-6">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#0757B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6.5-5.4-6.5-10.3A6.5 6.5 0 0 1 12 4a6.5 6.5 0 0 1 6.5 6.7C18.5 15.6 12 21 12 21z"/><circle cx="12" cy="10.6" r="2.3"/></svg>
            <div><p class="text-sm font-bold text-[#0757B8]">Registered Office</p>
              <p class="mt-1 text-sm leading-relaxed text-slate-600"><?= nl2br(e($addr)) ?></p></div>
          </div>
          <?php if ($addr2 !== ''): ?>
          <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-[#0757B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6.5-5.4-6.5-10.3A6.5 6.5 0 0 1 12 4a6.5 6.5 0 0 1 6.5 6.7C18.5 15.6 12 21 12 21z"/><circle cx="12" cy="10.6" r="2.3"/></svg>
            <div><p class="text-sm font-bold text-[#0757B8]">Corporate Office</p>
              <p class="mt-1 text-sm leading-relaxed text-slate-600"><?= nl2br(e($addr2)) ?></p></div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="contact-map-bg relative min-h-[260px]">
        <div class="absolute left-1/2 top-1/2 w-[85%] max-w-sm -translate-x-1/2 -translate-y-1/2 rounded-xl bg-white p-5 shadow-card">
          <p class="font-sans text-sm font-extrabold text-[#0b1f45]"><?= e($settings->companyName()) ?></p>
          <p class="mt-1 text-xs leading-relaxed text-slate-500"><?= nl2br(e($addr)) ?></p>
          <?php if ($mapsUrl !== ''): ?>
          <a href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener" class="mt-3 inline-flex items-center gap-1.5 text-sm font-bold text-[#0757B8] hover:underline">Directions
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ============ Partnership band ============ -->
    <div class="mt-7 flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-card sm:p-7 lg:flex-row lg:items-center lg:justify-between">
      <div class="flex items-start gap-4">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#0757B8]/10 text-[#0757B8]">
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/></svg>
        </span>
        <div>
          <h2 class="font-sans text-lg font-extrabold text-[#0b1f45]">Looking for Distributorship or Business Partnership?</h2>
          <p class="mt-1 text-sm text-slate-500">We are always open to new opportunities and long-term partnerships.</p>
        </div>
      </div>
      <div class="flex flex-wrap gap-3">
        <a href="/become-a-distributor" class="btn min-h-[48px] bg-[#E31B23] px-6 font-bold text-white shadow-[0_12px_24px_-14px_rgba(227,27,35,.8)] hover:-translate-y-px hover:bg-[#c4141b] focus-visible:ring-[#E31B23]">Send an Enquiry
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
        <?php if (!empty($whatsappLink)): ?>
        <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" data-wa-context="contact" class="btn min-h-[48px] border-2 border-slate-200 bg-white px-6 font-bold text-[#0b1f45] hover:border-[#25D366]/60 focus-visible:ring-[#25D366]">
          <svg class="h-4 w-4 text-[#25D366]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15l-1.3 4.7 4.8-1.3A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-1.7-.1-.4-.1-.9-.3-1.6-.6-2.8-1.2-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.8 0-1.3.7-2 .9-2.2.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.1.1.3 0 .5l-.4.5-.3.3c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1l.7-.9c.2-.3.4-.2.6-.1l1.9.9c.2.1.4.2.5.3.1.2.1.6-.1 1.1Z"/></svg>
          WhatsApp Us</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
