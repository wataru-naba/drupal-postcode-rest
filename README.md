# Drupal簡易掲示板

Drupal Coreのみで、Node Entity、Field、View、Themeの基本を学ぶための簡易掲示板です。

## 起動手順

```bash
docker compose up -d
```

ブラウザで `http://localhost:8080` を開き、インストールプロファイルに `BBS Learning Profile` を選択します。

データベース設定は以下を入力します。

| 項目 | 値 |
| --- | --- |
| Database type | MySQL, MariaDB, Percona Server, or equivalent |
| Database name | `drupal` |
| Database username | `drupal` |
| Database password | `drupal` |
| Advanced options / Host | `db` |
| Advanced options / Port number | `3306` |

インストール完了後、以下のURLで動作確認できます。

Docker Compose上ではMariaDBは `db` というサービス名で名前解決されます。DrupalインストーラのHostに `localhost` を入れると、Drupalコンテナ自身を見に行くため接続に失敗します。

| 機能 | URL |
| --- | --- |
| 投稿作成 | `http://localhost:8080/node/add/bbs_post` |
| 投稿一覧 | `http://localhost:8080/bbs` |
| 投稿詳細 | `http://localhost:8080/node/1` など |

初期化して最初からやり直す場合は、学習用データが消えることを確認してから `docker compose down -v` を実行します。

## ディレクトリ構成

```text
project/
├─ docker-compose.yml
├─ drupal/
│  └─ profiles/custom/bbs_profile/
├─ themes/custom/
│  └─ bbs_theme/
└─ README.md
```

## Drupal構成

### Content Type

`掲示板投稿` というContent Typeを `bbs_post` のマシン名で定義しています。

フィールドは以下です。

| 項目 | 型 |
| --- | --- |
| タイトル | Node標準のTitleフィールド |
| 本文 | Body（Text Long with summary） |

Node作成画面はDrupal標準のルーティングにより `/node/add/bbs_post` で利用できます。

### View

`掲示板投稿一覧` Viewを定義し、ページ表示を `/bbs` に配置しています。

Viewの条件は以下です。

- ベーステーブルは `node_field_data`
- Content Typeは `bbs_post`
- PublishedなNodeのみ表示
- 表示項目はタイトル、本文、投稿日時
- 投稿日時 `created` の降順
- タイトルはNode詳細画面へリンク

カスタムSQLは書かず、Drupal CoreのViews設定として実装しています。

### Theme

`bbs_theme` はCoreテーマ `olivero` をベースにしたカスタムテーマです。

一覧画面は以下のTwigテンプレートでカスタマイズしています。

- `themes/custom/bbs_theme/templates/views-view-unformatted--bbs--page-1.html.twig`
- `themes/custom/bbs_theme/templates/views-view-fields--bbs--page-1.html.twig`

出力する主なHTML構造は以下です。

```html
<div class="bbs-list">
  <article class="bbs-item">
    <h2>タイトル</h2>
    <div class="body">本文</div>
    <time>投稿日時</time>
  </article>
</div>
```

最低限のCSSは `themes/custom/bbs_theme/css/bbs.css` に定義しています。

## 学習内容

### Entity

DrupalのEntityは、サイト内で扱うデータ単位を表す仕組みです。Node、User、Taxonomy Term、FileなどがEntityです。

Node Entityは記事や固定ページなどのコンテンツを表すContent Entityで、掲示板投稿もNode Entityとして保存されます。

### Content Type

Content TypeはNode EntityのBundleです。

NodeというEntity Typeに対して、`article`、`page`、`bbs_post` のような種類を定義します。同じNode Entityでも、Content Typeごとに持つField、フォーム表示、詳細表示、権限、Viewでの絞り込み条件を変えられます。

この課題では `掲示板投稿` がNodeのContent Typeであり、マシン名は `bbs_post` です。

### Field

FieldはEntityに追加できるデータ項目です。

Nodeには標準で `nid`、`uuid`、`type`、`title`、`uid`、`status`、`created`、`changed` などのBase Fieldがあります。タイトルはNode標準のTitleフィールドです。

BodyはField APIで追加されるFieldです。今回の `body` は `text_with_summary` 型で、本文の値、概要、テキストフォーマットを保存できます。

### Database

Node Entityは複数のテーブルに分かれて保存されます。

- `node`: Nodeの基本IDやUUIDなど
- `node_field_data`: タイトル、Content Type、公開状態、作成日時など、言語別の主要データ
- `node_revision`: リビジョンの基本情報
- `node_field_revision`: リビジョンごとの主要データ
- `node__body`: Bodyフィールドの現在値
- `node_revision__body`: Bodyフィールドのリビジョン値

Content TypeやField定義、View定義、Theme設定はDrupalのConfigurationとして管理され、インストール後は主に `config` テーブルに保存されます。

### View

ViewはEntityやDatabaseテーブルを直接PHPでSQLを書く代わりに、設定として一覧取得を定義する仕組みです。

この課題のViewは `node_field_data` をベースに、`type = bbs_post`、`status = 1` で絞り込み、`created DESC` で並び替えます。Bodyを表示するためにField API経由で `node__body` の値も利用します。

Viewsは設定YAMLからSQLを組み立て、DrupalのEntity/Field APIやアクセスチェックと連携して一覧を表示します。

### Theme

ThemeはDrupalの表示層です。TwigテンプレートでHTML構造を変更し、ライブラリ定義でCSSやJavaScriptを読み込みます。

今回の `bbs_theme` はViewsのテンプレート候補を使い、`/bbs` の一覧だけを掲示板用のHTML構造に変更しています。
