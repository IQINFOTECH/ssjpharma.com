<?php
/** Honest placeholder shown only if no published home page exists yet. @var string $heading */
$this->layout('site.layout');
?>
<?php $this->start('content'); ?>
<section class="py-24">
  <div class="container-x max-w-2xl text-center">
    <span class="eyebrow mb-3">Website</span>
    <h1 class="text-3xl font-semibold sm:text-4xl"><?= e($heading ?? 'Welcome') ?></h1>
    <p class="mt-4 text-slate-600">The home page has not been published yet. Content can be added from the admin.</p>
    <div class="mt-8"><a href="/contact-us" class="btn btn-primary">Contact Us</a></div>
  </div>
</section>
<?php $this->stop(); ?>
