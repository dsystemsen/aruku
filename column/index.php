<?php
// aruku コラム一覧 — /column/
require __DIR__ . '/../render.php';
header('Content-Type: text/html; charset=UTF-8');
echo render_column_index();
