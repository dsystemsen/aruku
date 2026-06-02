<?php
require_once __DIR__ . '/../inc/member.php';
require_once __DIR__ . '/../inc/posts.php';

member_session_start();
$me = member_current();
$id = (int) ($_POST['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me && member_csrf_check($_POST['csrf'] ?? null) && $id > 0) {
    if (post_get_published($id)) {
        $was = member_liked($id, (int) $me['id']);
        like_toggle($id, (int) $me['id']);
        if (!$was) {
            notif_create(post_author_id($id), 'like', (int) $me['id'], $id);
        }
    }
}
header('Location: ' . ($id > 0 ? '/posts/' . $id : '/column/index.html'));
exit;
