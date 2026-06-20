<?php

namespace Drupal\postcode_api\Service;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Drupal\postcode_api\PostcodeMasterInterface;

/**
 * Provides postcode data through the Postcode Master entity storage.
 *
 * Controller から EntityStorage を直接呼ばず、このクラスに取得処理を集約します。
 */
class PostcodeService {

  /**
   * 管理画面で表示・検索・ソートに使うカラム。
   *
   * Service 側でも許可カラムを固定しておくことで、URL から想定外の値が
   * 渡ってきても Entity Query に流さないようにします。
   */
  private const COLUMNS = [
    'zipcode',
    'prefecture',
    'city',
    'town',
  ];

  /**
   * Postcode Master entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $postcodeStorage;

  /**
   * 現在の Drupal Pager ページ番号を取得するサービス。
   *
   * @var \Drupal\Core\Pager\PagerParametersInterface
   */
  protected PagerParametersInterface $pagerParameters;

  /**
   * PostcodeService のコンストラクタ。
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Pager\PagerParametersInterface $pager_parameters
   *   URL の ?page= から現在ページを取得するサービス。
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PagerParametersInterface $pager_parameters) {
    $this->postcodeStorage = $entity_type_manager->getStorage('postcode_master');
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
   * @return \Drupal\postcode_api\PostcodeMasterInterface[]
   *   Postcode Master entities.
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

    $query = $this->buildQuery($filters);
    $query->sort($sort, $direction);
    $query->range($offset, $limit);

    return $this->loadPostcodes($query->execute());
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
    return (int) $this->buildQuery($filters)->count()->execute();
  }

  /**
   * 郵便番号から住所を1件取得します。
   *
   * @param string $zipcode
   *   ハイフンなし7桁の郵便番号。
   *
   * @return \Drupal\postcode_api\PostcodeMasterInterface|null
   *   The Postcode Master entity, or NULL when it does not exist.
   */
  public function getByZipcode(string $zipcode): ?PostcodeMasterInterface {
    $entity = $this->postcodeStorage->load($zipcode);
    return $entity instanceof PostcodeMasterInterface ? $entity : NULL;
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
   * @return \Drupal\postcode_api\PostcodeMasterInterface[]
   *   Matching Postcode Master entities.
   */
  public function getZipcodes(
    string $prefecture,
    string $city,
    string $town,
  ): array {
    return $this->loadByConditions([
      'prefecture' => $prefecture,
      'city' => $city,
      'town' => $town,
    ], 'zipcode');
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
    $names = [];
    foreach ($this->loadByConditions($conditions, $column) as $postcode) {
      $value = trim((string) $postcode->get($column)->value);
      if ($value !== '') {
        $names[$value] = [
          'name' => $value,
        ];
      }
    }

    ksort($names, SORT_NATURAL);
    return array_values($names);
  }

  /**
   * Builds an entity query for Postcode Master entities.
   *
   * @param array $filters
   *   検索条件。
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  protected function buildQuery(array $filters = []): QueryInterface {
    $query = $this->postcodeStorage->getQuery()
      ->accessCheck(FALSE);

    $keyword = trim((string) ($filters['keyword'] ?? ''));

    if ($keyword !== '') {
      $or = $query->orConditionGroup()
        ->condition('zipcode', $keyword, 'CONTAINS')
        ->condition('prefecture', $keyword, 'CONTAINS')
        ->condition('city', $keyword, 'CONTAINS')
        ->condition('town', $keyword, 'CONTAINS');

      $query->condition($or);
    }

    return $query;
  }

  /**
   * Loads entities matching the given field conditions.
   *
   * @param array $conditions
   *   Conditions keyed by field name.
   * @param string $sort
   *   The sort field.
   *
   * @return \Drupal\postcode_api\PostcodeMasterInterface[]
   *   Postcode Master entities.
   */
  protected function loadByConditions(array $conditions = [], string $sort = 'zipcode'): array {
    $query = $this->postcodeStorage->getQuery()
      ->accessCheck(FALSE);

    foreach ($conditions as $field => $value) {
      if (in_array($field, self::COLUMNS, TRUE)) {
        $query->condition($field, $value);
      }
    }

    $query->sort($this->normalizeSort($sort), 'ASC');

    return $this->loadPostcodes($query->execute());
  }

  /**
   * Loads and filters Postcode Master entities by IDs.
   *
   * @param array $ids
   *   Entity IDs.
   *
   * @return \Drupal\postcode_api\PostcodeMasterInterface[]
   *   Postcode Master entities.
   */
  protected function loadPostcodes(array $ids): array {
    if ($ids === []) {
      return [];
    }

    $entities = $this->postcodeStorage->loadMultiple($ids);
    $postcodes = [];

    foreach ($entities as $entity) {
      if ($entity instanceof PostcodeMasterInterface) {
        $postcodes[] = $entity;
      }
    }

    return $postcodes;
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
