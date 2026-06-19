<?php

namespace Drupal\postcode_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Utility\TableSort;
use Drupal\postcode_api\Form\PostcodeSearchForm;
use Drupal\postcode_api\Service\PostcodeService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 郵便番号マスタ管理画面の Controller。
 *
 * Controller はリクエストから検索条件とソート条件を取り出し、
 * Service の結果を Drupal の Render Array に変換するだけにします。
 * DB アクセスは PostcodeService に集約します。
 */
class PostcodeAdminController extends ControllerBase {

  /**
   * 1ページあたりの表示件数。
   */
  private const ITEMS_PER_PAGE = 50;

  /**
   * 郵便番号データ取得サービス。
   *
   * @var \Drupal\postcode_api\Service\PostcodeService
   */
  protected PostcodeService $postcodeService;

  /**
   * Drupal Form API のフォーム生成サービス。
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected FormBuilderInterface $postcodeFormBuilder;

  /**
   * Drupal 標準 Pager を作成するサービス。
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected PagerManagerInterface $pagerManager;

  /**
   * 現在の Request を取得するサービス。
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * PostcodeAdminController のコンストラクタ。
   *
   * @param \Drupal\postcode_api\Service\PostcodeService $postcode_service
   *   郵便番号データ取得サービス。
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   フォーム生成サービス。
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   Pager 生成サービス。
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   現在の Request を取得するサービス。
   */
  public function __construct(
    PostcodeService $postcode_service,
    FormBuilderInterface $form_builder,
    PagerManagerInterface $pager_manager,
    RequestStack $request_stack,
  ) {
    $this->postcodeService = $postcode_service;
    $this->postcodeFormBuilder = $form_builder;
    $this->pagerManager = $pager_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * サービスコンテナから Controller に必要な依存を取り出します。
   *
   * Drupal の Controller は create() を実装することで Dependency Injection
   * を利用できます。\Drupal::service() をメソッド内で直接呼ばずに済みます。
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('postcode_api.postcode_service'),
      $container->get('form_builder'),
      $container->get('pager.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * 郵便番号マスタ一覧ページを表示します。
   *
   * @return array
   *   Drupal が HTML に変換する Render Array。
   */
  public function index(): array {
    $request = $this->requestStack->getCurrentRequest() ?? new Request();
    $filters = $this->buildFilters($request);
    $header = $this->buildTableHeader();

    // Drupal 標準 TableSort は、ヘッダー定義と URL の order/sort から
    // 現在のソート対象と昇順・降順を判断します。
    $order = TableSort::getOrder($header, $request);
    $sort = (string) ($order['sql'] ?? 'zipcode');
    $direction = strtoupper((string) TableSort::getSort($header, $request));

    $total = $this->postcodeService->countAll($filters);
    $this->pagerManager->createPager($total, self::ITEMS_PER_PAGE);

    $postcodes = $this->postcodeService->findAll(
      $filters,
      $sort,
      $direction,
      self::ITEMS_PER_PAGE
    );

    $build['search_form'] = $this->postcodeFormBuilder->getForm(PostcodeSearchForm::class);

    $build['count'] = [
      '#markup' => '<p>' . $this->t('@count 件', ['@count' => $total]) . '</p>',
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $this->buildTableRows($postcodes),
      '#empty' => $this->t('郵便番号データが見つかりません。'),
    ];

    $build['pager'] = [
      '#type' => 'pager',
    ];

    // 検索語・ソート・ページ番号ごとに結果が変わるため、URL query を
    // キャッシュの条件に含めます。
    $build['#cache']['contexts'][] = 'url.query_args';

    return $build;
  }

  /**
   * Request から検索条件を作ります。
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   現在の Request。
   *
   * @return array
   *   PostcodeService に渡す検索条件。
   */
  protected function buildFilters(Request $request): array {
    $keyword = trim((string) $request->query->get('keyword', ''));

    if ($keyword === '') {
      return [];
    }

    return [
      'keyword' => $keyword,
    ];
  }

  /**
   * Drupal 標準 TableSort に対応したテーブルヘッダーを作ります。
   *
   * field に DB カラム名を設定すると、TableSort がクリックされた列を
   * 判定し、Service に渡すソート対象として利用できます。
   *
   * @return array
   *   #type table の #header に渡す配列。
   */
  protected function buildTableHeader(): array {
    return [
      'zipcode' => [
        'data' => $this->t('郵便番号'),
        'field' => 'zipcode',
        'sort' => 'asc',
      ],
      'prefecture' => [
        'data' => $this->t('都道府県'),
        'field' => 'prefecture',
      ],
      'city' => [
        'data' => $this->t('市区町村'),
        'field' => 'city',
      ],
      'town' => [
        'data' => $this->t('町域'),
        'field' => 'town',
      ],
    ];
  }

  /**
   * Service から受け取った配列をテーブル行の Render Array に変換します。
   *
   * @param array $postcodes
   *   PostcodeService::findAll() の結果。
   *
   * @return array
   *   #type table の #rows に渡す配列。
   */
  protected function buildTableRows(array $postcodes): array {
    $rows = [];

    foreach ($postcodes as $postcode) {
      $rows[] = [
        'zipcode' => [
          'data' => [
            '#plain_text' => (string) ($postcode['zipcode'] ?? ''),
          ],
        ],
        'prefecture' => [
          'data' => [
            '#plain_text' => (string) ($postcode['prefecture'] ?? ''),
          ],
        ],
        'city' => [
          'data' => [
            '#plain_text' => (string) ($postcode['city'] ?? ''),
          ],
        ],
        'town' => [
          'data' => [
            '#plain_text' => (string) ($postcode['town'] ?? ''),
          ],
        ],
      ];
    }

    return $rows;
  }

}
