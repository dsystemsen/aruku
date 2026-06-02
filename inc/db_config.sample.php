<?php
/**
 * 本番(MySQL)用 DB接続設定テンプレート
 *
 * 使い方：
 *   1) このファイルを同じ inc/ 内に「db_config.php」という名前でコピー
 *   2) Xserver のデータベース情報を記入
 *   inc/db_config.php が存在すると自動的に MySQL へ接続します（無ければローカルSQLite）。
 *
 * ※ db_config.php は秘密情報です。git にコミットしないでください（.gitignore 済み）。
 *    Xserver サーバー上に直接設置してください。
 */
return [
    'host' => 'mysqlXXXX.xserver.jp', // Xserver の MySQL ホスト名（サーバーパネルで確認）
    'name' => 'xs186588_aruku',       // データベース名
    'user' => 'xs186588_aruku',       // ユーザー名
    'pass' => 'ここにパスワード',       // パスワード
];
