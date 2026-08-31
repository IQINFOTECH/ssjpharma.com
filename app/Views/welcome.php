<?php
/**
 * Phase-0 placeholder landing. Deliberately contains NO pharmaceutical content —
 * the real homepage is built in Phase 1.
 *
 * @var App\Core\View $this
 * @var string $appName
 */
$this->layout('layouts.base');
$title = $appName . ' — Foundation';
?>
<?php $this->start('content'); ?>
    <h1><?= e($appName) ?></h1>
    <p>Application foundation is running.</p>
    <p>Health status: <code>/health</code></p>
    <span class="tag">Phase 0 · Foundation</span>
<?php $this->stop(); ?>
