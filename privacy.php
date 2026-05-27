<?php
// aruku プライバシーポリシー — /privacy.html（.htaccess が privacy.php へ内部転送）
require __DIR__ . '/render.php';
$html = render_page('privacy');
if ($html === null) {
    http_response_code(404);
    readfile(__DIR__ . '/404.html');
    exit;
}
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex, follow');
echo $html;
