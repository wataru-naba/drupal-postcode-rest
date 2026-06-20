<?php

namespace Drupal\postcode_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\postcode_api\PostcodeMasterInterface;
use Drupal\postcode_api\Service\PostcodeService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 郵便番号検索 REST API の Controller。
 *
 * Controller はリクエストを受け取り、Service 経由で取得した Entity を JSON に変換します。
 */
class PostcodeController extends ControllerBase {

  /**
   * 郵便番号データ取得サービス。
   *
   * @var \Drupal\postcode_api\Service\PostcodeService
   */
  protected PostcodeService $postcodeService;

  /**
   * PostcodeController のコンストラクタ。
   *
   * @param \Drupal\postcode_api\Service\PostcodeService $postcode_service
   *   郵便番号データ取得サービス。
   */
  public function __construct(PostcodeService $postcode_service) {
    $this->postcodeService = $postcode_service;
  }

  /**
   * サービスコンテナから Controller に必要な依存を取り出します。
   *
   * Drupal の Controller は create() を実装することで Dependency Injection
   * を利用できます。ここでは PostcodeService を注入しています。
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('postcode_api.postcode_service')
    );
  }

  /**
   * GET /api/zipcodes/{zipcode}
   */
  public function getByZipcode(string $zipcode): JsonResponse {
    $postcode = $this->postcodeService->getByZipcode($zipcode);

    if ($postcode === NULL) {
      throw new NotFoundHttpException('指定された郵便番号が見つかりません。');
    }

    return $this->createJsonResponse($this->buildPostcodeData($postcode));
  }

  /**
   * GET /api/prefectures
   */
  public function getPrefectures(): JsonResponse {
    return $this->createJsonResponse($this->postcodeService->getPrefectures());
  }

  /**
   * GET /api/prefectures/{prefecture}/cities
   */
  public function getCities(string $prefecture): JsonResponse {
    $cities = $this->postcodeService->getCities($prefecture);

    if ($cities === []) {
      throw new NotFoundHttpException('指定された都道府県が見つかりません。');
    }

    return $this->createJsonResponse($cities);
  }

  /**
   * GET /api/prefectures/{prefecture}/cities/{city}/towns
   */
  public function getTowns(string $prefecture, string $city): JsonResponse {
    $towns = $this->postcodeService->getTowns($prefecture, $city);

    if ($towns === []) {
      throw new NotFoundHttpException('指定された市区町村が見つかりません。');
    }

    return $this->createJsonResponse($towns);
  }

  /**
   * GET /api/prefectures/{prefecture}/cities/{city}/towns/{town}/zipcodes
   */
  public function getZipcodes(
    string $prefecture,
    string $city,
    string $town,
  ): JsonResponse {
    $zipcodes = $this->postcodeService->getZipcodes($prefecture, $city, $town);

    if ($zipcodes === []) {
      throw new NotFoundHttpException('指定された町域が見つかりません。');
    }

    return $this->createJsonResponse(array_map(
      static fn(PostcodeMasterInterface $postcode): array => [
        'zipcode' => $postcode->getZipcode(),
      ],
      $zipcodes
    ));
  }

  /**
   * Converts a Postcode Master entity to response data.
   *
   * @param \Drupal\postcode_api\PostcodeMasterInterface $postcode
   *   The Postcode Master entity.
   *
   * @return array
   *   Response data.
   */
  protected function buildPostcodeData(PostcodeMasterInterface $postcode): array {
    return [
      'zipcode' => $postcode->getZipcode(),
      'prefecture' => $postcode->getPrefecture(),
      'city' => $postcode->getCity(),
      'town' => $postcode->getTown(),
    ];
  }

  /**
   * JSON レスポンスを作成します。
   *
   * 日本語を読みやすくするため、JSON_UNESCAPED_UNICODE を有効にします。
   */
  protected function createJsonResponse(array $data): JsonResponse {
    $response = new JsonResponse($data);
    $response->setEncodingOptions(
      $response->getEncodingOptions() | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    return $response;
  }

}
