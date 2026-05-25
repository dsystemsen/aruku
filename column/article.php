<?php
// aruku 個別コラム記事 — /column/<slug>.html（.htaccess で article.php?slug=<slug> に内部転送）
require __DIR__ . '/../render.php';

$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : '';
// slug は [a-z0-9-] のみ許可（不正・パストラバーサル対策）
if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
    $slug = '';
}

$html = $slug !== '' ? render_article($slug) : null;

if ($html === null) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    $notfound = __DIR__ . '/../404.html';
    if (is_file($notfound)) {
        readfile($notfound);
    } else {
        echo '404 Not Found';
    }
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
echo $html;
