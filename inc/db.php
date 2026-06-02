<?php
/**
 * aruku 共通DB層（PDO）
 * - 本番(Xserver)：MySQL（inc/db_config.php があればそれを使用）
 * - ローカル開発：SQLite（data/aruku.sqlite。設定不要で動く）
 * 同一の PDO API で扱い、スキーマはドライバ差を吸収して自動作成する。
 */

function aruku_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $cfgFile = __DIR__ . '/db_config.php';
    if (is_file($cfgFile)) {
        // 本番：MySQL（db_config.php は ['host','name','user','pass'] を返す）
        $c = require $cfgFile;
        $dsn = 'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
    } else {
        // ローカル：SQLite
        $dir = dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . $dir . '/aruku.sqlite', null, null, $opts);
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');
    }
    aruku_db_init($pdo);
    return $pdo;
}

/** テーブルを存在しなければ作成（SQLite / MySQL 両対応）。 */
function aruku_db_init(PDO $pdo): void
{
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pk = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $ts = "TEXT NOT NULL DEFAULT (datetime('now'))";
        $real = 'REAL';
    } else {
        $pk = 'INT AUTO_INCREMENT PRIMARY KEY';
        $ts = 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP';
        $real = 'DOUBLE';
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS members (
        id $pk,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        nickname VARCHAR(60) NOT NULL,
        sex VARCHAR(8) NOT NULL DEFAULT 'male',
        weekly_goal INT NOT NULL DEFAULT 0,
        created_at $ts
    )");
    try { $pdo->exec('ALTER TABLE members ADD COLUMN weekly_goal INT NOT NULL DEFAULT 0'); } catch (\Throwable $e) {}

    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id $pk,
        member_id INT NOT NULL,
        log_date DATE NOT NULL,
        activity VARCHAR(20) NOT NULL,
        minutes INT NOT NULL,
        weight $real,
        kcal $real NOT NULL,
        created_at $ts
    )");

    // 会員投稿（コラム）。status: pending（承認待ち）/ published（公開）/ rejected
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id $pk,
        member_id INT NOT NULL,
        title VARCHAR(120) NOT NULL,
        body TEXT NOT NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        image VARCHAR(255),
        category VARCHAR(20),
        created_at $ts,
        published_at DATETIME
    )");
    // 既存テーブルへの列追加（既にあれば例外→無視）
    try { $pdo->exec('ALTER TABLE posts ADD COLUMN image VARCHAR(255)'); } catch (\Throwable $e) {}
    try { $pdo->exec('ALTER TABLE posts ADD COLUMN category VARCHAR(20)'); } catch (\Throwable $e) {}
    try { $pdo->exec('ALTER TABLE posts ADD COLUMN seo_desc VARCHAR(180)'); } catch (\Throwable $e) {}
    try { $pdo->exec('ALTER TABLE posts ADD COLUMN updated_at DATETIME'); } catch (\Throwable $e) {}
    try { $pdo->exec('ALTER TABLE posts ADD COLUMN faq TEXT'); } catch (\Throwable $e) {}

    // いいね（1会員1投稿1回）
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_likes (
        id $pk,
        post_id INT NOT NULL,
        member_id INT NOT NULL,
        created_at $ts
    )");
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_like_unique ON post_likes (post_id, member_id)');

    // コメント（会員限定・即時表示）
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id $pk,
        post_id INT NOT NULL,
        member_id INT NOT NULL,
        body TEXT NOT NULL,
        created_at $ts
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_post ON comments (post_id, id)');

    // 投稿の複数画像
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_images (
        id $pk,
        post_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        sort INT NOT NULL DEFAULT 0,
        created_at $ts
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_images ON post_images (post_id, sort, id)');

    // フォロー（follower が followee をフォロー）
    $pdo->exec("CREATE TABLE IF NOT EXISTS member_follows (
        id $pk,
        follower_id INT NOT NULL,
        followee_id INT NOT NULL,
        created_at $ts
    )");
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_follow_unique ON member_follows (follower_id, followee_id)');

    // 投稿タグ
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_tags (
        id $pk,
        post_id INT NOT NULL,
        tag VARCHAR(40) NOT NULL,
        created_at $ts
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_tags_tag ON post_tags (tag)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_tags_post ON post_tags (post_id)');

    // 通知（user_id 宛て。type: comment/like/follow）
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id $pk,
        user_id INT NOT NULL,
        type VARCHAR(12) NOT NULL,
        actor_id INT NOT NULL,
        post_id INT,
        is_read INT NOT NULL DEFAULT 0,
        created_at $ts
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_notif_user ON notifications (user_id, is_read, id)');
    try { $pdo->exec('ALTER TABLE notifications ADD COLUMN meta VARCHAR(100)'); } catch (\Throwable $e) {}

    // 獲得バッジ（1会員1バッジ1回）
    $pdo->exec("CREATE TABLE IF NOT EXISTS member_badges (
        id $pk,
        member_id INT NOT NULL,
        badge_key VARCHAR(30) NOT NULL,
        created_at $ts
    )");
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_badge_unique ON member_badges (member_id, badge_key)');

    // ブックマーク
    $pdo->exec("CREATE TABLE IF NOT EXISTS bookmarks (
        id $pk,
        member_id INT NOT NULL,
        post_id INT NOT NULL,
        created_at $ts
    )");
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_bookmark_unique ON bookmarks (member_id, post_id)');

    // 通報（target_type: post / comment）
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id $pk,
        target_type VARCHAR(10) NOT NULL,
        target_id INT NOT NULL,
        reporter_id INT NOT NULL,
        reason VARCHAR(200),
        handled INT NOT NULL DEFAULT 0,
        created_at $ts
    )");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_reports ON reports (handled, id)');

    // 集計・取得を速く（存在しなければ作成）
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_logs_member_date ON activity_logs (member_id, log_date)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_status ON posts (status, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_member ON posts (member_id, id)');
}
