<?php
// 歩くツール集ページ — /tools.html（.htaccess で tools.php に内部転送）
require __DIR__ . '/render.php';
header('Content-Type: text/html; charset=UTF-8');
echo render_tools();
