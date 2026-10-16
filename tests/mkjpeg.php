<?php
// Тестовая картинка для загрузки: php tests/mkjpeg.php -> tests/test.jpg (1600×1200, полосы)
$i = imagecreatetruecolor(1600, 1200);
for ($x = 0; $x < 1600; $x += 40) imagefilledrectangle($i, $x, 0, $x + 20, 1200, imagecolorallocate($i, intdiv($x, 7) % 255, 120, 200));
imagejpeg($i, __DIR__ . '/test.jpg', 85);
