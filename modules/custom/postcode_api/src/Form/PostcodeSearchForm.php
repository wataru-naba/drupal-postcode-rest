<?php

namespace Drupal\postcode_api\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * 郵便番号マスタ管理画面の検索フォーム。
 *
 * Form API を使うことで、Drupal 標準のフォームレンダリング、
 * 翻訳、バリデーションの仕組みに乗せられます。
 */
class PostcodeSearchForm extends FormBase {

  /**
   * 現在の Request を取得するサービス。
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $postcodeRequestStack;

  /**
   * PostcodeSearchForm のコンストラクタ。
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   現在の Request を取得するサービス。
   */
  public function __construct(RequestStack $request_stack) {
    $this->postcodeRequestStack = $request_stack;
  }

  /**
   * サービスコンテナから Form に必要な依存を取り出します。
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * フォームIDを返します。
   *
   * @return string
   *   Drupal 内でフォームを識別するための一意なID。
   */
  public function getFormId(): string {
    return 'postcode_api_search_form';
  }

  /**
   * 検索フォームを組み立てます。
   *
   * @param array $form
   *   フォームの Render Array。
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   フォーム状態。
   *
   * @return array
   *   Drupal が HTML に変換するフォーム Render Array。
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $request = $this->postcodeRequestStack->getCurrentRequest();
    $keyword = trim((string) $request?->query->get('keyword', ''));

    // 検索条件を URL に出すため GET を使います。
    // これにより、検索結果ページを共有したりブックマークできます。
    $form['#method'] = 'get';
    $form['#action'] = Url::fromRoute('postcode_api.admin')->toString();
    $form['#attributes']['class'][] = 'postcode-api-search-form';

    $form['keyword'] = [
      '#type' => 'search',
      '#title' => $this->t('検索キーワード'),
      '#default_value' => $keyword,
      '#size' => 40,
      '#maxlength' => 255,
      '#attributes' => [
        'placeholder' => $this->t('郵便番号、都道府県、市区町村、町域'),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('検索'),
    ];

    if ($keyword !== '') {
      $form['actions']['reset'] = [
        '#type' => 'link',
        '#title' => $this->t('リセット'),
        '#url' => Url::fromRoute('postcode_api.admin'),
        '#attributes' => [
          'class' => ['button'],
        ],
      ];
    }

    return $form;
  }

  /**
   * 検索実行時の遷移先を設定します。
   *
   * GET フォームではブラウザが query string を作れますが、ここで明示的に
   * リダイレクトすることで form_id や op などの内部値を URL から除外します。
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $keyword = trim((string) $form_state->getValue('keyword'));
    $query = [];

    if ($keyword !== '') {
      $query['keyword'] = $keyword;
    }

    $form_state->setRedirect('postcode_api.admin', [], [
      'query' => $query,
    ]);
  }

}
