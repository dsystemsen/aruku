<?php
// コラム内検索 /search.html?q=...
require __DIR__ . '/render.php';
header('Content-Type: text/html; charset=UTF-8');
echo render_search_page(isset($_GET['q']) ? (string) $_GET['q'] : '');
