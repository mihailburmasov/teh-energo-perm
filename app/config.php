<?php
// Настройки по умолчанию — для работы на своём компьютере.
// На хостинге рядом лежит app/config.local.php (создаётся при deploy.php init из app/config.server.php).
return [
    // Папки. data — единственный источник правды (тексты, каталог, цены, фото).
    // public — статика (css, js, картинки). out — куда пишутся готовые страницы (корень сайта).
    // Если public и out — одна папка (так на хостинге), статика не копируется.
    'data_dir'    => ROOT_DIR . '/data',
    'public_dir'  => ROOT_DIR . '/public',
    'out_dir'     => ROOT_DIR . '/dist',
    'storage_dir' => ROOT_DIR . '/storage',
];
