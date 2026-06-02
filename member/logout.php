<?php
require_once __DIR__ . '/../inc/member.php';
member_logout();
header('Location: ../index.html');
exit;
