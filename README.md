# 郵便番号 REST API の構築

Drupal 11 のカスタムモジュールとして、郵便番号マスタを取り込み、住所検索用の REST API と管理画面を用意するまでの構築記録です。

このリポジトリは、もともと Drupal の Content Type、View、Theme を学ぶ簡易掲示板から始まり、現在は `modules/custom/postcode_api` に郵便番号 API の実装を追加しています。

## できること

- 郵便番号マスタ用の `postcode_master` テーブルを作成する
- UTF-8 のタブ区切り郵便番号データを取り込む
- 郵便番号から住所を JSON で返す
- 都道府県、市区町村、町域を段階的に取得する
- 管理画面で郵便番号マスタを検索、ページング、ソートする

## 起動手順

```bash
docker compose up -d
```

ブラウザで `http://localhost:8080` を開き、Drupal をインストールします。

データベース設定は以下です。

| 項目 | 値 |
| --- | --- |
| Database type | MySQL, MariaDB, Percona Server, or equivalent |
| Database name | `drupal` |
| Database username | `drupal` |
| Database password | `drupal` |
| Advanced options / Host | `db` |
| Advanced options / Port number | `3306` |

Docker Compose 上では MariaDB は `db` というサービス名で名前解決されます。Drupal インストーラの Host に `localhost` を入れると、Drupal コンテナ自身を見に行くため接続に失敗します。

ホスト PC から MySQL Workbench などで接続する場合は、Docker Compose で公開しているホスト側ポートを使います。

| 項目 | 値 |
| --- | --- |
| Hostname | `127.0.0.1` |
| Port | `3307` |
| Username | `drupal` |
| Password | `drupal` |
| Default Schema | `drupal` |

初期化して最初からやり直す場合は、データが消えることを確認してから実行します。

```bash
docker compose down -v
```

## モジュール有効化

郵便番号 API は `Postcode API` というカスタムモジュールです。

Drupal 管理画面の `Extend` から `Postcode API` を有効化します。有効化時に `postcode_api.install` の `hook_schema()` が実行され、`postcode_master` テーブルが作成されます。

Drush が使える環境では、次のように有効化できます。

```bash
drush pm:enable postcode_api -y
```

このリポジトリの Docker 構成は Drupal 公式イメージを使っており、Drush 自体は必須依存として追加していません。Drush コマンドを使う場合は、Drush を利用できる環境で実行してください。

## 郵便番号データの取り込み

取り込み用コマンドは `postcode:import` です。

```bash
drush postcode:import /path/to/KEN_ALL_ROME.CSV
```

取り込みファイルは UTF-8 のタブ区切りを想定しています。利用する列は以下です。

| 列番号 | 内容 |
| --- | --- |
| 0 | 郵便番号 |
| 1 | 都道府県 |
| 2 | 市区町村 |
| 3 | 町域 |
| 4 | 都道府県ローマ字 |
| 5 | 市区町村ローマ字 |
| 6 | 町域ローマ字 |

現在のテーブルには日本語住所だけを保存するため、0 から 3 列目を使います。郵便番号を主キーにしているので、同じ郵便番号を再取り込みした場合は Drupal Database API の `merge()` により更新されます。

## REST API

すべて GET のみ許可しています。日本語を含むパスパラメータは、ブラウザやクライアント側で URL エンコードしてください。

| 用途 | エンドポイント |
| --- | --- |
| 郵便番号から住所を取得 | `GET /api/zipcodes/{zipcode}` |
| 都道府県一覧を取得 | `GET /api/prefectures` |
| 都道府県配下の市区町村一覧を取得 | `GET /api/prefectures/{prefecture}/cities` |
| 市区町村配下の町域一覧を取得 | `GET /api/prefectures/{prefecture}/cities/{city}/towns` |
| 住所に紐づく郵便番号一覧を取得 | `GET /api/prefectures/{prefecture}/cities/{city}/towns/{town}/zipcodes` |

例:

```bash
curl http://localhost:8080/api/zipcodes/0640941
```

レスポンス例:

```json
{
  "zipcode": "0640941",
  "prefecture": "北海道",
  "city": "札幌市中央区",
  "town": "旭ケ丘"
}
```

都道府県一覧のレスポンス例:

```json
[
  {
    "name": "北海道"
  },
  {
    "name": "青森県"
  }
]
```

該当データがない場合、Controller は `NotFoundHttpException` を投げ、Drupal の 404 レスポンスになります。

## 管理画面

管理画面は以下の URL です。

```text
http://localhost:8080/admin/postcode
```

`PostcodeAdminController` が検索条件、ソート条件、ページ番号を受け取り、`PostcodeService` から取得した結果を Drupal の Render Array に変換します。

管理画面では以下を利用しています。

| 機能 | 実装 |
| --- | --- |
| キーワード検索 | `PostcodeSearchForm` |
| 一覧表示 | Drupal の `#type table` |
| ページング | Drupal 標準 Pager |
| ソート | Drupal 標準 `TableSort` |
| キャッシュ条件 | `url.query_args` |

## 構築遍歴

### 1. カスタムモジュールを作る

`modules/custom/postcode_api` を作成し、`postcode_api.info.yml` で Drupal にカスタムモジュールとして認識させました。

```text
modules/custom/postcode_api/
├─ postcode_api.info.yml
├─ postcode_api.install
├─ postcode_api.routing.yml
├─ postcode_api.services.yml
├─ postcode_api.links.menu.yml
└─ src/
```

### 2. 郵便番号マスタの保存先を定義する

`postcode_api.install` の `hook_schema()` で `postcode_master` テーブルを定義しました。

| カラム | 型 | 用途 |
| --- | --- | --- |
| `zipcode` | `varchar(7)` | ハイフンなし7桁の郵便番号、主キー |
| `prefecture` | `varchar(50)` | 都道府県 |
| `city` | `varchar(100)` | 市区町村 |
| `town` | `varchar(255)` | 町域 |

Drupal の Database API には、実テーブル名ではなく論理テーブル名 `postcode_master` を渡します。DB プレフィックスが設定されていても、Drupal 側が実テーブル名へ解決します。

### 3. CSV 取り込みを Drush コマンドにする

`src/Drush/Commands/PostcodeCommands.php` に `postcode:import` を実装しました。

取り込み時に行っていることは以下です。

- ファイルの存在と読み取り可否を確認する
- `fgetcsv()` にタブ区切りを指定して読む
- 必須項目、文字コード、郵便番号長を検証する
- `merge()` で `postcode_master` に upsert する
- 取り込み件数とスキップ件数をログに出す

### 4. DB アクセスを Service に集約する

`src/Service/PostcodeService.php` に DB 問い合わせをまとめました。

Controller から直接 SQL を組み立てず、次のようなメソッド経由で取得します。

- `getByZipcode()`
- `getPrefectures()`
- `getCities()`
- `getTowns()`
- `getZipcodes()`
- `findAll()`
- `countAll()`

ソート対象カラムやソート方向は Service 側で許可リストに通し、想定外の値を Database API に渡さないようにしています。

### 5. REST API のルーティングを定義する

`postcode_api.routing.yml` で API の URL と Controller メソッドを対応づけました。

郵便番号検索では `zipcode: '\d{7}'` を指定し、7桁の数字だけを受け付けます。すべての API ルートは `methods: [GET]` にして、読み取り専用の REST API として扱います。

### 6. Controller は JSON 返却に集中させる

`src/Controller/PostcodeController.php` は、リクエストパラメータを受け取り、`PostcodeService` の結果を `JsonResponse` に変換します。

日本語の住所を読みやすく返すため、JSON のエンコードオプションに `JSON_UNESCAPED_UNICODE` と `JSON_UNESCAPED_SLASHES` を追加しています。

### 7. 管理画面を追加する

API だけではデータ確認がしづらいため、`/admin/postcode` に管理画面を追加しました。

`PostcodeSearchForm` は GET フォームにしているため、検索結果 URL を共有できます。`PostcodeAdminController` は検索語、ソート、ページ番号を URL query から読み取り、管理画面の一覧に反映します。

### 8. Docker からカスタムモジュールをマウントする

`docker-compose.yml` では、ローカルのカスタムモジュールを Drupal コンテナの Web ルートへマウントしています。

```yaml
./modules/custom/postcode_api:/opt/drupal/web/modules/custom/postcode_api:ro
```

これにより、ローカルで編集した `postcode_api` モジュールをコンテナ内の Drupal から利用できます。

## ディレクトリ構成

```text
project/
├─ docker-compose.yml
├─ composer.json
├─ modules/custom/postcode_api/
│  ├─ postcode_api.info.yml
│  ├─ postcode_api.install
│  ├─ postcode_api.routing.yml
│  ├─ postcode_api.services.yml
│  ├─ postcode_api.links.menu.yml
│  └─ src/
│     ├─ Controller/
│     ├─ Drush/Commands/
│     ├─ Form/
│     └─ Service/
├─ drupal/profiles/custom/bbs_profile/
├─ themes/custom/bbs_theme/
└─ README.md
```

## 実装上のポイント

- REST API の入口は `routing.yml`、処理本体は Controller、DB アクセスは Service に分ける
- Drupal のサービスコンテナを使い、Controller や Form に依存を注入する
- SQL 文字列を直接書かず、Drupal Database API で `select()`、`condition()`、`merge()` を使う
- 管理画面は Render Array、Form API、Pager、TableSort に乗せる
- URL query で表示内容が変わる画面は cache context に `url.query_args` を追加する
