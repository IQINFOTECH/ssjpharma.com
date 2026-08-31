<?php
/**
 * CMS page renderer. Iterates modular sections and includes the matching
 * component partial. The contact form is injected for 'contact' template pages.
 * @var App\Core\View $this
 * @var array $sections  each: ['type'=>string,'data'=>array]
 * @var bool $isContact
 * @var array|null $contactForm
 */
$this->layout('site.layout');
$sectionsDir = __DIR__ . '/sections';
?>
<?php $this->start('content'); ?>

  <?php if (!empty($breadcrumbs) && count($breadcrumbs) > 1): ?>
  <nav class="container-x pt-6 text-sm text-slate-500" aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-1.5">
      <?php foreach ($breadcrumbs as $i => $crumb): ?>
        <li class="flex items-center gap-1.5">
          <?php if ($i < count($breadcrumbs) - 1): ?>
            <a href="<?= e(parse_url($crumb['url'], PHP_URL_PATH) ?: '/') ?>" class="hover:text-brand-600"><?= e($crumb['name']) ?></a>
            <span class="text-slate-300">/</span>
          <?php else: ?>
            <span class="text-slate-700"><?= e($crumb['name']) ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ol>
  </nav>
  <?php endif; ?>

  <?php foreach ($sections as $s):
      $file = $sectionsDir . '/' . preg_replace('/[^a-z_]/', '', $s['type']) . '.php';
      if (!is_file($file)) { continue; }
      $section = $s['data'];
      include $file;
  endforeach; ?>

  <?php if ($isContact && $contactForm !== null):
      $form = $contactForm;
      include $sectionsDir . '/contact_form.php';
  endif; ?>

<?php $this->stop(); ?>
