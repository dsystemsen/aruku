<?php
// カテゴリ別コラム一覧（note風） — /category/<cat>.html, /category/index.html（すべて）
require __DIR__ . '/render.php';
header('Content-Type: text/html; charset=UTF-8');
$cat = isset($_GET['cat']) ? (string) $_GET['cat'] : '';
echo render_category_columns($cat);
