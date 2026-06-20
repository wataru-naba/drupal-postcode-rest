<?php

namespace Drupal\postcode_api\Drush\Commands;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\postcode_api\Entity\PostcodeMaster;
use Drupal\postcode_api\PostcodeMasterInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * 郵便番号マスタを操作する Drush コマンド。
 */
class PostcodeCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Postcode Master entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $postcodeStorage;

  /**
   * PostcodeCommands のコンストラクタ。
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct();
    $this->postcodeStorage = $entity_type_manager->getStorage('postcode_master');
  }

  /**
   * 郵便番号CSV(TSV)を postcode_master テーブルへ取り込みます。
   *
   * CSV の列定義:
   * - 0: zipcode
   * - 1: prefecture
   * - 2: city
   * - 3: town
   * - 4: prefecture_roman
   * - 5: city_roman
   * - 6: town_roman
   *
   * 今回のテーブルでは日本語住所のみを保存するため、0〜3列目を使います。
   *
   * @param string $csv_path
   *   インポート対象の UTF-8 TSV ファイルパス。
   *
   * @return int
   *   Drush の終了コード。0 は成功、1 は失敗です。
   */
  #[CLI\Command(name: 'postcode:import')]
  #[CLI\Argument(name: 'csv_path', description: 'インポートする UTF-8 タブ区切りCSV(TSV)ファイルのパス。')]
  #[CLI\Usage(name: 'drush postcode:import /path/to/KEN_ALL_ROME.CSV', description: '郵便番号TSVを postcode_master テーブルへインポートします。')]
  public function import(string $csv_path): int {
    if (!file_exists($csv_path)) {
      $this->logger()->error(sprintf('CSVファイルが存在しません: %s', $csv_path));
      return self::EXIT_FAILURE;
    }

    if (!is_readable($csv_path)) {
      $this->logger()->error(sprintf('CSVファイルを読み込めません: %s', $csv_path));
      return self::EXIT_FAILURE;
    }

    $this->logger()->notice(sprintf('郵便番号CSVのインポートを開始します: %s', $csv_path));

    $handle = fopen($csv_path, 'rb');
    if ($handle === FALSE) {
      $this->logger()->error(sprintf('CSVファイルを開けませんでした: %s', $csv_path));
      return self::EXIT_FAILURE;
    }

    $imported = 0;
    $skipped = 0;
    $line_number = 0;

    try {
      // fgetcsv() の delimiter に "\t" を指定して、タブ区切りとして解析します。
      while (($row = fgetcsv($handle, 0, "\t", '"', '')) !== FALSE) {
        $line_number++;

        // 空行はインポート対象外として扱います。
        if ($row === [NULL] || $row === ['']) {
          $skipped++;
          continue;
        }

        if (count($row) < 4) {
          $this->logger()->warning(sprintf('%d行目は列数が不足しているためスキップしました。', $line_number));
          $skipped++;
          continue;
        }

        $zipcode = trim((string) $row[0]);
        $prefecture = trim((string) $row[1]);
        $city = trim((string) $row[2]);
        $town = trim((string) $row[3]);

        if (!$this->isValidImportRow($zipcode, $prefecture, $city, $town, $line_number)) {
          $skipped++;
          continue;
        }

        $postcode = $this->loadExistingPostcode($zipcode);
        if ($postcode === NULL) {
          $postcode = PostcodeMaster::create([
            'zipcode' => $zipcode,
            'prefecture' => $prefecture,
            'city' => $city,
            'town' => $town,
          ]);
        }
        else {
          $postcode
            ->setPrefecture($prefecture)
            ->setCity($city)
            ->setTown($town);
        }

        $postcode->save();

        $imported++;
      }
    }
    finally {
      fclose($handle);
    }

    $this->logger()->notice('郵便番号CSVのインポートを終了しました。');
    $this->logger()->success(sprintf('%d件をインポートしました。', $imported));

    if ($skipped > 0) {
      $this->logger()->warning(sprintf('%d行をスキップしました。', $skipped));
    }

    return self::EXIT_SUCCESS;
  }

  /**
   * Loads an existing Postcode Master entity.
   *
   * @param string $zipcode
   *   The zipcode.
   *
   * @return \Drupal\postcode_api\PostcodeMasterInterface|null
   *   The loaded entity, or NULL when it does not exist.
   */
  protected function loadExistingPostcode(string $zipcode): ?PostcodeMasterInterface {
    $entity = $this->postcodeStorage->load($zipcode);
    return $entity instanceof PostcodeMasterInterface ? $entity : NULL;
  }

  /**
   * インポートに必要な4列の値を検証します。
   *
   * @param string $zipcode
   *   郵便番号。
   * @param string $prefecture
   *   都道府県名。
   * @param string $city
   *   市区町村名。
   * @param string $town
   *   町域名。
   * @param int $line_number
   *   CSV 上の行番号。
   *
   * @return bool
   *   インポート可能な行なら TRUE。
   */
  protected function isValidImportRow(string $zipcode, string $prefecture, string $city, string $town, int $line_number): bool {
    if ($zipcode === '' || $prefecture === '' || $city === '' || $town === '') {
      $this->logger()->warning(sprintf('%d行目は必須項目が空のためスキップしました。', $line_number));
      return FALSE;
    }

    // UTF-8 前提のファイルとして扱うため、取り込み対象列の文字コードを確認します。
    if (!mb_check_encoding($zipcode . $prefecture . $city . $town, 'UTF-8')) {
      $this->logger()->warning(sprintf('%d行目は UTF-8 として読めないためスキップしました。', $line_number));
      return FALSE;
    }

    if (!preg_match('/^\d{7}$/', $zipcode)) {
      $this->logger()->warning(sprintf('%d行目は郵便番号が7桁の数字ではないためスキップしました。', $line_number));
      return FALSE;
    }

    return TRUE;
  }

}
