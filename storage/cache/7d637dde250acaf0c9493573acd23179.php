<?php class_exists('Pluto\Core\Template') or exit; ?>
<!DOCTYPE html>
<html lang="<?php echo self::$language->getLanguage(); ?>">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Ana Sayfa</title>
		<?php foreach($styles as $style): ?><link rel="stylesheet" href="<?php echo $style; ?>"/>
		<?php endforeach; ?>

	</head>
	<body>
		
		

	Example User Page<br>


	

	<?php foreach($users as $user): ?>
	<div>
		<form action="/user/update" method="post">
			<input type="hidden" name="id" value="<?php echo $user->id; ?>"><br>
			<b>Name:</b>
			<input type="text" value="<?php echo $user->name; ?>" name="name"><br>
			<b>Email:</b>
			<input type="text" value="<?php echo $user->email; ?>" name="email"><br>
			<b>Password:</b>
			<input type="text" value="<?php echo $user->password; ?>" name="password"><br>
			<input type="submit" value="Update">
			<button type="button" data-id="updateWithApi">Update with API</button>
		</form>
	</div>
	<?php endforeach; ?>



		
		<?php foreach($scripts as $script): ?><script src="<?php echo $script; ?>"></script>
		<?php endforeach; ?>

	</body>
</html>





