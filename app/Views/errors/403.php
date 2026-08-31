<?php
/** @var App\Core\View $this */
$this->layout('layouts.base');
$title = 'Forbidden';
?>
<?php $this->start('content'); ?>
    <h1>403 — Forbidden</h1>
    <p>You do not have permission to access this resource.</p>
    <p><a href="/" style="color:#9cc4ff">Return home</a></p>
<?php $this->stop(); ?>
