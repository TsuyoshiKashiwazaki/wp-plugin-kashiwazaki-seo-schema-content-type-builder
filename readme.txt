=== Kashiwazaki SEO Schema Content Type Builder ===
Contributors: tsuyoshikashiwazaki
Tags: schema, structured data, seo, json-ld, rich snippets
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.1
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

管理画面から簡単に構造化データ（Schema.org）を設定・出力できるWordPressプラグイン

== Description ==

Kashiwazaki SEO Schema Content Type Builderは、Article、NewsArticle、BlogPosting、WebPageの4つのContent Typeに対応した構造化データ（JSON-LD）を自動生成するプラグインです。

GoogleのリッチリザルトやSchema.orgの仕様に準拠した構造化データを、プログラミング知識なしで簡単に実装できます。各投稿タイプごとに異なるスキーマタイプを設定でき、SEO効果を最大化します。

= 主な機能 =

* 管理画面でContent Typeごとに投稿タイプを設定
* Google リッチリザルトに準拠した構造化データの自動生成
* パンくずリストの構造化データ対応
* 各Content Type固有のプロパティ設定が可能
* 投稿のタイトル、説明、画像、著者情報などを自動取得
* エラーの出ない適切なフォーマットで出力

= 対応するContent Type =

* **Article** - 一般的な記事（ブログ記事、ニュース以外の記事）
* **NewsArticle** - ニュース記事（時事的な内容、報道記事）
* **BlogPosting** - ブログ投稿（個人的な意見、日記形式の記事）
* **WebPage** - 固定ページ（会社概要、サービス紹介など）

= 設定可能な項目 =

**共通設定：**
* Publisher Type（Organization/Person）
* Publisher Name（発行者名）
* Publisher Logo URL（ロゴ画像）
* Default Author Type（Person/Organization）
* パンくずリストの有効化

**Content Type別の設定：**
* Article: Article Section（記事のカテゴリー）
* NewsArticle: News Keywords（ニュースキーワード）
* BlogPosting: コメント数の表示
* WebPage: Speakableプロパティ（音声読み上げ対応）

= 自動取得される情報 =

* 記事のURL
* タイトル（headline）
* 公開日時（datePublished）
* 更新日時（dateModified）
* 説明文（description）
* アイキャッチ画像（image）
* 著者情報（author）
* カテゴリー（articleSection）
* タグ（keywords）
* 文字数（wordCount）

== Installation ==

1. プラグインファイルを `/wp-content/plugins/kashiwazaki-seo-schema-content-type-builder` ディレクトリにアップロード
2. WordPress管理画面の「プラグイン」メニューからプラグインを有効化
3. 管理画面の左側メニューに表示される「Kashiwazaki SEO Schema Content Type Builder」から設定

== Frequently Asked Questions ==

= 構造化データはどこに出力されますか？ =

各ページの `<head>` タグ内に `<script type="application/ld+json">` として出力されます。

= カスタム投稿タイプにも対応していますか？ =

はい、公開されているすべての投稿タイプから選択可能です。

= 複数の投稿タイプに同じContent Typeを設定できますか？ =

はい、例えば「投稿」と「カスタム投稿タイプA」の両方にArticleタイプを設定することができます。

= WebPageを有効化したのに、CollectionPageが出力されるのはなぜですか？ =

WebPageスキーマでは、個別ページとアーカイブページで出力されるスキーマタイプが異なります。

**個別ページの場合:**
「対象の投稿タイプ」で選択した投稿タイプには `@type: "WebPage"` が出力されます。

**アーカイブページの場合:**
「対象のアーカイブページ」で選択したページ（ホーム、カテゴリー、タグなど）には `@type: "CollectionPage"` が出力されます。CollectionPageはWebPageの一種で、複数の投稿を一覧表示するページに最適なスキーマタイプです。

これはSchema.org仕様に準拠した正しい実装です。

= トップページにWebPageスキーマを出力するにはどうすればいいですか？ =

WebPageタブで「WebPage Schemaを有効にする」にチェックを入れ、「対象のアーカイブページ」で「ホームページ（投稿一覧）」を選択してください。トップページには `CollectionPage` スキーマが出力されます。

= 構造化データが正しく出力されているか確認する方法は？ =

Googleの「リッチリザルトテスト」ツールを使用して確認できます。ページのURLを入力すると、構造化データの検証結果が表示されます。

== Screenshots ==

1. 管理画面の設定画面
2. 出力される構造化データの例

== Changelog ==

= 1.0.1 =
* Improve: カスタムクエリを使用するプラグインとの互換性を向上
* Fix: get_queried_object()を使用してより堅牢な投稿取得処理に変更

= 1.0.0 =
* 初回リリース

== Upgrade Notice ==

= 1.0.1 =
カスタムクエリプラグインとの互換性が向上しました。

= 1.0.0 =
初回リリース 