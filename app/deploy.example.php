<?php
// Пример доступа для app/bin/deploy.php. Скопируйте в app/deploy.local.php (в git не хранится) и заполните.
return [
    'host'     => 'tehenem8.beget.tech', // FTP-сервер Beget
    'user'     => 'tehenem8',
    'pass'     => '',
    'ssl'      => false,
    'root'     => 'teh-enerdgy.ru', // папка сайта от корня FTP (в ней лежат app, data, storage, public_html)
    'docroot'  => 'public_html',    // корень сайта внутри этой папки
    'site_url' => 'https://teh-enerdgy.ru',
    // тот же ключ, что deploy_key в app/config.server.php — по нему сервер пересобирает сайт после заливки
    'deploy_key' => '',
];
