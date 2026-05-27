<?php
// aruku 運営者情報ページ — /about.html（.htaccess が about.php へ内部転送）
require __DIR__ . '/render.php';
$html = render_page('about');
if ($html === null) {
    http_response_code(404);
    readfile(__DIR__ . '/404.html');
    exit;
}
header('Content-Type: text/html; charset=UTF-8');
echo $html;
