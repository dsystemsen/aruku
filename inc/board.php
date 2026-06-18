<?php
/**
 * 誰でも掲示板（つぶやき掲示板）— ログイン不要の匿名短文ボード。
 * 「あるく」ことのなんでも・つぶやきを気軽に。愚痴ではなく前向きな一歩を。
 * セキュリティ：CSRF・ハニーポット・レート制限・URL禁止・出力は必ず h() でエスケープ。
 */
require_once __DIR__ . '/db.php';

const BOARD_BODY_MAX   = 140;   // 本文の最大文字数
const BOARD_NICK_MAX   = 20;    // ニックネームの最大文字数
const BOARD_MIN_GAP    = 15;    // 同一端末の連続投稿の最小間隔（秒）
const BOARD_DAILY_CAP  = 50;    // 同一端末の1日あたり投稿上限
const BOARD_FEED_LIMIT = 30;    // トップに表示する件数

/** IPを日替わりソルトでハッシュ化（生IPは保存しない）。 */
function board_ip_hash(string $ip): string
{
    $day = gmdate('Y-m-d');
    return hash('sha256', 'aruku-board|' . $day . '|' . $ip);
}

/** 同じ人・同じ日でそろう短い匿名タグ（例: 名無し-7F3A2）。 */
function board_author_tag(string $ipHash): string
{
    return strtoupper(substr($ipHash, 0, 5));
}

/** 相対時刻の表示（たった今 / n分前 / n時間前 / n日前）。 */
function board_relative_time(string $ts): string
{
    $t = strtotime($ts . ' UTC');
    if ($t === false) {
        $t = strtotime($ts);
    }
    $diff = time() - (int) $t;
    if ($diff < 0)      { $diff = 0; }
    if ($diff < 60)     { return 'たった今'; }
    if ($diff < 3600)   { return floor($diff / 60) . '分前'; }
    if ($diff < 86400)  { return floor($diff / 3600) . '時間前'; }
    if ($diff < 2592000){ return floor($diff / 86400) . '日前'; }
    return date('Y/m/d', (int) $t);
}

/** 直近の投稿を新着順で取得。 */
function board_recent(int $limit = BOARD_FEED_LIMIT): array
{
    $limit = max(1, min(100, $limit));
    $st = aruku_db()->prepare(
        'SELECT id, nickname, author_tag, body, created_at
         FROM board_posts WHERE hidden = 0 ORDER BY created_at DESC, id DESC LIMIT ' . $limit
    );
    $st->execute();
    return $st->fetchAll() ?: [];
}

/** 公開中の総件数。 */
function board_count(): int
{
    return (int) aruku_db()->query('SELECT COUNT(*) FROM board_posts WHERE hidden = 0')->fetchColumn();
}

/**
 * 投稿を作成。戻り値 ['ok'=>bool, 'error'=>string]
 */
function board_create(string $nickname, string $body, string $ip): array
{
    // 不正なUTF-8は受け付けない（文字化け投稿・preg失敗を防ぐ）
    if (!mb_check_encoding($nickname, 'UTF-8') || !mb_check_encoding($body, 'UTF-8')) {
        return ['ok' => false, 'error' => '文字コードが正しくありません。もう一度入力してください。'];
    }
    // 正規化（preg失敗時のnullは空文字に丸める）
    $nickname = trim((string) preg_replace('/\s+/u', ' ', strip_tags($nickname)));
    $body     = trim((string) preg_replace('/[ \t]+/u', ' ', strip_tags($body)));
    $body     = (string) preg_replace('/\n{3,}/u', "\n\n", $body);

    if ($body === '') {
        return ['ok' => false, 'error' => 'つぶやきを入力してください。'];
    }
    if (mb_strlen($body) > BOARD_BODY_MAX) {
        return ['ok' => false, 'error' => 'つぶやきは' . BOARD_BODY_MAX . '文字以内でお願いします。'];
    }
    if (mb_strlen($nickname) > BOARD_NICK_MAX) {
        $nickname = mb_substr($nickname, 0, BOARD_NICK_MAX);
    }
    if ($nickname === '') {
        $nickname = '名無しの歩行者';
    }
    // スパム対策：URL・連絡先らしき文字列は弾く
    if (preg_match('~https?://|www\.|\.com|\.net|\.jp/|＠|@[\w.-]+\.~iu', $body . ' ' . $nickname)) {
        return ['ok' => false, 'error' => 'URLや連絡先を含む投稿はできません。歩くことのつぶやきを気軽にどうぞ。'];
    }

    $ipHash = board_ip_hash($ip);
    $pdo = aruku_db();

    // レート制限：直近の投稿との間隔
    $st = $pdo->prepare('SELECT created_at FROM board_posts WHERE ip_hash = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$ipHash]);
    $last = $st->fetchColumn();
    if ($last) {
        $t = strtotime($last . ' UTC') ?: strtotime($last);
        if (time() - (int) $t < BOARD_MIN_GAP) {
            return ['ok' => false, 'error' => '投稿の間隔が短すぎます。少し時間をおいてからお試しください。'];
        }
    }
    // 1日あたりの上限
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM board_posts WHERE ip_hash = ? AND created_at >= ?"
    );
    $since = gmdate('Y-m-d H:i:s', time() - 86400);
    $st->execute([$ipHash, $since]);
    if ((int) $st->fetchColumn() >= BOARD_DAILY_CAP) {
        return ['ok' => false, 'error' => '本日の投稿上限に達しました。また明日お気軽にどうぞ。'];
    }

    $tag = board_author_tag($ipHash);
    $ins = $pdo->prepare(
        'INSERT INTO board_posts (nickname, author_tag, body, ip_hash) VALUES (?, ?, ?, ?)'
    );
    $ins->execute([$nickname, $tag, $body, $ipHash]);
    return ['ok' => true];
}
