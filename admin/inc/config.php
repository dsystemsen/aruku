<?php
/**
 * aruku 管理画面 — 認証設定
 * ※ .htaccess（^admin/inc/ を Forbidden）により直接アクセス不可。
 *
 * パスワードを変更するには、新しいハッシュを生成して 'password_hash' を差し替える:
 *   php -r "echo password_hash('新しいパスワード', PASSWORD_BCRYPT);"
 */
return [
    'email'          => 'yugo_saitou_g@dsystemsen.com',
    // bcrypt ハッシュ（平文は保存しない）
    'password_hash'  => '$2y$10$Nrip6Wsj8Fu6BjVPmhos9uloDBaKOc8oVMAUJ0r2TdceYfwuAMQgu',
    'session_name'   => 'aruku_admin',
    // CSRF/署名用の秘密値（このサイト固有・流出させない）
    'secret'         => 'b1f9a3c7e5d28406af1c9b7d3e6052a8c4f0e9d7b25a1c6f8309e4d7a2b5c8f1e',
    // ログイン試行制限
    'login_max_attempts' => 6,
    'login_lockout_min'  => 15,
];
