<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
    <meta charset="UTF-8" />
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0" />
    <title><?php echo __('errors.404_title'); ?></title>
    <link
        rel="stylesheet"
        href="<?php echo getenv('HOST'); ?>/core/styles" />
</head>

<body class="d-flex w-100 h-100 justify-center items-center">
    <div class="d-flex flex-column self-center items-center w-50 border border-primary pb-3">
        <h1 class="text-primary-dark">404</h1>
        <h2 class="text-primary-dark"><?php echo __('errors.404_subtitle'); ?></h2>
        <p>
            <?php echo __('errors.404_description'); ?>
        </p>
        <a
            class="button primary"
            href="<?php echo getenv('HOST'); ?>"><?php echo __('navigation.home'); ?></a>
    </div>
</body>

</html>