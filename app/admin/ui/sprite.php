<?php
// Иконки интерфейса админки: линейные 24×24, через <use href="#i-имя">.
declare(strict_types=1);

const ADMIN_ICONS = [
    'box' => 'M3 7l9-4 9 4v10l-9 4-9-4zM3 7l9 4 9-4M12 11v10',
    'tag' => 'M3 12V3h9l9 9-9 9zM7.5 7.5h.01',
    'phone' => 'M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z',
    'mail' => 'M3 5h18v14H3zM3 6l9 7 9-7',
    'press' => 'M4 21h16M6 21V9h12v12M9 9V4h6v5M12 13v4',
    'laser' => 'M12 2v6M12 22a4 4 0 100-8 4 4 0 000 8zM4 4l3 3M20 4l-3 3',
    'layers' => 'M12 3l9 5-9 5-9-5zM3 13l9 5 9-5',
    'doc' => 'M7 3h8l4 4v14H7zM14 3v5h5M10 13h6M10 17h6',
    'help' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM9.5 9a2.5 2.5 0 015 .5c0 1.5-2.5 2-2.5 3.5M12 17h.01',
    'clock' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2',
    'trash' => 'M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14',
    'gear' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19 12l2-1-1-3-2 .2-1.5-1.5L16.7 4l-3-1-1 2h-1.4l-1-2-3 1 .2 2.7L6 8.2 4 8 3 11l2 1v1.4L3 14l1 3 2-.2 1.5 1.5L7.3 21l3 1 1-2h1.4l1 2 3-1-.2-2.7 1.5-1.5 2 .2 1-3-2-1z',
    'menu' => 'M4 7h16M4 12h16M4 17h16',
    'star' => 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
];

function sprite_svg(): string
{
    $symbols = '';
    foreach (ADMIN_ICONS as $k => $d) $symbols .= '<symbol id="i-' . $k . '" viewBox="0 0 24 24"><path d="' . $d . '"/></symbol>';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $symbols . '</svg>';
}

function icon(string $name): string
{
    return '<svg class="ic" aria-hidden="true" focusable="false"><use href="#i-' . $name . '"/></svg>';
}
