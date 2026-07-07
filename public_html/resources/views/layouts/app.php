<!-- resources/views/layouts/app.php -->

<html>
<head>
    <title><?= $title ?? 'IPKF' ?></title>
</head>

<body>

<?php component('header'); ?>

<div class="container">

    <?= $content ?? '' ?>

</div>

<?php component('footer'); ?>

</body>
</html>