<?php

declare(strict_types=1);

namespace Drupal\analyze_seo\Plugin\Analyze;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Exception\AiRateLimitException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\analyze\AnalyzePluginBase;
use Drupal\analyze\BatchableAnalyzerInterface;
use Drupal\analyze\HelperInterface;
use Drupal\analyze_seo\Service\SeoInspectorService;
use Drupal\analyze_seo\Service\SeoStorageService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SEO analyser for content entities.
 *
 * @Analyze(
 *   id = "analyze_seo_checker",
 *   label = @Translation("SEO Analysis"),
 *   description = @Translation("Checks content for traditional SEO factors: title tags, meta descriptions, headings, images, links, and structured data.")
 * )
 */
final class SeoAnalyzer extends AnalyzePluginBase implements BatchableAnalyzerInterface {

  /**
   * Human-readable labels for each check.
   *
   * @var array<string, string>
   */
  private const CHECK_LABELS = [
    'title_tag' => 'Title Tag',
    'meta_description' => 'Meta Description',
    'url_structure' => 'URL Structure',
    'heading_hierarchy' => 'Heading Hierarchy',
    'image_optimization' => 'Image Alt Text',
    'content_length' => 'Content Length',
    'link_profile' => 'Link Profile',
    'canonical_url' => 'Canonical URL',
    'open_graph' => 'Open Graph Tags',
    'schema_org' => 'Structured Data',
    'keyword_coherence' => 'Keyword Coherence (AI)',
    'readability' => 'Readability (AI)',
  ];

  /**
   * Recommendation text keyed by check ID.
   *
   * @var array<string, string>
   */
  /**
   * Educational text explaining why each check matters for SEO.
   *
   * @var array<string, string>
   */
  private const CHECK_WHY = [
    'title_tag' => 'The title tag is the single most important on-page SEO element. Search engines display it as the clickable headline in results, and it heavily influences ranking for your target keywords.',
    'meta_description' => 'While not a direct ranking factor, the meta description controls the snippet shown in search results. A compelling description increases click-through rate, which indirectly improves rankings.',
    'url_structure' => 'Clean, descriptive URLs help search engines and users understand page content before visiting. Short, keyword-rich URLs tend to rank better and earn more clicks.',
    'heading_hierarchy' => 'Search engines use heading hierarchy to understand content structure and topic relationships. A clear hierarchy (one H1, properly nested H2s and H3s) makes content easier to crawl and index.',
    'image_optimization' => 'Alt text makes images accessible to screen readers and search engine crawlers. Images with descriptive alt text can rank in image search and contribute to the page\'s topical relevance.',
    'content_length' => 'Search engines generally favour comprehensive content that thoroughly covers a topic. Longer content provides more opportunities for natural keyword usage and tends to attract more backlinks.',
    'link_profile' => 'Internal links distribute page authority and help search engines discover content. External links to authoritative sources signal credibility. Good anchor text provides context for linked pages.',
    'canonical_url' => 'Canonical tags prevent duplicate content issues by telling search engines which version of a page is the "original". Without one, search engines may split ranking signals across multiple URLs.',
    'open_graph' => 'Open Graph tags control how your page appears when shared on social platforms. Rich social previews drive more engagement and traffic, creating social signals that can indirectly benefit SEO.',
    'schema_org' => 'Structured data helps search engines understand content meaning, not just content text. It enables rich results (star ratings, FAQs, breadcrumbs) that increase visibility and click-through rates.',
    'keyword_coherence' => 'When title, description, H1, and body text all focus on the same topic, search engines have a stronger signal about what the page is about. Misalignment dilutes relevance.',
    'readability' => 'Content that is easy to read keeps visitors on the page longer, reducing bounce rate. Search engines interpret engagement metrics as quality signals.',
  ];

  private const RECOMMENDATIONS = [
    'title_tag' => 'Write a unique, descriptive title between 30 and 60 characters that includes the primary topic of the page.',
    'meta_description' => 'Write a compelling meta description between 120 and 160 characters that summarises the page content and encourages clicks.',
    'url_structure' => 'Use short, lowercase URLs with hyphens as word separators. Keep paths under 75 characters and avoid special characters.',
    'heading_hierarchy' => 'Use exactly one H1 per page and nest subheadings (H2, H3, etc.) without skipping levels.',
    'image_optimization' => 'Add descriptive alt text to all images. Alt text should describe the image content concisely for both accessibility and search engines.',
    'content_length' => 'Expand the content to meet the minimum word count. Longer, comprehensive content tends to rank better for informational queries.',
    'link_profile' => 'Include at least 2 internal links to related content and add external links to authoritative sources. Ensure all links have descriptive anchor text.',
    'canonical_url' => 'Add a canonical URL tag pointing to the preferred version of this page to prevent duplicate content issues.',
    'open_graph' => 'Add Open Graph meta tags (og:title, og:description, og:type, og:image) so the page displays properly when shared on social platforms.',
    'schema_org' => 'Add JSON-LD structured data with an appropriate content-type schema (Article, Product, WebPage, etc.) to help search engines understand your content.',
    'keyword_coherence' => 'Align the primary topic across title, meta description, H1, and body text. Consistent topical focus helps search engines understand your page.',
    'readability' => 'Improve readability by using shorter sentences, simpler vocabulary, and clear paragraph structure. Aim for a reading level appropriate to your audience.',
  ];

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    HelperInterface $helper,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory,
    private readonly SeoInspectorService $contentInspector,
    private readonly SeoStorageService $storage,
    private readonly ?AiProviderPluginManager $aiProvider,
    private readonly ?PromptJsonDecoderInterface $promptJsonDecoder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $helper, $currentUser, $configFactory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $ai_provider = $container->has('ai.provider') ? $container->get('ai.provider') : NULL;
    $prompt_decoder = $container->has('ai.prompt_json_decode') ? $container->get('ai.prompt_json_decode') : NULL;

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('analyze.helper'),
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('analyze_seo.content_inspector'),
      $container->get('analyze_seo.storage'),
      $ai_provider,
      $prompt_decoder,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity): bool {
    return $this->currentUser->hasPermission('access seo reports');
  }

  /**
   * {@inheritdoc}
   */
  public function renderSummary(EntityInterface $entity): array {
    $results = $this->getOrComputeResults($entity);
    if ($results === NULL) {
      return $this->createStatusTable('No content available for SEO analysis.');
    }

    return [
      '#theme' => 'analyze_gauge',
      '#caption' => $this->t('SEO Analysis'),
      '#range_min_label' => $this->t('Poor'),
      '#range_mid_label' => $this->t('Fair'),
      '#range_max_label' => $this->t('Good'),
      '#range_min' => 0,
      '#range_max' => 100,
      '#value' => $results['overall_score'] / 100,
      '#display_value' => $results['overall_score'] . '%',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function renderFullReport(EntityInterface $entity): array {
    $results = $this->getOrComputeResults($entity);
    if ($results === NULL) {
      return $this->createStatusTable('No content available for SEO analysis.');
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['analyze-full-report', 'analyze-seo-report']],
      '#attached' => ['library' => ['analyze_seo/full-report']],
    ];

    $score = $results['overall_score'];
    $check_results = $results['check_results'] ?? [];
    $grouped = $this->groupChecksByStatus($check_results);
    $pass_count = count($grouped['pass']);
    $partial_count = count($grouped['partial']);
    $fail_count = count($grouped['fail']);

    $build['score_summary'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['analyze-full-report__score-summary', 'gin-layer-wrapper']],
    ];
    $build['score_summary']['gauge'] = [
      '#theme' => 'analyze_circular_gauge',
      '#caption' => $this->t('SEO Analysis'),
      '#range_min_label' => $this->t('Poor'),
      '#range_mid_label' => $this->t('Fair'),
      '#range_max_label' => $this->t('Good'),
      '#range_min' => 0,
      '#range_max' => 100,
      '#value' => $score / 100,
      '#display_value' => $score . '%',
    ];
    $build['score_summary']['text'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['analyze-full-report__score-text']],
    ];
    $build['score_summary']['text']['message'] = [
      '#markup' => $this->getSeoScoreSummaryText($score),
    ];

    $build['stats'] = [
      '#type' => 'inline_template',
      '#template' => '<div class="analyze-full-report__stats"><span class="analyze-full-report__stat analyze-full-report__stat--pass"><span class="analyze-full-report__stat-dot"></span>{{ pass }} {{ pass_label }}</span><span class="analyze-full-report__stat analyze-full-report__stat--partial"><span class="analyze-full-report__stat-dot"></span>{{ partial }} {{ partial_label }}</span><span class="analyze-full-report__stat analyze-full-report__stat--fail"><span class="analyze-full-report__stat-dot"></span>{{ fail }} {{ fail_label }}</span></div>',
      '#context' => [
        'pass' => $pass_count,
        'pass_label' => (string) $this->t('passed'),
        'partial' => $partial_count,
        'partial_label' => (string) $this->t('to improve'),
        'fail' => $fail_count,
        'fail_label' => (string) $this->t('to fix'),
      ],
      '#weight' => 5,
    ];

    $weight = 10;
    if (!empty($grouped['fail'])) {
      $build['section_fail'] = $this->buildCheckSection(
        $this->t('Needs attention (@count)', ['@count' => count($grouped['fail'])]),
        $grouped['fail'],
        'fail',
        TRUE,
      );
      $build['section_fail']['#weight'] = $weight++;
    }

    if (!empty($grouped['partial'])) {
      $build['section_partial'] = $this->buildCheckSection(
        $this->t('Can be improved (@count)', ['@count' => count($grouped['partial'])]),
        $grouped['partial'],
        'partial',
        TRUE,
      );
      $build['section_partial']['#weight'] = $weight++;
    }

    if (!empty($grouped['pass'])) {
      $build['section_pass'] = $this->buildCheckSection(
        $this->t('Passed (@count)', ['@count' => count($grouped['pass'])]),
        $grouped['pass'],
        'pass',
        FALSE,
      );
      $build['section_pass']['#weight'] = $weight;
    }

    return $build;
  }

  /**
   * Returns a contextual summary sentence for the overall SEO score.
   */
  private function getSeoScoreSummaryText(int $score): string {
    if ($score >= 75) {
      return (string) $this->t('This page has strong SEO fundamentals. Review any remaining items below for further optimisation.');
    }
    if ($score >= 50) {
      return (string) $this->t('This page covers some SEO basics but has room for improvement. Address the items under "Needs attention" to strengthen search engine visibility.');
    }
    return (string) $this->t('This page has significant SEO gaps that may limit its search engine visibility. The checks below identify specific improvements, starting with the most impactful.');
  }

  /**
   * Groups check results into pass, partial, and fail buckets.
   *
   * @param array<string, array{score: int, max: int, details: array<string, mixed>}> $check_results
   *   The check results.
   *
   * @return array<string, array<string, array{score: int, max: int, details: array<string, mixed>}>>
   *   Grouped results keyed by 'fail', 'partial', 'pass'.
   */
  private function groupChecksByStatus(array $check_results): array {
    $grouped = ['fail' => [], 'partial' => [], 'pass' => []];

    foreach ($check_results as $check_id => $check) {
      $pct = $check['max'] > 0 ? ($check['score'] / $check['max']) * 100 : 0;
      if ($pct >= 75) {
        $grouped['pass'][$check_id] = $check;
      }
      elseif ($pct >= 50) {
        $grouped['partial'][$check_id] = $check;
      }
      else {
        $grouped['fail'][$check_id] = $check;
      }
    }

    return $grouped;
  }

  /**
   * Builds a collapsible section of check results.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $title
   *   The section title.
   * @param array<string, array{score: int, max: int, details: array<string, mixed>}> $checks
   *   The checks in this group.
   * @param string $status
   *   One of 'fail', 'partial', 'pass'.
   * @param bool $open
   *   Whether the section starts expanded.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildCheckSection(string|\Drupal\Core\StringTranslation\TranslatableMarkup $title, array $checks, string $status, bool $open): array {
    $section = [
      '#type' => 'html_tag',
      '#tag' => 'details',
      '#attributes' => [
        'class' => ['analyze-full-report__section', 'analyze-full-report__section--' . $status],
      ],
    ];
    if ($open) {
      $section['#attributes']['open'] = 'open';
    }

    $section['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'summary',
      '#value' => $title,
    ];

    $weight = 0;
    foreach ($checks as $check_id => $check) {
      $section['check_' . $check_id] = $this->buildCheckItem($check_id, $check, $status);
      $section['check_' . $check_id]['#weight'] = $weight++;
    }

    return $section;
  }

  /**
   * Builds a single check item with diagnosis, education, and action.
   *
   * @param string $check_id
   *   The check machine name.
   * @param array{score: int, max: int, details: array<string, mixed>} $check
   *   The check result.
   * @param string $status
   *   One of 'fail', 'partial', 'pass'.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function buildCheckItem(string $check_id, array $check, string $status): array {
    $label = self::CHECK_LABELS[$check_id] ?? $check_id;
    $icon_map = ['pass' => "\xE2\x9C\x93", 'partial' => "\xE2\x97\x8B", 'fail' => "\xE2\x9C\x97"];
    $issues = $check['details']['issues'] ?? [];

    $item = [
      '#type' => 'container',
      '#attributes' => ['class' => ['analyze-check']],
    ];

    $item['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => ['class' => ['analyze-check__header']],
    ];
    $item['header']['icon'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $icon_map[$status],
      '#attributes' => ['class' => ['analyze-check__icon', 'analyze-check__icon--' . $status]],
    ];
    $item['header']['name'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $label,
      '#attributes' => ['class' => ['analyze-check__name']],
    ];
    $item['header']['score'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => $check['score'] . '/' . $check['max'],
      '#attributes' => ['class' => ['analyze-check__score']],
    ];

    if (!empty($issues)) {
      $item['diagnosis'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => implode(' ', $issues),
        '#attributes' => ['class' => ['analyze-check__diagnosis']],
      ];
    }
    if ($status !== 'pass' && isset(self::CHECK_WHY[$check_id])) {
      $item['why'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => self::CHECK_WHY[$check_id],
        '#attributes' => ['class' => ['analyze-check__why']],
      ];
    }

    if ($status !== 'pass' && isset(self::RECOMMENDATIONS[$check_id])) {
      $item['action'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => self::RECOMMENDATIONS[$check_id],
        '#attributes' => ['class' => ['analyze-check__action']],
      ];
    }

    $data_parts = $this->buildSeoDataLine($check_id, $check);
    if (!empty($data_parts)) {
      $item['data'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $data_parts,
        '#attributes' => ['class' => ['analyze-check__data']],
      ];
    }

    return $item;
  }

  /**
   * Builds a data summary line for a check.
   */
  private function buildSeoDataLine(string $check_id, array $check): string {
    $details = $check['details'] ?? [];
    $parts = [];

    switch ($check_id) {
      case 'title_tag':
        if (isset($details['length'])) {
          $parts[] = $this->t('@length characters (aim for 30 to 60)', ['@length' => $details['length']]);
        }
        break;

      case 'meta_description':
        if (isset($details['length'])) {
          $parts[] = $this->t('@length characters (aim for 120 to 160)', ['@length' => $details['length']]);
        }
        break;

      case 'heading_hierarchy':
        if (isset($details['heading_count'])) {
          $parts[] = $this->t('@count headings', ['@count' => $details['heading_count']]);
        }
        if (isset($details['h1_count'])) {
          $parts[] = $this->t('@count H1', ['@count' => $details['h1_count']]);
        }
        break;

      case 'image_optimization':
        if (isset($details['total_images'])) {
          $with_alt = $details['with_alt'] ?? 0;
          $parts[] = $this->t('@with of @total images have alt text', [
            '@with' => $with_alt,
            '@total' => $details['total_images'],
          ]);
        }
        break;

      case 'content_length':
        if (isset($details['word_count'])) {
          $min = $details['min_word_count'] ?? 300;
          $parts[] = $this->t('@count words (minimum: @min)', [
            '@count' => $details['word_count'],
            '@min' => $min,
          ]);
        }
        break;

      case 'link_profile':
        if (isset($details['internal_links'])) {
          $parts[] = $this->t('@int internal, @ext external links', [
            '@int' => $details['internal_links'],
            '@ext' => $details['external_links'] ?? 0,
          ]);
        }
        break;

      case 'open_graph':
        if (!empty($details['present'])) {
          $parts[] = $this->t('Present: @tags', ['@tags' => implode(', ', $details['present'])]);
        }
        break;

      case 'schema_org':
        if (!empty($details['schemas_found'])) {
          $types = [];
          foreach ($details['schemas_found'] as $type) {
            $types[] = is_array($type) ? implode(', ', $type) : $type;
          }
          $parts[] = $this->t('Found: @types', ['@types' => implode(', ', $types)]);
        }
        break;
    }

    return implode(' · ', $parts);
  }

  /**
   * {@inheritdoc}
   */
  public function processEntity(EntityInterface $entity, bool $force_refresh = FALSE): bool {
    $content_hash = $this->contentInspector->generateContentHash($entity);

    if (!$force_refresh && $this->storage->hasValidCache($entity, $content_hash)) {
      return FALSE;
    }

    if ($force_refresh) {
      $this->storage->deleteScores($entity);
    }

    $check_results = $this->runAllChecks($entity);
    if (empty($check_results)) {
      return FALSE;
    }

    $overall = $this->calculateOverallScore($check_results);
    $this->storage->saveScores($entity, $overall, $check_results, $content_hash);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasResults(EntityInterface $entity): bool {
    $content_hash = $this->contentInspector->generateContentHash($entity);
    return $this->storage->hasValidCache($entity, $content_hash);
  }

  /**
   * {@inheritdoc}
   */
  public function countAnalyzedEntities(string $entity_type_id, string $bundle): int {
    return $this->storage->countAnalyzedEntities($entity_type_id, $bundle);
  }

  /**
   * Gets cached results or computes them.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array<string, mixed>|null
   *   Score data or NULL.
   */
  private function getOrComputeResults(EntityInterface $entity): ?array {
    $content_hash = $this->contentInspector->generateContentHash($entity);

    $cached = $this->storage->getValidScores($entity, $content_hash);
    if ($cached !== NULL) {
      return $cached;
    }

    $check_results = $this->runAllChecks($entity);
    if (empty($check_results)) {
      return NULL;
    }

    $overall = $this->calculateOverallScore($check_results);
    $this->storage->saveScores($entity, $overall, $check_results, $content_hash);

    return [
      'overall_score' => $overall,
      'check_results' => $check_results,
    ];
  }

  /**
   * Runs all enabled checks on an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array<string, array{score: int, max: int, details: array<string, mixed>}>
   *   Combined check results.
   */
  private function runAllChecks(EntityInterface $entity): array {
    $results = $this->contentInspector->inspect($entity);

    $config = $this->configFactory->get('analyze_seo.settings');
    $ai_enabled = (bool) $config->get('ai_checks_enabled');
    $checks = $config->get('checks') ?: [];

    if ($ai_enabled && $this->aiProvider !== NULL) {
      if (!empty($checks['keyword_coherence'])) {
        $results['keyword_coherence'] = $this->checkKeywordCoherence($entity);
      }
      if (!empty($checks['readability'])) {
        $results['readability'] = $this->checkReadability($entity);
      }
    }

    return $results;
  }

  /**
   * AI check: keyword coherence across page elements.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkKeywordCoherence(EntityInterface $entity): array {
    $content = $this->contentInspector->getPlainText($entity);
    if (empty($content) || mb_strlen($content) < 100) {
      return ['score' => 0, 'max' => 3, 'details' => ['issues' => ['Content too short for keyword analysis.']]];
    }

    $truncated = mb_substr($content, 0, 4000);
    $prompt = <<<EOT
<task>Analyse the following page content for keyword and topic coherence across its SEO elements.</task>
<text>
$truncated
</text>

<instructions>
Score each dimension from 0 to 1:
1. topic_alignment: Are the title, headings, and body text focused on the same primary topic?
2. keyword_distribution: Are important terms used naturally throughout the content without over-stuffing?
3. search_intent_match: Does the content structure match the likely search intent (informational, transactional, navigational)?
</instructions>
<output_format>Respond with JSON only: {"topic_alignment": number, "keyword_distribution": number, "search_intent_match": number}</output_format>
EOT;

    $ai_result = $this->callAi($prompt);
    if ($ai_result === NULL) {
      return ['score' => 0, 'max' => 3, 'details' => ['issues' => ['AI provider not available.']]];
    }

    $score = 0;
    $details = ['ai_scores' => $ai_result, 'issues' => []];

    if (($ai_result['topic_alignment'] ?? 0) >= 0.5) {
      $score++;
    }
    else {
      $details['issues'][] = 'Title, headings, and body are not topically aligned.';
    }
    if (($ai_result['keyword_distribution'] ?? 0) >= 0.5) {
      $score++;
    }
    else {
      $details['issues'][] = 'Key terms are not well distributed through the content.';
    }
    if (($ai_result['search_intent_match'] ?? 0) >= 0.5) {
      $score++;
    }
    else {
      $details['issues'][] = 'Content structure does not match likely search intent.';
    }

    return ['score' => $score, 'max' => 3, 'details' => $details];
  }

  /**
   * AI check: readability assessment.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkReadability(EntityInterface $entity): array {
    $content = $this->contentInspector->getPlainText($entity);
    if (empty($content) || mb_strlen($content) < 100) {
      return ['score' => 0, 'max' => 3, 'details' => ['issues' => ['Content too short for readability analysis.']]];
    }

    $truncated = mb_substr($content, 0, 4000);
    $prompt = <<<EOT
<task>Assess the readability of the following text for a general web audience.</task>
<text>
$truncated
</text>

<instructions>
Score each dimension from 0 to 1:
1. sentence_clarity: Are sentences clear, concise, and easy to parse on first reading?
2. vocabulary_accessibility: Is the vocabulary appropriate for the target audience, avoiding unnecessary jargon?
3. structure_scannability: Is the content well-structured for scanning, with clear paragraphs, lists, or visual breaks?
</instructions>
<output_format>Respond with JSON only: {"sentence_clarity": number, "vocabulary_accessibility": number, "structure_scannability": number}</output_format>
EOT;

    $ai_result = $this->callAi($prompt);
    if ($ai_result === NULL) {
      return ['score' => 0, 'max' => 3, 'details' => ['issues' => ['AI provider not available.']]];
    }

    $score = 0;
    $details = ['ai_scores' => $ai_result, 'issues' => []];

    if (($ai_result['sentence_clarity'] ?? 0) >= 0.5) {
      $score++;
    }
    else {
      $details['issues'][] = 'Sentences are not sufficiently clear or concise.';
    }
    if (($ai_result['vocabulary_accessibility'] ?? 0) >= 0.5) {
      $score++;
    }
    else {
      $details['issues'][] = 'Vocabulary may be too complex for the target audience.';
    }
    if (($ai_result['structure_scannability'] ?? 0) >= 0.5) {
      $score++;
    }
    else {
      $details['issues'][] = 'Content is not well-structured for scanning.';
    }

    return ['score' => $score, 'max' => 3, 'details' => $details];
  }

  /**
   * Calls the AI provider with a prompt.
   *
   * @param string $prompt
   *   The prompt text.
   *
   * @return array<string, float>|null
   *   Decoded response or NULL on failure.
   */
  private function callAi(string $prompt): ?array {
    if ($this->aiProvider === NULL || $this->promptJsonDecoder === NULL) {
      return NULL;
    }

    try {
      if (!$this->aiProvider->hasProvidersForOperationType('chat', TRUE)) {
        return NULL;
      }

      $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
      if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
        return NULL;
      }

      $provider = $this->aiProvider->createInstance($defaults['provider_id']);
      if (method_exists($provider, 'setConfiguration')) {
        $provider->setConfiguration(['temperature' => 0.1]);
      }

      $messages = new ChatInput([new ChatMessage('user', $prompt)]);
      // ProviderProxy has no declared chat(); PHPStan cannot resolve it.
      // __call() is the same dispatch, so events and exception mapping stay.
      $response = $provider
        ->__call('chat', [$messages, $defaults['model_id']])
        ->getNormalized();

      $decoded = $this->promptJsonDecoder->decode($response);
      if (!is_array($decoded)) {
        return NULL;
      }

      return $decoded;
    }
    catch (AiRateLimitException $e) {
      throw $e;
    }
    catch (\Exception) {
      return NULL;
    }
  }

  /**
   * Calculates the overall SEO score from individual check results.
   *
   * @param array<string, array{score: int, max: int, details: array<string, mixed>}> $check_results
   *   Individual check results.
   *
   * @return int
   *   Overall score 0-100.
   */
  private function calculateOverallScore(array $check_results): int {
    $total_score = 0;
    $total_max = 0;

    foreach ($check_results as $check) {
      $total_score += $check['score'];
      $total_max += $check['max'];
    }

    if ($total_max === 0) {
      return 0;
    }

    return (int) round(($total_score / $total_max) * 100);
  }

  /**
   * Creates a fallback status table.
   *
   * @param string $message
   *   The status message.
   *
   * @return array<string, mixed>
   *   Render array.
   */
  private function createStatusTable(string $message): array {
    return [
      '#theme' => 'analyze_table',
      '#table_title' => 'SEO Analysis',
      '#rows' => [
        [
          'label' => 'Status',
          'data' => $message,
        ],
      ],
    ];
  }

}
