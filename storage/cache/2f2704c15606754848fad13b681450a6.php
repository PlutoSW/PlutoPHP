<?php class_exists('Pluto\Core\Template') or exit; ?>
<!DOCTYPE html>
<html lang="<?php echo self::$language->getLanguage(); ?>">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Test Page</title>
		<?php foreach($styles as $style): ?><link rel="stylesheet" href="<?php echo $style; ?>"/>
		<?php endforeach; ?>

	</head>
	<body>
		
		

        <div>
            Example Test Page
        </div>

		
		<?php foreach($scripts as $script): ?><script src="<?php echo $script; ?>"></script>
		<?php endforeach; ?>

	</body>
</html>




