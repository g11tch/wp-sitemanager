Infinity CMSはモジュール式のプラグインです。
Infinity CMS自体には、モジュールの管理機能のみが実装され、それ以外の機能については、すべてモジュールにより実現されるようになっています。


モジュールの仕様について
モジュールとして扱われるのは、modulesディレクトリに配置したPHPファイルの中で、有効なコメントが記述されたものとなります。

コメント形式については、下記の通りとなります。

/*
 * cms module:         モジュール名
 * Module Description: モジュールの説明
 * Order:              モジュール管理画面での表示順
 * First Introduced:   モジュールが導入されたInfinity CMSのバージョン
 * Builtin:            モジュール管理とするかどうか。管理から外す（自動でon）場合は1かture
 * Major Changes In:   未使用
 * Free:               有料モジュールかどうかの判別（未使用）
*/

■TODO
○moduleds/site-structure.php
済 ドロップダウンページリストの順序不整合修正
・ 設定項目の反映
・ 設定方法の見直し
・ ソースのリファクタリング