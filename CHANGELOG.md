# Changelog

## [1.0.1] - 2025-11-01

### Improved
- カスタムクエリを使用するプラグインとの互換性を向上

### Fixed
- `global $post` の代わりに `get_queried_object()` を使用してより堅牢な投稿取得処理に変更
- カスタムリライトルールを使用するプラグインとの連携問題を解決

## [1.0.0] - 2025-10-06

### Added
- 初回リリース
- Article、NewsArticle、BlogPosting、WebPageの4つのContent Typeに対応
- 管理画面でContent Typeごとに投稿タイプを設定する機能
- Google リッチリザルトに準拠した構造化データの自動生成
- パンくずリストの構造化データ対応
- Publisher情報（Organization/Person）の設定機能
- 各Content Type固有のプロパティ設定機能
- 投稿のメタデータ（タイトル、説明、画像、著者など）の自動取得
- Schema.org仕様に完全準拠したJSON-LD形式での出力

### Features
- **Article設定**: Article Section（記事カテゴリー）の設定
- **NewsArticle設定**: News Keywords（ニュースキーワード）の設定
- **BlogPosting設定**: コメント数の表示機能
- **WebPage設定**: Speakableプロパティ（音声読み上げ対応）
- カスタム投稿タイプへの対応
- リッチリザルトテストツールでの検証対応
