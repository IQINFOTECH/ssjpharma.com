<?php
/**
 * Floating WhatsApp click-to-chat button (wa.me only — ADR-001). Renders only
 * when a WhatsApp number is configured in the CMS.
 * @var string $whatsappLink
 */
if (empty($whatsappLink)) {
    return;
}
?>
<a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener"
   class="fixed bottom-5 right-5 z-40 inline-flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg transition hover:scale-105 focus-visible:ring-2 focus-visible:ring-offset-2"
   aria-label="Chat on WhatsApp">
  <svg class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
    <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.28-1.38a9.85 9.85 0 0 0 4.76 1.22h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.13-2.9-7A9.82 9.82 0 0 0 12.04 2Zm5.8 14.14c-.24.68-1.4 1.3-1.93 1.35-.5.05-1.12.08-1.8-.11-.42-.13-.95-.31-1.63-.6-2.87-1.24-4.75-4.13-4.9-4.32-.14-.19-1.17-1.55-1.17-2.96s.74-2.1 1-2.39c.26-.29.57-.36.76-.36.19 0 .38 0 .55.01.18.01.42-.07.65.5.24.58.82 2 .89 2.15.07.14.12.31.02.5-.09.19-.14.31-.28.48-.14.17-.29.38-.42.51-.14.14-.28.29-.12.57.16.29.72 1.19 1.55 1.93 1.06.95 1.96 1.24 2.24 1.38.28.14.44.12.6-.07.17-.19.7-.81.88-1.09.18-.28.36-.23.6-.14.24.09 1.55.73 1.82.86.27.14.44.21.5.32.07.12.07.66-.17 1.34Z"/>
  </svg>
</a>
