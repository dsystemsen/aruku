<?php
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/posts.php';

member_session_start();
$me = member_current();
$id = (int) ($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me && member_csrf_check($_POST['csrf'] ?? null) && $id > 0) {
    $was = is_following((int) $me['id'], $id);
    follow_toggle((int) $me['id'], $id);
    if (!$was) {
        notif_create($id, 'follow', (int) $me['id'], null);
    }
}
header('Location: ' . ($id > 0 ? '/u/' . $id : '/column/index.html'));
exit;
