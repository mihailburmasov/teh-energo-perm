<?php
// Точка подключения ядра: пути, настройки, общие функции.
// Настройки по умолчанию — app/config.php, серверные и секретные — app/config.local.php (в git не хранится).
declare(strict_types=1);

mb_internal_encoding('UTF-8');
// Дробные числа в JSON — кратчайшей записью (58.04804, а не 58.0480400000000003…): на Beget по умолчанию другое значение
ini_set('serialize_precision', '-1');
date_default_timezone_set('Asia/Yekaterinburg'); // Пермь

define('APP_DIR', __DIR__);
define('ROOT_DIR', dirname(__DIR__));

function cfg(?string $key = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require APP_DIR . '/config.php';
        if (is_file(APP_DIR . '/config.local.php')) $cfg = array_replace($cfg, require APP_DIR . '/config.local.php');
        if (getenv('SITE_URL') !== false) $cfg['site_url'] = getenv('SITE_URL');
        if (getenv('OUT_DIR') !== false) $cfg['out_dir'] = getenv('OUT_DIR');
        if (getenv('STORAGE_DIR') !== false) $cfg['storage_dir'] = getenv('STORAGE_DIR');
    }
    return $key === null ? $cfg : ($cfg[$key] ?? null);
}

require_once APP_DIR . '/lib/fs.php';
require_once APP_DIR . '/lib/render.php';
require_once APP_DIR . '/lib/pages.php';
require_once APP_DIR . '/lib/build.php';
