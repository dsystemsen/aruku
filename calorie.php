<?php
// 消費カロリー専用ハブページ — /calorie-table.html（.htaccess で calorie.php に内部転送）
require __DIR__ . '/render.php';
header('Content-Type: text/html; charset=UTF-8');
echo render_calorie();
