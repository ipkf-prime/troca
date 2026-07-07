<!-- resources/views/welcome.php -->

<?php ob_start(); ?>

<h1>Welcome to IPKF Framework</h1>

<?php $content = ob_get_clean(); ?>

<?php \IPKF\View\Layout::extend('app', compact('content')); ?>