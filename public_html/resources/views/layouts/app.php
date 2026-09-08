<!-- resources/views/layouts/app.php -->
<!doctype html>

<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title><?= htmlspecialchars(
        (string) (
            $title
            ?? 'IPKF'
        ),
        ENT_QUOTES
        | ENT_SUBSTITUTE,
        'UTF-8'
    ) ?></title>
</head>

<body dir="rtl">

<?php component('header'); ?>

<div class="container">
    <?= $content ?? '' ?>
</div>

<?php component('footer'); ?>

</body>
</html>
