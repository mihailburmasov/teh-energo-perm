<?php /** @var string|null $error */ /** @var string $prefix */ load_site_data(); ?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Вход — управление сайтом <?= esc(S::$c['brand']) ?></title>
<link rel="stylesheet" href="<?= $prefix ?>/asset/admin.css?v=<?= filemtime(__DIR__ . '/admin.css') ?>">
</head>
<body class="login-page">
<form class="login" method="post" action="<?= $prefix ?>/login">
  <div class="login__logo"><?= LOGO_MARK ?><b><?= esc(S::$c['brand']) ?></b><span>управление сайтом</span></div>
  <?php if (!admin_account()): ?>
    <p class="alert alert--err">Учётная запись ещё не создана. Создайте её командой <code>php app/bin/admin-setup.php</code>.</p>
  <?php else: ?>
    <?php if ($error): ?><p class="alert alert--err" role="alert"><?= esc($error) ?></p><?php endif; ?>
    <label class="fld"><span class="fld__label">Пароль</span><input type="password" name="password" autocomplete="current-password" required autofocus></label>
    <button class="btn btn--primary btn--block" type="submit">Войти</button>
  <?php endif; ?>
</form>
</body>
</html>
