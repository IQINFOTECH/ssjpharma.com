<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CaptchaService;
use App\Services\LeadService;
use App\Support\EnquiryType;
use App\Support\RateLimiter;

/**
 * Contact / lead capture handler (foundation for the future CRM).
 *
 * Security: CSRF (global middleware), honeypot, rate limiting, optional
 * Cloudflare Turnstile, server-side validation. Capture-first — the lead is
 * saved before any notification, and spam is stored-but-flagged rather than
 * silently dropped. On error we PRG-redirect back with flashed errors/old input.
 */
final class ContactController extends SiteController
{
    public function submit(Request $request): Response
    {
        /** @var Session $session */
        $session = $this->container->get(Session::class);

        $formKey = (string) $request->input('form_key', 'contact-us');
        $formKey = preg_match('/^[a-z0-9\-]{1,60}$/', $formKey) ? $formKey : 'contact-us';
        $backUrl = '/' . $formKey;

        // Enquiry type + source are derived SERVER-SIDE from form_key (never from
        // a client-supplied "source" field). Product enquiry: the hidden product_id
        // is NOT trusted — validate it against a PUBLISHED product (§18).
        $productId = null;
        $rawProductId = (int) $request->input('product_id', 0);
        if ($rawProductId > 0) {
            $product = $this->container->get(\App\Repositories\ProductRepository::class)->findPublishedById($rawProductId);
            if ($product !== null) {
                $productId = (int) $product['id'];
                $formKey = 'product-enquiry';      // canonical product form key
                // The product-bound form lives on the Contact page (kept off the
                // long product page), so validation errors return there.
                $backUrl = '/contact-us?product=' . $productId;
            }
        }
        // Marker used by the page/catalog renderers to re-show flashed errors.
        $flashKey = $productId !== null ? ('product-' . $productId) : $formKey;

        $input = [
            'name'              => (string) $request->input('name', ''),
            'company'           => (string) $request->input('company', ''),
            'email'             => (string) $request->input('email', ''),
            'phone'             => (string) $request->input('phone', ''),
            'whatsapp'          => (string) $request->input('whatsapp', ''),
            'country'           => (string) $request->input('country', ''),
            'state'             => (string) $request->input('state', ''),
            'city'              => (string) $request->input('city', ''),
            'business_type'     => (string) $request->input('business_type', ''),
            'requirement'       => (string) $request->input('requirement', ''),
            'message'           => (string) $request->input('message', ''),
            'preferred_contact' => (string) $request->input('preferred_contact', ''),
            'consent'           => $request->input('consent') ? '1' : '',
        ];

        // --- Honeypot: a hidden field bots tend to fill. ---
        $honeypot = trim((string) $request->input('company_website', ''));
        $isSpam = $honeypot !== '';

        // --- Rate limiting (per IP): filesystem window + DB backstop. ---
        $ip = $request->ip();
        $limiter = new RateLimiter();
        $allowed = $limiter->attempt('contact:' . $ip, 5, 600); // 5 / 10 min
        /** @var LeadService $leads */
        $leads = $this->container->get(LeadService::class);

        if (!$allowed) {
            $session->flash('contact_errors', ['_form' => 'Too many submissions. Please try again shortly.']);
            $session->flash('contact_old', $input);
            $session->put('contact_form_key', $flashKey);
            return Response::redirect($backUrl);
        }

        // --- Turnstile (if enabled). ---
        /** @var CaptchaService $captcha */
        $captcha = $this->container->get(CaptchaService::class);
        if (!$captcha->verify($request->input('cf-turnstile-response'), $ip)) {
            $session->flash('contact_errors', ['_form' => 'Verification failed. Please try again.']);
            $session->flash('contact_old', $input);
            $session->put('contact_form_key', $flashKey);
            return Response::redirect($backUrl);
        }

        // --- Server-side validation. ---
        $errors = $leads->validate($input);
        if ($errors !== []) {
            $session->flash('contact_errors', $errors);
            $session->flash('contact_old', $input);
            $session->put('contact_form_key', $flashKey);
            return Response::redirect($backUrl);
        }

        // --- Capture-first: store the lead (flag spam, still store). ---
        $meta = [
            'form_key'     => $formKey,
            'product_id'   => $productId,
            'ip'           => $ip,
            'user_agent'   => $request->userAgent(),
            'landing_page' => (string) $request->input('landing_page', $backUrl),
            'source_url'   => (string) ($request->header('Referer') ?? $request->input('landing_page', $backUrl)),
            'referrer'     => (string) ($request->header('Referer') ?? ''),
            'utm_source'   => (string) $request->input('utm_source', ''),
            'utm_medium'   => (string) $request->input('utm_medium', ''),
            'utm_campaign' => (string) $request->input('utm_campaign', ''),
            'utm_term'     => (string) $request->input('utm_term', ''),
            'utm_content'  => (string) $request->input('utm_content', ''),
            'is_spam'      => $isSpam,
        ];

        try {
            $leads->create($input, $meta);
        } catch (\Throwable $e) {
            // Never expose internals; log and show a friendly error.
            $this->container->get(\App\Core\Logger::class)->error('contact.create_failed', ['error' => $e->getMessage()]);
            $session->flash('contact_errors', ['_form' => 'Sorry, something went wrong. Please try again.']);
            $session->flash('contact_old', $input);
            $session->put('contact_form_key', $flashKey);
            return Response::redirect($backUrl);
        }

        // Pass a NON-PII conversion marker to the thank-you page for analytics.
        // app.js fires the matching GA event from this whitelisted ?c= value only.
        $event = match (EnquiryType::resolve($formKey)['type']) {
            'product'      => 'product_enquiry_submit',
            'distributor'  => 'distributor_enquiry_submit',
            'partnership'  => 'partnership_enquiry_submit',
            default        => 'contact_form_submit',
        };
        return Response::redirect('/thank-you?c=' . $event);
    }
}
