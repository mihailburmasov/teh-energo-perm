<?php
// Приём заявок с форм сайта. Обработчик лежит вне корня сайта (папка app рядом с корнем).
if (is_file(__DIR__ . '/../app/lead.php')) { require __DIR__ . '/../app/lead.php'; exit; }
http_response_code(503);
header('Content-Type: application/json; charset=utf-8');
echo '{"ok":false,"error":"Приём заявок временно недоступен. Позвоните нам, пожалуйста."}';
