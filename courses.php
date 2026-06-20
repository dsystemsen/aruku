<?php
// ウォーキングコース・スポット ガイド — /courses.html（.htaccess で courses.php に内部転送）
require __DIR__ . '/render.php';
header('Content-Type: text/html; charset=UTF-8');
echo render_courses();
