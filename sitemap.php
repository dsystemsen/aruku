<?php
// aruku サイトマップ — /sitemap.xml（.htaccess で sitemap.php に内部転送）
require __DIR__ . '/render.php';
header('Content-Type: application/xml; charset=UTF-8');
echo render_sitemap();
