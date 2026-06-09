<?php
/**
 * aruku 会員投稿（コラム）— 作成・取得・承認
 * 本文はプレーンテキストとして保存し、表示時に必ずエスケープ＋nl2br（XSS対策）。
 * 公開は管理者の承認後（status: pending → published）。
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/member.php';

/** 会員投稿のカテゴリ（既存5本柱に合わせる）。 */
function aruku_post_categories(): array
{
    return [
        'koka'      => '効果・効能',
        'calorie'   => '歩数・カロリー',
        'howto'     => '正しい歩き方',
        'poikatsu'  => '歩いてポイ活',
        'machine'   => 'ウォーキングマシン',
        'diet'      => 'ダイエット・減量',
        'shoes'     => 'シューズ・ギア',
        'wear'      => 'ウェア・服装',
        'morning'   => '朝活・習慣化',
        'course'    => 'コース・名所',
        'indoor'    => '室内・雨の日',
        'health'    => '健康数値の改善',
        'mental'    => 'メンタル・ストレス',
        'senior'    => 'シニアの歩行',
        'family'    => '親子・子ども',
        'story'     => '体験談・継続のコツ',
        'app'       => 'アプリ・歩数計',
        'nutrition' => '栄養・食事',
        'stretch'   => 'ストレッチ・ケガ予防',
        'season'    => '季節・天気の歩き方',
        'other'     => 'その他',
    ];
}
/** カテゴリの絵文字（表示用）。 */
function aruku_post_category_emoji(): array
{
    return [
        'koka' => '💚', 'calorie' => '🔥', 'howto' => '👟', 'poikatsu' => '💰', 'machine' => '🏃',
        'diet' => '⚖️', 'shoes' => '🥾', 'wear' => '🧥', 'morning' => '🌅', 'course' => '🗺️',
        'indoor' => '🏠', 'health' => '🩺', 'mental' => '🧠', 'senior' => '👵', 'family' => '👨‍👧',
        'story' => '📔', 'app' => '📱', 'nutrition' => '🥗', 'stretch' => '🤸', 'season' => '🍃',
        'other' => '🗂️',
    ];
}

function post_create(int $memberId, string $title, string $body, ?string $image = null, ?string $category = null, string $status = 'pending'): array
{
    $title = trim($title);
    $body  = trim($body);
    if ($title === '' || mb_strlen($title) > 120) {
        return ['ok' => false, 'error' => 'タイトルは1〜120文字で入力してください。'];
    }
    if ($body === '' || mb_strlen($body) > 20000) {
        return ['ok' => false, 'error' => '本文を入力してください（2万文字以内）。'];
    }
    $cats = aruku_post_categories();
    $category = ($category !== null && isset($cats[$category])) ? $category : null;
    $status = in_array($status, ['pending', 'draft', 'published'], true) ? $status : 'pending';
    $db = aruku_db();
    if ($status === 'published') {
        $st = $db->prepare('INSERT INTO posts (member_id, title, body, status, image, category, published_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$memberId, $title, $body, $status, $image, $category, date('Y-m-d H:i:s')]);
    } else {
        $st = $db->prepare('INSERT INTO posts (member_id, title, body, status, image, category) VALUES (?, ?, ?, ?, ?, ?)');
        $st->execute([$memberId, $title, $body, $status, $image, $category]);
    }
    return ['ok' => true, 'id' => (int) $db->lastInsertId()];
}

/** 自分の投稿を取得（任意のstatus）。所有確認込み。 */
function post_get_owned(int $id, int $memberId): ?array
{
    $st = aruku_db()->prepare('SELECT * FROM posts WHERE id = ? AND member_id = ?');
    $st->execute([$id, $memberId]);
    return $st->fetch() ?: null;
}

/** 自分の投稿を更新。公開済みを編集した場合は status=pending に戻して再審査。 */
function post_update(int $id, int $memberId, string $title, string $body, ?string $image, ?string $category, string $status): array
{
    $title = trim($title);
    $body  = trim($body);
    if ($title === '' || mb_strlen($title) > 120) {
        return ['ok' => false, 'error' => 'タイトルは1〜120文字で入力してください。'];
    }
    if ($body === '' || mb_strlen($body) > 20000) {
        return ['ok' => false, 'error' => '本文を入力してください（2万文字以内）。'];
    }
    $cats = aruku_post_categories();
    $category = ($category !== null && isset($cats[$category])) ? $category : null;
    $status = in_array($status, ['pending', 'draft', 'published'], true) ? $status : 'pending';
    $db = aruku_db();
    $own = $db->prepare('SELECT id FROM posts WHERE id = ? AND member_id = ?');
    $own->execute([$id, $memberId]);
    if (!$own->fetch()) {
        return ['ok' => false, 'error' => '権限がありません。'];
    }
    $pubAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
    $now = date('Y-m-d H:i:s');
    if ($image !== null) {
        $sql = 'UPDATE posts SET title=?, body=?, category=?, status=?, image=?, published_at=?, updated_at=? WHERE id=? AND member_id=?';
        $params = [$title, $body, $category, $status, $image, $pubAt, $now, $id, $memberId];
    } else {
        $sql = 'UPDATE posts SET title=?, body=?, category=?, status=?, published_at=?, updated_at=? WHERE id=? AND member_id=?';
        $params = [$title, $body, $category, $status, $pubAt, $now, $id, $memberId];
    }
    $db->prepare($sql)->execute($params);
    return ['ok' => true];
}

/** 自分の投稿を削除（コメント・いいね・画像も一緒に削除）。 */
function post_delete_owned(int $id, int $memberId): array
{
    $db = aruku_db();
    $st = $db->prepare('SELECT image FROM posts WHERE id = ? AND member_id = ?');
    $st->execute([$id, $memberId]);
    $row = $st->fetch();
    if (!$row) {
        return ['ok' => false];
    }
    // 複数画像のファイルも削除
    $imgs = $db->prepare('SELECT filename FROM post_images WHERE post_id = ?');
    $imgs->execute([$id]);
    foreach ($imgs->fetchAll() as $im) {
        @unlink(dirname(__DIR__) . '/uploads/' . $im['filename']);
    }
    $db->prepare('DELETE FROM posts WHERE id = ? AND member_id = ?')->execute([$id, $memberId]);
    $db->prepare('DELETE FROM comments WHERE post_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM post_likes WHERE post_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM post_images WHERE post_id = ?')->execute([$id]);
    if (!empty($row['image'])) {
        @unlink(dirname(__DIR__) . '/uploads/' . $row['image']);
    }
    return ['ok' => true];
}

/* ===== 複数画像 ===== */
function post_images(int $postId): array
{
    $st = aruku_db()->prepare('SELECT id, filename FROM post_images WHERE post_id = ? ORDER BY sort ASC, id ASC');
    $st->execute([$postId]);
    return $st->fetchAll();
}
function post_add_image(int $postId, string $filename, int $sort = 0): void
{
    aruku_db()->prepare('INSERT INTO post_images (post_id, filename, sort) VALUES (?, ?, ?)')
        ->execute([$postId, $filename, $sort]);
}
/** この投稿の画像1枚を削除（所有確認は呼び出し側で投稿所有を担保）。 */
function post_image_delete(int $imageId, int $postId): void
{
    $db = aruku_db();
    $st = $db->prepare('SELECT filename FROM post_images WHERE id = ? AND post_id = ?');
    $st->execute([$imageId, $postId]);
    $row = $st->fetch();
    if ($row) {
        @unlink(dirname(__DIR__) . '/uploads/' . $row['filename']);
        $db->prepare('DELETE FROM post_images WHERE id = ? AND post_id = ?')->execute([$imageId, $postId]);
    }
}
/** 画像の並び順を更新（所有投稿の画像のみ）。 */
function post_image_set_sort(int $imageId, int $postId, int $sort): void
{
    aruku_db()->prepare('UPDATE post_images SET sort = ? WHERE id = ? AND post_id = ?')
        ->execute([$sort, $imageId, $postId]);
}

/** 投稿の全画像ファイル名（旧 image 列も含めて統合）。 */
function post_all_image_files(array $post): array
{
    $files = [];
    foreach (post_images((int) $post['id']) as $im) {
        $files[] = $im['filename'];
    }
    if (!empty($post['image']) && !in_array($post['image'], $files, true)) {
        array_unshift($files, $post['image']);
    }
    return $files;
}

/** 複数アップロード（$_FILES['image']）を検証・リサイズ保存。['ok','names','error']。 */
function aruku_save_images(array $field, int $max = 5): array
{
    $names = [];
    if (!isset($field['name'])) {
        return ['ok' => true, 'names' => $names];
    }
    $name = (array) $field['name'];
    $count = count($name);
    for ($i = 0; $i < $count; $i++) {
        $err = is_array($field['error']) ? $field['error'][$i] : $field['error'];
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (count($names) >= $max) {
            break;
        }
        $one = [
            'error'    => $err,
            'size'     => is_array($field['size']) ? $field['size'][$i] : $field['size'],
            'tmp_name' => is_array($field['tmp_name']) ? $field['tmp_name'][$i] : $field['tmp_name'],
            'name'     => is_array($field['name']) ? $field['name'][$i] : $field['name'],
        ];
        $r = post_save_image($one);
        if (!$r['ok']) {
            foreach ($names as $n) {
                @unlink(dirname(__DIR__) . '/uploads/' . $n);
            }
            return ['ok' => false, 'error' => $r['error'], 'names' => []];
        }
        if ($r['name']) {
            $names[] = $r['name'];
        }
    }
    return ['ok' => true, 'names' => $names];
}

/* ===== フィード最適化用の集計マップ（N+1回避） ===== */
function aruku_in_placeholders(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}
function like_counts_map(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $st = aruku_db()->prepare('SELECT post_id, COUNT(*) c FROM post_likes WHERE post_id IN (' . aruku_in_placeholders($ids) . ') GROUP BY post_id');
    $st->execute(array_values($ids));
    $m = [];
    foreach ($st as $r) {
        $m[(int) $r['post_id']] = (int) $r['c'];
    }
    return $m;
}
function comment_counts_map(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $st = aruku_db()->prepare('SELECT post_id, COUNT(*) c FROM comments WHERE post_id IN (' . aruku_in_placeholders($ids) . ') GROUP BY post_id');
    $st->execute(array_values($ids));
    $m = [];
    foreach ($st as $r) {
        $m[(int) $r['post_id']] = (int) $r['c'];
    }
    return $m;
}
function post_first_images_map(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $st = aruku_db()->prepare('SELECT post_id, filename FROM post_images WHERE post_id IN (' . aruku_in_placeholders($ids) . ') ORDER BY sort ASC, id ASC');
    $st->execute(array_values($ids));
    $m = [];
    foreach ($st as $r) {
        if (!isset($m[(int) $r['post_id']])) {
            $m[(int) $r['post_id']] = $r['filename'];
        }
    }
    return $m;
}

/* ===== タグ ===== */
function post_set_tags(int $postId, $raw): void
{
    $tags = is_array($raw) ? $raw : preg_split('/[,、\s]+/u', (string) $raw);
    $norm = [];
    foreach ((array) $tags as $t) {
        $t = ltrim(trim(mb_strtolower((string) $t)), '#＃');
        if ($t === '' || mb_strlen($t) > 30) {
            continue;
        }
        if (!in_array($t, $norm, true)) {
            $norm[] = $t;
        }
        if (count($norm) >= 5) {
            break;
        }
    }
    $db = aruku_db();
    $db->prepare('DELETE FROM post_tags WHERE post_id = ?')->execute([$postId]);
    $ins = $db->prepare('INSERT INTO post_tags (post_id, tag) VALUES (?, ?)');
    foreach ($norm as $t) {
        $ins->execute([$postId, $t]);
    }
}
function post_tags(int $postId): array
{
    $st = aruku_db()->prepare('SELECT tag FROM post_tags WHERE post_id = ? ORDER BY id ASC');
    $st->execute([$postId]);
    return array_column($st->fetchAll(), 'tag');
}
function post_tags_map(array $ids): array
{
    if (!$ids) {
        return [];
    }
    $st = aruku_db()->prepare('SELECT post_id, tag FROM post_tags WHERE post_id IN (' . aruku_in_placeholders($ids) . ') ORDER BY id ASC');
    $st->execute(array_values($ids));
    $m = [];
    foreach ($st as $r) {
        $m[(int) $r['post_id']][] = $r['tag'];
    }
    return $m;
}
function tags_popular(int $limit = 20): array
{
    $st = aruku_db()->prepare("SELECT t.tag, COUNT(*) c FROM post_tags t JOIN posts p ON p.id = t.post_id WHERE p.status = 'published' GROUP BY t.tag ORDER BY c DESC, t.tag ASC LIMIT ?");
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/* ===== 検索・タグ/著者別の公開投稿一覧 ===== */
function aruku_posts_select(): string
{
    return 'SELECT p.id, p.title, p.body, p.image, p.category, p.member_id, p.created_at, p.published_at, m.nickname
            FROM posts p JOIN members m ON m.id = p.member_id';
}
function posts_search(string $q, int $limit = 50): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $st = aruku_db()->prepare(aruku_posts_select()
        . " WHERE p.status = 'published' AND (p.title LIKE ? ESCAPE '\\' OR p.body LIKE ? ESCAPE '\\')
            ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT ?");
    $st->bindValue(1, $like, PDO::PARAM_STR);
    $st->bindValue(2, $like, PDO::PARAM_STR);
    $st->bindValue(3, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
function posts_by_tag(string $tag, int $limit = 50): array
{
    $tag = mb_strtolower(trim($tag));
    if ($tag === '') {
        return [];
    }
    $st = aruku_db()->prepare(aruku_posts_select()
        . " JOIN post_tags t ON t.post_id = p.id WHERE p.status = 'published' AND t.tag = ?
            ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT ?");
    $st->bindValue(1, $tag, PDO::PARAM_STR);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
function posts_by_member_published(int $memberId, int $limit = 50): array
{
    $st = aruku_db()->prepare(aruku_posts_select()
        . " WHERE p.member_id = ? AND p.status = 'published'
            ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT ?");
    $st->bindValue(1, $memberId, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/* ===== フォロー ===== */
function follow_toggle(int $follower, int $followee): void
{
    if ($follower === $followee) {
        return;
    }
    $db = aruku_db();
    $st = $db->prepare('SELECT id FROM member_follows WHERE follower_id = ? AND followee_id = ?');
    $st->execute([$follower, $followee]);
    if ($st->fetch()) {
        $db->prepare('DELETE FROM member_follows WHERE follower_id = ? AND followee_id = ?')->execute([$follower, $followee]);
    } else {
        try {
            $db->prepare('INSERT INTO member_follows (follower_id, followee_id) VALUES (?, ?)')->execute([$follower, $followee]);
        } catch (\Throwable $e) {
        }
    }
}
function is_following(int $follower, int $followee): bool
{
    $st = aruku_db()->prepare('SELECT 1 FROM member_follows WHERE follower_id = ? AND followee_id = ?');
    $st->execute([$follower, $followee]);
    return (bool) $st->fetchColumn();
}
function follower_count(int $memberId): int
{
    $st = aruku_db()->prepare('SELECT COUNT(*) FROM member_follows WHERE followee_id = ?');
    $st->execute([$memberId]);
    return (int) $st->fetchColumn();
}
function following_count(int $memberId): int
{
    $st = aruku_db()->prepare('SELECT COUNT(*) FROM member_follows WHERE follower_id = ?');
    $st->execute([$memberId]);
    return (int) $st->fetchColumn();
}
function following_ids(int $memberId): array
{
    $st = aruku_db()->prepare('SELECT followee_id FROM member_follows WHERE follower_id = ?');
    $st->execute([$memberId]);
    return array_map('intval', array_column($st->fetchAll(), 'followee_id'));
}

/** 公開プロフィール用の最小情報。 */
function member_public(int $id): ?array
{
    $st = aruku_db()->prepare('SELECT id, nickname FROM members WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function post_author_id(int $postId): int
{
    $st = aruku_db()->prepare('SELECT member_id FROM posts WHERE id = ?');
    $st->execute([$postId]);
    return (int) $st->fetchColumn();
}

/* ===== 通知 ===== */
function notif_create(int $userId, string $type, int $actorId, ?int $postId = null): void
{
    if ($userId <= 0 || $userId === $actorId) {
        return; // 自分への通知は作らない
    }
    aruku_db()->prepare('INSERT INTO notifications (user_id, type, actor_id, post_id) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $type, $actorId, $postId]);
}
function notifications_for(int $userId, int $limit = 50): array
{
    $st = aruku_db()->prepare(
        'SELECT n.id, n.type, n.post_id, n.is_read, n.created_at, n.meta, m.id AS actor_id, m.nickname AS actor, p.title AS post_title
         FROM notifications n JOIN members m ON m.id = n.actor_id LEFT JOIN posts p ON p.id = n.post_id
         WHERE n.user_id = ? ORDER BY n.id DESC LIMIT ?'
    );
    $st->bindValue(1, $userId, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
/** バッジ獲得の通知（actor は本人＝システム的扱い、meta にバッジ名）。 */
function notif_badge(int $userId, string $label): void
{
    aruku_db()->prepare('INSERT INTO notifications (user_id, type, actor_id, post_id, meta) VALUES (?, ?, ?, NULL, ?)')
        ->execute([$userId, 'badge', $userId, $label]);
}

/** バッジ定義（key => [絵文字, ラベル, 達成済みか]）。表示と付与で共用。 */
function aruku_badge_defs(int $days, float $totalKcal, int $streak, bool $goalReached): array
{
    return [
        'days1'      => ['🌱', 'はじめの一歩', $days >= 1],
        'days7'      => ['🔥', '7日記録', $days >= 7],
        'days30'     => ['🏅', '30日記録', $days >= 30],
        'days100'    => ['👑', '100日記録', $days >= 100],
        'kcal1000'   => ['🍙', '1,000kcal', $totalKcal >= 1000],
        'kcal10000'  => ['💪', '1万kcal', $totalKcal >= 10000],
        'kcal50000'  => ['🏆', '5万kcal', $totalKcal >= 50000],
        'streak3'    => ['⚡', '3日連続', $streak >= 3],
        'streak7'    => ['🌟', '7日連続', $streak >= 7],
        'goal'       => ['🎯', '週間目標達成', $goalReached],
    ];
}

/** 新たに達成したバッジを記録し通知を作成。戻り値は新規獲得ラベル配列。 */
function aruku_award_badges(int $memberId): array
{
    $db = aruku_db();
    $agg = $db->prepare("SELECT COUNT(DISTINCT log_date) d, COALESCE(SUM(kcal),0) t FROM activity_logs WHERE member_id = ?");
    $agg->execute([$memberId]);
    $row = $agg->fetch();
    $days = (int) $row['d'];
    $total = (float) $row['t'];
    $streak = member_streak($memberId);
    $g = $db->prepare('SELECT weekly_goal FROM members WHERE id = ?');
    $g->execute([$memberId]);
    $goal = (int) $g->fetchColumn();
    $weekKcal = logs_kcal_since($memberId, date('Y-m-d', strtotime('monday this week')));
    $goalReached = ($goal > 0 && $weekKcal >= $goal);

    $defs = aruku_badge_defs($days, $total, $streak, $goalReached);
    $have = array_flip(array_column(
        $db->query('SELECT badge_key FROM member_badges WHERE member_id = ' . (int) $memberId)->fetchAll(),
        'badge_key'
    ));
    $ins = $db->prepare('INSERT INTO member_badges (member_id, badge_key) VALUES (?, ?)');
    $new = [];
    foreach ($defs as $key => [$ico, $lbl, $ok]) {
        if ($ok && !isset($have[$key])) {
            try {
                $ins->execute([$memberId, $key]);
                notif_badge($memberId, $ico . ' ' . $lbl);
                $new[] = $lbl;
            } catch (\Throwable $e) {
            }
        }
    }
    return $new;
}

function notif_unread_count(int $userId): int
{
    $st = aruku_db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}
function notif_mark_all_read(int $userId): void
{
    aruku_db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$userId]);
}

/* ===== ブックマーク ===== */
function bookmark_toggle(int $memberId, int $postId): void
{
    $db = aruku_db();
    $st = $db->prepare('SELECT id FROM bookmarks WHERE member_id = ? AND post_id = ?');
    $st->execute([$memberId, $postId]);
    if ($st->fetch()) {
        $db->prepare('DELETE FROM bookmarks WHERE member_id = ? AND post_id = ?')->execute([$memberId, $postId]);
    } else {
        try {
            $db->prepare('INSERT INTO bookmarks (member_id, post_id) VALUES (?, ?)')->execute([$memberId, $postId]);
        } catch (\Throwable $e) {
        }
    }
}
function is_bookmarked(int $memberId, int $postId): bool
{
    $st = aruku_db()->prepare('SELECT 1 FROM bookmarks WHERE member_id = ? AND post_id = ?');
    $st->execute([$memberId, $postId]);
    return (bool) $st->fetchColumn();
}
function bookmarks_for(int $memberId, int $limit = 50): array
{
    $st = aruku_db()->prepare(aruku_posts_select()
        . " JOIN bookmarks b ON b.post_id = p.id WHERE b.member_id = ? AND p.status = 'published'
            ORDER BY b.id DESC LIMIT ?");
    $st->bindValue(1, $memberId, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/* ===== フォロー中タイムライン ===== */
function posts_following_timeline(int $memberId, int $limit = 50): array
{
    $ids = following_ids($memberId);
    if (!$ids) {
        return [];
    }
    $st = aruku_db()->prepare(aruku_posts_select()
        . " WHERE p.status = 'published' AND p.member_id IN (" . aruku_in_placeholders($ids) . ")
            ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT ?");
    $i = 1;
    foreach ($ids as $fid) {
        $st->bindValue($i++, (int) $fid, PDO::PARAM_INT);
    }
    $st->bindValue($i, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/* ===== NGワード ===== */
function aruku_ngwords(): array
{
    // 暴力的・スパム的な語（運用で追記）。
    return [
        '死ね', '殺す', '殺害', '殺人', '爆破', 'テロ', 'レイプ', '暴行', '自殺しろ',
        'spam', 'ｾﾞﾆ', '出会い系', 'アダルト', '裏バイト', '即日融資', '必ず儲',
    ];
}
/** 投稿が要注意（暴力的・スパム）か簡易判定。true なら自動公開せず保留にする。 */
function aruku_post_flagged(string $title, string $body): bool
{
    $text = $title . "\n" . $body;
    if (aruku_has_ngword($text)) {
        return true;
    }
    // リンク過多（スパムの典型）
    if (preg_match_all('#https?://#i', $text) >= 4) {
        return true;
    }
    // スパム的フレーズ
    foreach (['必ず稼', '即金', '副業で月', '簡単に稼', 'カジノ', '無料登録はこちら', 'お金を増やす方法', '高収入'] as $w) {
        if (mb_strpos($text, $w) !== false) {
            return true;
        }
    }
    return false;
}

/** OpenAI APIキー（data/.openai_key または環境変数）。無ければ空文字。 */
function aruku_openai_key(): string
{
    $f = dirname(__DIR__) . '/data/.openai_key';
    if (is_file($f)) {
        $k = trim((string) @file_get_contents($f));
        if ($k !== '') {
            return $k;
        }
    }
    $e = getenv('OPENAI_API_KEY');
    return $e ? trim($e) : '';
}

/**
 * OpenAI Moderation で本文を判定。
 * 戻り値: true=不適切（暴力/ヘイト/性的/自傷 等）/ false=問題なし / null=キー無し・失敗（スキップ）。
 */
function aruku_openai_moderation(string $text): ?bool
{
    $key = aruku_openai_key();
    if ($key === '' || !function_exists('curl_init')) {
        return null;
    }
    $text = trim($text);
    if ($text === '') {
        return false;
    }
    $payload = json_encode(['model' => 'omni-moderation-latest', 'input' => mb_substr($text, 0, 8000)], JSON_UNESCAPED_UNICODE);
    $ch = curl_init('https://api.openai.com/v1/moderations');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_TIMEOUT => 12,
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false || $code !== 200) {
        return null; // 失敗時はスキップ（公開を妨げない）
    }
    $j = json_decode($res, true);
    if (!isset($j['results'][0]['flagged'])) {
        return null;
    }
    return (bool) $j['results'][0]['flagged'];
}
function aruku_has_ngword(string $text): bool
{
    $t = mb_strtolower($text);
    foreach (aruku_ngwords() as $w) {
        if ($w !== '' && mb_strpos($t, mb_strtolower($w)) !== false) {
            return true;
        }
    }
    return false;
}

/* ===== 通報 ===== */
function report_create(string $type, int $targetId, int $reporterId, string $reason = ''): void
{
    $type = in_array($type, ['post', 'comment'], true) ? $type : 'post';
    aruku_db()->prepare('INSERT INTO reports (target_type, target_id, reporter_id, reason) VALUES (?, ?, ?, ?)')
        ->execute([$type, $targetId, $reporterId, mb_substr(trim($reason), 0, 200)]);
}
function reports_open(int $limit = 80): array
{
    $st = aruku_db()->prepare('SELECT r.id, r.target_type, r.target_id, r.reason, r.created_at, m.nickname AS reporter
        FROM reports r JOIN members m ON m.id = r.reporter_id WHERE r.handled = 0 ORDER BY r.id DESC LIMIT ?');
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
function report_handle(int $id): void
{
    aruku_db()->prepare('UPDATE reports SET handled = 1 WHERE id = ?')->execute([$id]);
}
function report_open_count(): int
{
    return (int) aruku_db()->query('SELECT COUNT(*) FROM reports WHERE handled = 0')->fetchColumn();
}
function comment_get(int $id): ?array
{
    $st = aruku_db()->prepare('SELECT id, post_id, body, member_id FROM comments WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* ===== 会員管理（管理者用） ===== */
function members_list(int $limit = 300): array
{
    $st = aruku_db()->prepare('SELECT m.id, m.nickname, m.email, m.sex, m.created_at,
        (SELECT COUNT(*) FROM posts p WHERE p.member_id = m.id) AS posts
        FROM members m ORDER BY m.id DESC LIMIT ?');
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
function member_delete(int $id): void
{
    $db = aruku_db();
    $pids = array_map('intval', array_column(
        $db->query('SELECT id FROM posts WHERE member_id = ' . (int) $id)->fetchAll(), 'id'
    ));
    foreach ($pids as $pid) {
        $imgs = $db->prepare('SELECT filename FROM post_images WHERE post_id = ?');
        $imgs->execute([$pid]);
        foreach ($imgs->fetchAll() as $im) {
            @unlink(dirname(__DIR__) . '/uploads/' . $im['filename']);
        }
    }
    $legacy = $db->prepare('SELECT image FROM posts WHERE member_id = ? AND image IS NOT NULL');
    $legacy->execute([$id]);
    foreach ($legacy->fetchAll() as $lr) {
        @unlink(dirname(__DIR__) . '/uploads/' . $lr['image']);
    }
    if ($pids) {
        $in = implode(',', $pids);
        $db->exec("DELETE FROM post_images WHERE post_id IN ($in)");
        $db->exec("DELETE FROM post_tags WHERE post_id IN ($in)");
        $db->exec("DELETE FROM comments WHERE post_id IN ($in)");
        $db->exec("DELETE FROM post_likes WHERE post_id IN ($in)");
        $db->exec("DELETE FROM bookmarks WHERE post_id IN ($in)");
    }
    $db->prepare('DELETE FROM posts WHERE member_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM comments WHERE member_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM post_likes WHERE member_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM bookmarks WHERE member_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM member_follows WHERE follower_id = ? OR followee_id = ?')->execute([$id, $id]);
    $db->prepare('DELETE FROM notifications WHERE user_id = ? OR actor_id = ?')->execute([$id, $id]);
    $db->prepare('DELETE FROM activity_logs WHERE member_id = ?')->execute([$id]);
    $db->prepare('DELETE FROM members WHERE id = ?')->execute([$id]);
}
function site_stats(): array
{
    $db = aruku_db();
    return [
        'members'  => (int) $db->query('SELECT COUNT(*) FROM members')->fetchColumn(),
        'posts'    => (int) $db->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn(),
        'pending'  => (int) $db->query("SELECT COUNT(*) FROM posts WHERE status = 'pending'")->fetchColumn(),
        'comments' => (int) $db->query('SELECT COUNT(*) FROM comments')->fetchColumn(),
    ];
}

/* ===== コメント通知メール ===== */
function notify_comment(int $postId, int $commenterId): void
{
    $db = aruku_db();
    $st = $db->prepare('SELECT p.title, p.member_id, m.email, m.nickname FROM posts p JOIN members m ON m.id = p.member_id WHERE p.id = ?');
    $st->execute([$postId]);
    $row = $st->fetch();
    if (!$row || (int) $row['member_id'] === $commenterId) {
        return; // 自分の投稿への自コメントは通知しない
    }
    if (!function_exists('mail') || !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
        return;
    }
    $url = function_exists('site') ? (site()['url'] . '/posts/' . $postId) : ('/posts/' . $postId);
    $host = function_exists('site') ? parse_url(site()['url'], PHP_URL_HOST) : 'aruku.ne.jp';
    $subject = '【aruku】あなたのコラムにコメントがつきました';
    $body = $row['nickname'] . " さん\n\nあなたの投稿「" . $row['title'] . "」に新しいコメントがつきました。\n"
        . $url . "\n\n— あるく";
    $headers = 'From: no-reply@' . $host . "\r\nContent-Type: text/plain; charset=UTF-8";
    @mail($row['email'], '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

/* ===== いいね ===== */
function like_toggle(int $postId, int $memberId): void
{
    $db = aruku_db();
    $st = $db->prepare('SELECT id FROM post_likes WHERE post_id = ? AND member_id = ?');
    $st->execute([$postId, $memberId]);
    if ($st->fetch()) {
        $db->prepare('DELETE FROM post_likes WHERE post_id = ? AND member_id = ?')->execute([$postId, $memberId]);
    } else {
        try {
            $db->prepare('INSERT INTO post_likes (post_id, member_id) VALUES (?, ?)')->execute([$postId, $memberId]);
        } catch (\Throwable $e) {
            // 二重押下の競合は無視
        }
    }
}
function like_count(int $postId): int
{
    $st = aruku_db()->prepare('SELECT COUNT(*) FROM post_likes WHERE post_id = ?');
    $st->execute([$postId]);
    return (int) $st->fetchColumn();
}
function member_liked(int $postId, int $memberId): bool
{
    $st = aruku_db()->prepare('SELECT 1 FROM post_likes WHERE post_id = ? AND member_id = ?');
    $st->execute([$postId, $memberId]);
    return (bool) $st->fetchColumn();
}

/* ===== コメント（会員限定・即時表示） ===== */
function comment_add(int $postId, int $memberId, string $body): array
{
    $body = trim($body);
    if ($body === '') {
        return ['ok' => false, 'error' => 'コメントを入力してください。'];
    }
    if (mb_strlen($body) > 2000) {
        return ['ok' => false, 'error' => 'コメントは2000文字以内で入力してください。'];
    }
    if (aruku_has_ngword($body)) {
        return ['ok' => false, 'error' => '不適切な語句が含まれているため投稿できません。'];
    }
    aruku_db()->prepare('INSERT INTO comments (post_id, member_id, body) VALUES (?, ?, ?)')
        ->execute([$postId, $memberId, $body]);
    return ['ok' => true];
}
function comments_for(int $postId): array
{
    $st = aruku_db()->prepare(
        'SELECT c.id, c.body, c.created_at, c.member_id, m.nickname
         FROM comments c JOIN members m ON m.id = c.member_id
         WHERE c.post_id = ? ORDER BY c.id ASC'
    );
    $st->execute([$postId]);
    return $st->fetchAll();
}
function comment_count(int $postId): int
{
    $st = aruku_db()->prepare('SELECT COUNT(*) FROM comments WHERE post_id = ?');
    $st->execute([$postId]);
    return (int) $st->fetchColumn();
}
/** 自分のコメントを削除。 */
function comment_delete_owned(int $id, int $memberId): void
{
    aruku_db()->prepare('DELETE FROM comments WHERE id = ? AND member_id = ?')->execute([$id, $memberId]);
}

/**
 * アップロード画像を検証して uploads/ に保存。
 * 戻り値: ['ok'=>true,'name'=>filename|null]（画像なしは name=null）/ ['ok'=>false,'error'=>...]
 * 検証: 実体が画像か(getimagesize)・形式(JPEG/PNG/WebP/GIF)・サイズ(4MB)。保存名はランダム。
 */
function post_save_image(array $file): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'name' => null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => '画像のアップロードに失敗しました（サイズ制限超過の可能性）。'];
    }
    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        return ['ok' => false, 'error' => '画像は4MB以内にしてください。'];
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return ['ok' => false, 'error' => '画像ファイルを認識できませんでした。'];
    }
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $ext = $extMap[$info['mime']] ?? null;
    if ($ext === null) {
        return ['ok' => false, 'error' => '対応していない画像形式です（JPEG / PNG / WebP / GIF のみ）。'];
    }
    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = date('Ym') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    // GDがあれば最大1600pxにリサイズして再エンコード（EXIF除去・軽量化）。失敗時はそのまま保存。
    $resized = aruku_resize_save($file['tmp_name'], $dest, $info['mime']);
    if (!$resized) {
        $moved = is_uploaded_file($file['tmp_name'])
            ? @move_uploaded_file($file['tmp_name'], $dest)
            : @rename($file['tmp_name'], $dest); // CLIテスト用フォールバック
        if (!$moved) {
            return ['ok' => false, 'error' => '画像の保存に失敗しました。'];
        }
    }
    @chmod($dest, 0644);
    return ['ok' => true, 'name' => $name];
}

/** GDで最大辺 $maxDim に縮小して保存・再エンコード。GIFや非対応・失敗時は false（呼び出し側がそのまま保存）。 */
function aruku_resize_save(string $src, string $dest, string $mime, int $maxDim = 1600): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($src); break;
        case 'image/png':  $img = @imagecreatefrompng($src); break;
        case 'image/webp': $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
        default: return false; // GIF等はリサイズせず原本を保存
    }
    if (!$img) {
        return false;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $maxDim / max($w, $h));
    if ($scale < 1) {
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $tr = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $tr);
        }
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }
    $ok = false;
    switch ($mime) {
        case 'image/jpeg': $ok = imagejpeg($img, $dest, 82); break;
        case 'image/png':  $ok = imagepng($img, $dest, 6); break;
        case 'image/webp': $ok = function_exists('imagewebp') ? imagewebp($img, $dest, 82) : false; break;
    }
    imagedestroy($img);
    return (bool) $ok;
}

/* ===== 管理者向け：コメント・ランキング ===== */
function comments_recent(int $limit = 80): array
{
    $st = aruku_db()->prepare(
        'SELECT c.id, c.body, c.created_at, c.post_id, m.nickname, m.email, p.title AS post_title
         FROM comments c JOIN members m ON m.id = c.member_id JOIN posts p ON p.id = c.post_id
         ORDER BY c.id DESC LIMIT ?'
    );
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}
function comment_delete_admin(int $id): void
{
    aruku_db()->prepare('DELETE FROM comments WHERE id = ?')->execute([$id]);
}

/** いいね数ランキング（公開済み・1件以上）。$sinceDays 指定で期間内のいいねのみ集計。 */
function posts_top_liked(int $limit = 5, ?int $sinceDays = null): array
{
    $join = 'LEFT JOIN post_likes pl ON pl.post_id = p.id';
    $since = null;
    if ($sinceDays !== null) {
        $join = 'LEFT JOIN post_likes pl ON pl.post_id = p.id AND pl.created_at >= ?';
        $since = date('Y-m-d H:i:s', time() - $sinceDays * 86400);
    }
    $sql = "SELECT p.id, p.title, p.image, p.category, m.nickname, COUNT(pl.id) AS likes
            FROM posts p JOIN members m ON m.id = p.member_id
            $join
            WHERE p.status = 'published'
            GROUP BY p.id, p.title, p.image, p.category, m.nickname
            HAVING COUNT(pl.id) > 0
            ORDER BY likes DESC, p.id DESC
            LIMIT ?";
    $st = aruku_db()->prepare($sql);
    $i = 1;
    if ($since !== null) {
        $st->bindValue($i++, $since, PDO::PARAM_STR);
    }
    $st->bindValue($i, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function post_get_published(int $id): ?array
{
    $st = aruku_db()->prepare(
        "SELECT p.id, p.title, p.body, p.image, p.category, p.seo_desc, p.faq, p.member_id, p.created_at, p.published_at, p.updated_at, m.nickname
         FROM posts p JOIN members m ON m.id = p.member_id
         WHERE p.id = ? AND p.status = 'published'"
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function posts_published(int $limit = 50, ?string $category = null, string $sort = 'new', int $offset = 0): array
{
    $cats = aruku_post_categories();
    $category = ($category !== null && isset($cats[$category])) ? $category : null;
    $likeSel = $sort === 'popular' ? ', (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.id) AS likes' : '';
    $orderBy = $sort === 'popular'
        ? 'ORDER BY likes DESC, p.id DESC'
        : 'ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC';
    $sql = "SELECT p.id, p.title, p.body, p.image, p.category, p.member_id, p.created_at, p.published_at, p.updated_at, m.nickname" . $likeSel . "
            FROM posts p JOIN members m ON m.id = p.member_id
            WHERE p.status = 'published'";
    if ($category !== null) {
        $sql .= ' AND p.category = ?';
    }
    $sql .= " $orderBy LIMIT ? OFFSET ?";
    $st = aruku_db()->prepare($sql);
    $i = 1;
    if ($category !== null) {
        $st->bindValue($i++, $category, PDO::PARAM_STR);
    }
    $st->bindValue($i++, $limit, PDO::PARAM_INT);
    $st->bindValue($i, $offset, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function posts_published_count(?string $category = null): int
{
    $cats = aruku_post_categories();
    $category = ($category !== null && isset($cats[$category])) ? $category : null;
    $sql = "SELECT COUNT(*) FROM posts WHERE status = 'published'";
    $p = [];
    if ($category !== null) {
        $sql .= ' AND category = ?';
        $p[] = $category;
    }
    $st = aruku_db()->prepare($sql);
    $st->execute($p);
    return (int) $st->fetchColumn();
}

/** 関連記事（同カテゴリ・自分以外）。 */
function posts_related(int $postId, ?string $category, int $limit = 4): array
{
    if (!$category) {
        return [];
    }
    $st = aruku_db()->prepare(aruku_posts_select()
        . " WHERE p.status = 'published' AND p.category = ? AND p.id <> ?
            ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC LIMIT ?");
    $st->bindValue(1, $category, PDO::PARAM_STR);
    $st->bindValue(2, $postId, PDO::PARAM_INT);
    $st->bindValue(3, $limit, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function posts_by_member(int $memberId): array
{
    $st = aruku_db()->prepare(
        'SELECT id, title, status, created_at, published_at FROM posts WHERE member_id = ? ORDER BY id DESC'
    );
    $st->execute([$memberId]);
    return $st->fetchAll();
}

function posts_by_status(string $status): array
{
    $st = aruku_db()->prepare(
        "SELECT p.id, p.title, p.body, p.created_at, p.published_at, m.nickname, m.email
         FROM posts p JOIN members m ON m.id = p.member_id
         WHERE p.status = ? ORDER BY p.id DESC"
    );
    $st->execute([$status]);
    return $st->fetchAll();
}

function post_set_status(int $id, string $status): void
{
    $status = in_array($status, ['pending', 'published', 'rejected'], true) ? $status : 'pending';
    if ($status === 'published') {
        $st = aruku_db()->prepare('UPDATE posts SET status = ?, published_at = CURRENT_TIMESTAMP WHERE id = ?');
    } else {
        $st = aruku_db()->prepare('UPDATE posts SET status = ? WHERE id = ?');
    }
    $st->execute([$status, $id]);
}

/** 投稿を完全削除（運営判断用）。関連データ・添付画像も削除。 */
function post_delete_admin(int $id): void
{
    $db = aruku_db();
    foreach ($db->query('SELECT filename FROM post_images WHERE post_id = ' . (int) $id)->fetchAll(PDO::FETCH_COLUMN) as $fn) {
        @unlink(dirname(__DIR__) . '/uploads/' . $fn);
    }
    foreach (['comments', 'post_likes', 'post_images', 'post_tags', 'bookmarks', 'reports', 'notifications'] as $t) {
        try { $db->prepare("DELETE FROM {$t} WHERE post_id = ?")->execute([$id]); } catch (Exception $e) {}
    }
    $db->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
}

function post_delete(int $id): void
{
    $st = aruku_db()->prepare('DELETE FROM posts WHERE id = ?');
    $st->execute([$id]);
}

/** 本文の抜粋（プレーンテキスト・安全）。 */
function post_excerpt(string $body, int $len = 96): string
{
    // Markdown記法を素のテキストに（リンクは表示文字だけ残す）
    $body = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $body) ?? $body;
    $body = preg_replace('/[#*`>_~]+/u', '', $body) ?? $body;
    $t = preg_replace('/\s+/u', ' ', trim($body));
    if (!is_string($t)) { // 不正UTF-8等で /u が失敗した場合のフォールバック
        $t = trim((string) preg_replace('/\s+/', ' ', $body));
    }
    if (mb_strlen($t) > $len) {
        $t = mb_substr($t, 0, $len) . '…';
    }
    return $t;
}

/** 本文を安全にHTML化（Markdownサブセット。先にエスケープしてからタグ化＝XSS対策）。 */
function post_body_html(string $body, string $prefix = ''): string
{
    return aruku_markdown($body, $prefix);
}

/** インライン記法（太字/斜体/コード/リンク/画像）。入力は既にHTMLエスケープ済み。 */
function aruku_md_inline(string $s, string $prefix = ''): string
{
    // 画像 ![alt](url) — ローカル uploads/ または http(s) のみ許可（リンクより先に処理）
    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/u', static function ($m) use ($prefix) {
        $url = $m[2];
        if (!preg_match('#^(https?://|(\.\./)?uploads/)#u', $url)) {
            return $m[0]; // 不許可URLは記法のまま残す
        }
        // 相対 uploads/ はページのprefixで解決（記事ページ /posts/ID は ../）
        if ($prefix !== '' && strpos($url, 'uploads/') === 0) {
            $url = $prefix . $url;
        }
        return '<img src="' . $url . '" alt="' . $m[1] . '" loading="lazy" class="post-inline-img">';
    }, $s);
    // リンク [text](url) — 外部は http(s)（別タブ・nofollow）、内部は相対パス(.html/.php)のみ許可。画像(![..])は除外。
    $s = preg_replace_callback('/(?<!!)\[([^\]]+)\]\(([^)\s]+)\)/u', static function ($m) {
        $text = $m[1];
        $url  = $m[2];
        if (preg_match('#^https?://#u', $url)) {
            return '<a href="' . $url . '" target="_blank" rel="noopener nofollow">' . $text . '</a>';
        }
        // 内部の相対リンク（../ ./ から始まる .html/.php。危険スキームは不可）
        if (preg_match('#^(\.\.?/)*[A-Za-z0-9._~/\-]+\.(html|php)(\#[A-Za-z0-9._\-]*)?$#u', $url)) {
            return '<a href="' . $url . '">' . $text . '</a>';
        }
        return $m[0]; // 不許可はそのまま
    }, $s);
    $s = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $s);          // **太字**
    $s = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/u', '<em>$1</em>', $s);        // *斜体*
    $s = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $s);                     // `コード`
    return $s;
}

/** Markdownサブセット → 安全なHTML。$prefix は相対 uploads/ 画像の解決用。 */
function aruku_markdown(string $md, string $prefix = ''): string
{
    $md = str_replace("\r\n", "\n", $md);
    $esc = htmlspecialchars($md, ENT_QUOTES, 'UTF-8'); // 先に全エスケープ（ユーザーHTMLは無効化）
    $lines = explode("\n", $esc);
    $html = '';
    $para = [];
    $inUl = false;
    $inOl = false;
    $flushPara = static function () use (&$html, &$para) {
        if ($para) {
            $html .= '<p>' . implode("<br>\n", $para) . '</p>';
            $para = [];
        }
    };
    $closeLists = static function () use (&$html, &$inUl, &$inOl) {
        if ($inUl) { $html .= '</ul>'; $inUl = false; }
        if ($inOl) { $html .= '</ol>'; $inOl = false; }
    };
    foreach ($lines as $line) {
        $t = rtrim($line);
        if (trim($t) === '') { $flushPara(); $closeLists(); continue; }
        if (preg_match('/^###\s+(.+)$/u', $t, $m)) { $flushPara(); $closeLists(); $html .= '<h3>' . aruku_md_inline($m[1], $prefix) . '</h3>'; continue; }
        if (preg_match('/^##\s+(.+)$/u', $t, $m))  { $flushPara(); $closeLists(); $html .= '<h2>' . aruku_md_inline($m[1], $prefix) . '</h2>'; continue; }
        if (preg_match('/^&gt;\s?(.*)$/u', $t, $m)) { $flushPara(); $closeLists(); $html .= '<blockquote>' . aruku_md_inline($m[1], $prefix) . '</blockquote>'; continue; }
        if (preg_match('/^(?:-|\*)\s+(.+)$/u', $t, $m)) { $flushPara(); if (!$inUl) { $closeLists(); $html .= '<ul>'; $inUl = true; } $html .= '<li>' . aruku_md_inline($m[1], $prefix) . '</li>'; continue; }
        if (preg_match('/^\d+\.\s+(.+)$/u', $t, $m)) { $flushPara(); if (!$inOl) { $closeLists(); $html .= '<ol>'; $inOl = true; } $html .= '<li>' . aruku_md_inline($m[1], $prefix) . '</li>'; continue; }
        $closeLists();
        $para[] = aruku_md_inline($t, $prefix);
    }
    $flushPara();
    $closeLists();
    return $html;
}

/** ニックネームの頭文字（アバター用）。 */
function post_avatar_char(string $nickname): string
{
    $n = trim($nickname);
    return $n === '' ? '?' : mb_substr($n, 0, 1);
}
