<?php

declare(strict_types=1);

namespace Drupal\analyze_seo\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure SEO analysis checks.
 */
final class SeoSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'analyze_seo_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['analyze_seo.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('analyze_seo.settings');
    $checks = $config->get('checks') ?: [];

    try {
      $reports_url = Url::fromRoute('view.seo_results.page_1');
      if ($reports_url->access()) {
        $form['actions_top'] = [
          '#type' => 'container',
          '#attributes' => ['class' => ['form-actions']],
          '#weight' => -10,
          'report_link' => [
            '#type' => 'link',
            '#title' => $this->t('View reports'),
            '#url' => $reports_url,
            '#attributes' => [
              'class' => ['button', 'button--small', 'button--primary'],
            ],
          ],
        ];
      }
    }
    catch (\Exception $e) {
    }

    $form['deterministic'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Deterministic checks'),
      '#description' => $this->t('These checks run without an AI provider and analyse the HTML output of each entity.'),
    ];

    $deterministic_checks = [
      'title_tag' => [
        'title' => $this->t('Title tag quality'),
        'description' => $this->t('Checks for title tag presence and optimal length (30 to 60 characters).'),
      ],
      'meta_description' => [
        'title' => $this->t('Meta description quality'),
        'description' => $this->t('Checks for meta description presence and optimal length (120 to 160 characters).'),
      ],
      'url_structure' => [
        'title' => $this->t('URL structure'),
        'description' => $this->t('Checks for clean slugs, path length, lowercase, and hyphen usage.'),
      ],
      'heading_hierarchy' => [
        'title' => $this->t('Heading hierarchy'),
        'description' => $this->t('Checks for a single H1, proper heading nesting, and no skipped levels.'),
      ],
      'image_optimization' => [
        'title' => $this->t('Image optimization'),
        'description' => $this->t('Checks that images have descriptive alt text.'),
      ],
      'content_length' => [
        'title' => $this->t('Content length'),
        'description' => $this->t('Checks the word count against a configurable minimum.'),
      ],
      'link_profile' => [
        'title' => $this->t('Link profile'),
        'description' => $this->t('Checks for internal links (at least 2), external links, and non-empty anchor text.'),
      ],
      'canonical_url' => [
        'title' => $this->t('Canonical URL'),
        'description' => $this->t('Checks for a valid canonical link tag.'),
      ],
      'open_graph' => [
        'title' => $this->t('Open Graph tags'),
        'description' => $this->t('Checks for og:title, og:description, og:type, and og:image.'),
      ],
      'schema_org' => [
        'title' => $this->t('Schema.org structured data'),
        'description' => $this->t('Checks for JSON-LD structured data with a content-type schema.'),
      ],
    ];

    foreach ($deterministic_checks as $key => $info) {
      $form['deterministic'][$key] = [
        '#type' => 'checkbox',
        '#title' => $info['title'],
        '#description' => $info['description'],
        '#default_value' => $checks[$key] ?? TRUE,
      ];
    }

    $form['content_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content settings'),
    ];

    $form['content_settings']['min_word_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum word count'),
      '#description' => $this->t('The minimum number of words expected per page for the content length check.'),
      '#default_value' => $config->get('min_word_count') ?? 300,
      '#min' => 50,
      '#max' => 5000,
      '#step' => 50,
    ];

    $form['ai'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('AI-powered checks'),
      '#description' => $this->t('These checks require the <a href="@url">AI module</a> with a configured chat provider.', [
        '@url' => 'https://www.drupal.org/project/ai',
      ]),
    ];

    $ai_module_installed = $this->moduleHandler->moduleExists('ai');

    $form['ai']['ai_checks_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable AI-powered checks'),
      '#default_value' => $config->get('ai_checks_enabled') ?? FALSE,
      '#disabled' => !$ai_module_installed,
      '#description' => $ai_module_installed
        ? $this->t('Use AI to evaluate keyword coherence and readability.')
        : $this->t('Install the <a href="@url">AI module</a> to enable these checks.', [
          '@url' => 'https://www.drupal.org/project/ai',
        ]),
    ];

    $ai_checks = [
      'keyword_coherence' => [
        'title' => $this->t('Keyword coherence'),
        'description' => $this->t('AI evaluates whether title, description, H1, and body text are topically aligned.'),
      ],
      'readability' => [
        'title' => $this->t('Readability'),
        'description' => $this->t('AI evaluates content readability, clarity, and audience appropriateness.'),
      ],
    ];

    foreach ($ai_checks as $key => $info) {
      $form['ai'][$key] = [
        '#type' => 'checkbox',
        '#title' => $info['title'],
        '#description' => $info['description'],
        '#default_value' => $checks[$key] ?? TRUE,
        '#states' => [
          'visible' => [
            ':input[name="ai_checks_enabled"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $check_keys = [
      'title_tag',
      'meta_description',
      'url_structure',
      'heading_hierarchy',
      'image_optimization',
      'content_length',
      'link_profile',
      'canonical_url',
      'open_graph',
      'schema_org',
      'keyword_coherence',
      'readability',
    ];

    $checks = [];
    foreach ($check_keys as $key) {
      $checks[$key] = (bool) $form_state->getValue($key);
    }

    $this->config('analyze_seo.settings')
      ->set('checks', $checks)
      ->set('ai_checks_enabled', (bool) $form_state->getValue('ai_checks_enabled'))
      ->set('min_word_count', (int) $form_state->getValue('min_word_count'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
