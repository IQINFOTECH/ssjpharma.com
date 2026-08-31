<?php
/** @var App\Core\View $this
 *  @var int $status @var bool $debug @var string $message @var string $trace */
$this->layout('layouts.base');
$status = $status ?? 500;
$title  = 'Error ' . $status;
?>
<?php $this->start('content'); ?>
    <h1><?= e($status) ?> — Something went wrong</h1>
    <p>An unexpected error occurred. Please try again later.</p>
    <?php if (!empty($debug) && !empty($message)): ?>
        <p style="opacity:.9"><code><?= e($message) ?></code></p>
        <?php if (!empty($trace)): ?>
            <pre style="text-align:left;white-space:pre-wrap;font-size:.7rem;opacity:.6"><?= e($trace) ?></pre>
        <?php endif; ?>
    <?php endif; ?>
<?php $this->stop(); ?>
