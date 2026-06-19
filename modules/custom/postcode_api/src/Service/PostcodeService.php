<?php

namespace Drupal\postcode_api\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Pager\PagerParametersInterface;

/**
 * postcode_master テーブルから郵便番号データを取得するサービス。
 *
 * Controller から Database API を直接呼ばず、このクラスに DB アクセスを集約します。
 */
class PostcodeService {

  /**
   * 郵便番号マスタの論理テーブル名。
   *
   * 実 DB で drupal_postcode_master のようなプレフィックス付きテーブルに
   * なっていても、Database API には論理名 postcode_master を渡します。
   */
  private const TABLE = 'postcode_master';

  /**
   * 管理画面で表示・検索・ソートに使うカラム。
   *
   * Service 側でも許可カラムを固定しておくことで、URL から想定外の
   * ソート項目が渡ってきても Database API に流さないようにします。
   */
  private const COLUMNS = [
    'zipcode',
    'prefecture',
    'city',
    'town',
  ];

  /**
   * Drupal の DB 接続サービス。
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * 現在の Drupal Pager ページ番号を取得するサービス。
   *
   * @var \Drupal\Core\Pager\PagerParametersInterface
   */
  protected PagerParametersInterface $pagerParameters;

  /**
   * PostcodeService のコンストラクタ。
   *
   * @param \Drupal\Core\Database\Connection $database
   *   サービスコンテナから注入される Database Connection。
   * @param \Drupal\Core\Pager\PagerParametersInterface $pager_parameters
   *   URL の ?page= から現在ページを取得するサービス。
   */
  public function __construct(Connection $database, PagerParametersInterface $pager_parameters) {
    $this->database = $database;
    $this->pagerParameters = $pager_parameters;
  }

  /**
   * 郵便番号マスタを管理画面の一覧用に取得します。
   *
   * @param array $filters
   *   検索条件。keyword を渡すと zipcode/prefecture/city/town を
   *   部分一致で横断検索します。
   * @param string $sort
   *   ソート対象カラム。zipcode/prefecture/city/town のいずれか。
   * @param string $direction
   *   ASC または DESC。
   * @param int $limit
   *   1ページあたりの取得件数。
   *
   * @return array
   *   郵便番号マスタの行配列。
   */
  public function findAll(
    array $filters = [],
    string $sort = 'zipcode',
    string $direction = 'ASC',
    int $limit = 50,
  ): array {
    $sort = $this->normalizeSort($sort);
    $direction = $this->normalizeDirection($direction);
    $limit = max(1, $limit);

    // Drupal 標準 Pager の現在ページ番号から OFFSET を計算します。
    // page=0 が1ページ目、page=1 が2ページ目です。
    $page = max(0, $this->pagerParameters->findPage());
    $offset = $page * $limit;

    $query = $this->database->select(self::TABLE, 'pm');
    $query->fields('pm', self::COLUMNS);
    $this->applyFilters($query, $filters);
    $query->orderBy($sort, $direction);
    $query->range($offset, $limit);

    return $query->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * 郵便番号マスタの件数を取得します。
   *
   * Pager は総件数と1ページ件数からページ数を計算するため、
   * 一覧取得とは別に COUNT(*) を実行します。
   *
   * @param array $filters
   *   findAll() と同じ検索条件。
   *
   * @return int
   *   検索条件に一致する総件数。
   */
  public function countAll(array $filters = []): int {
    $query = $this->database->select(self::TABLE, 'pm');
    $query->addExpression('COUNT(*)');
    $this->applyFilters($query, $filters);

    return (int) $query->execute()->fetchField();
  }

  /**
   * 郵便番号から住所を1件取得します。
   *
   * @param string $zipcode
   *   ハイフンなし7桁の郵便番号。
   *
   * @return array
   *   見つかった場合は zipcode/prefecture/city/town を持つ配列。
   *   見つからない場合は空配列。
   */
  public function getByZipcode(string $zipcode): array {
    // select() には実テーブル名ではなく論理テーブル名を指定します。
    $record = $this->database->select(self::TABLE, 'pm')
      ->fields('pm', ['zipcode', 'prefecture', 'city', 'town'])
      ->condition('zipcode', $zipcode)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    return $record ?: [];
  }

  /**
   * 都道府県一覧を取得します。
   *
   * @return array
   *   例: [['name' => '北海道'], ['name' => '青森県']]
   */
  public function getPrefectures(): array {
    return $this->getDistinctNames('prefecture');
  }

  /**
   * 指定都道府県に属する市区町村一覧を取得します。
   *
   * @param string $prefecture
   *   都道府県名。
   *
   * @return array
   *   例: [['name' => '札幌市中央区']]
   */
  public function getCities(string $prefecture): array {
    return $this->getDistinctNames('city', [
      'prefecture' => $prefecture,
    ]);
  }

  /**
   * 指定都道府県・市区町村に属する町域一覧を取得します。
   *
   * @param string $prefecture
   *   都道府県名。
   * @param string $city
   *   市区町村名。
   *
   * @return array
   *   例: [['name' => '旭ケ丘']]
   */
  public function getTowns(string $prefecture, string $city): array {
    return $this->getDistinctNames('town', [
      'prefecture' => $prefecture,
      'city' => $city,
    ]);
  }

  /**
   * 指定住所に紐づく郵便番号一覧を取得します。
   *
   * @param string $prefecture
   *   都道府県名。
   * @param string $city
   *   市区町村名。
   * @param string $town
   *   町域名。
   *
   * @return array
   *   例: [['zipcode' => '0640941']]
   */
  public function getZipcodes(
    string $prefecture,
    string $city,
    string $town,
  ): array {
    $query = $this->database->select(self::TABLE, 'pm');
    $query->addField('pm', 'zipcode');
    $query->condition('prefecture', $prefecture);
    $query->condition('city', $city);
    $query->condition('town', $town);
    $query->orderBy('zipcode', 'ASC');

    $zipcodes = [];
    foreach ($query->execute()->fetchAll() as $row) {
      $zipcodes[] = [
        'zipcode' => $row->zipcode,
      ];
    }

    return $zipcodes;
  }

  /**
   * 指定カラムの重複なし一覧を name キーの配列として取得します。
   *
   * @param string $column
   *   取得対象カラム。prefecture/city/town のいずれか。
   * @param array $conditions
   *   WHERE 条件。キーがカラム名、値が検索値。
   *
   * @return array
   *   例: [['name' => '北海道']]
   */
  protected function getDistinctNames(string $column, array $conditions = []): array {
    $query = $this->database->select(self::TABLE, 'pm')->distinct();
    $query->addField('pm', $column, 'name');

    foreach ($conditions as $field => $value) {
      $query->condition($field, $value);
    }

    $query->orderBy($column, 'ASC');

    $names = [];
    foreach ($query->execute()->fetchAll() as $row) {
      $names[] = [
        'name' => $row->name,
      ];
    }

    return $names;
  }

  /**
   * 管理画面の検索条件を Select Query に適用します。
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   条件を追加する Select Query。
   * @param array $filters
   *   検索条件。
   */
  protected function applyFilters(SelectInterface $query, array $filters): void {
    $keyword = trim((string) ($filters['keyword'] ?? ''));

    if ($keyword !== '') {
      // LIKE 検索では % や _ が特別な意味を持つため、escapeLike() で
      // ユーザー入力を安全にエスケープしてからワイルドカードを付けます。
      $like = '%' . $this->database->escapeLike($keyword) . '%';
      $or = $query->orConditionGroup()
        ->condition('zipcode', $like, 'LIKE')
        ->condition('prefecture', $like, 'LIKE')
        ->condition('city', $like, 'LIKE')
        ->condition('town', $like, 'LIKE');

      $query->condition($or);
    }
  }

  /**
   * ソート対象カラムを許可リストで検証します。
   *
   * @param string $sort
   *   URL などから渡されたソート対象。
   *
   * @return string
   *   許可済みのソート対象。
   */
  protected function normalizeSort(string $sort): string {
    if (in_array($sort, self::COLUMNS, TRUE)) {
      return $sort;
    }

    return 'zipcode';
  }

  /**
   * ソート方向を ASC/DESC のみに正規化します。
   *
   * @param string $direction
   *   URL などから渡されたソート方向。
   *
   * @return string
   *   ASC または DESC。
   */
  protected function normalizeDirection(string $direction): string {
    $direction = strtoupper($direction);

    if (in_array($direction, ['ASC', 'DESC'], TRUE)) {
      return $direction;
    }

    return 'ASC';
  }

}
