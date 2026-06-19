<?php

namespace Drupal\postcode_api\Service;

use Drupal\Core\Database\Connection;

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
   * Drupal の DB 接続サービス。
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * PostcodeService のコンストラクタ。
   *
   * @param \Drupal\Core\Database\Connection $database
   *   サービスコンテナから注入される Database Connection。
   */
  public function __construct(Connection $database) {
    $this->database = $database;
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

}
