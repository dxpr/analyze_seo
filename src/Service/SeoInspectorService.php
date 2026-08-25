<?php

declare(strict_types=1);

namespace Drupal\analyze_seo\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Deterministic content inspector for SEO checks.
 */
final class SeoInspectorService {

  use DependencySerializationTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly RendererInterface $renderer,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Runs all enabled deterministic checks on an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to inspect.
   *
   * @return array<string, array{score: int, max: int, details: array<string, mixed>}>
   *   Keyed array of check results.
   */
  public function inspect(EntityInterface $entity): array {
    $html = $this->renderEntity($entity);
    if (empty($html)) {
      return [];
    }

    $config = $this->configFactory->get('analyze_seo.settings');
    $checks = $config->get('checks') ?: [];

    $doc = new \DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);

    $results = [];

    if (!empty($checks['title_tag'])) {
      $results['title_tag'] = $this->checkTitleTag($entity);
    }
    if (!empty($checks['meta_description'])) {
      $results['meta_description'] = $this->checkMetaDescription($doc);
    }
    if (!empty($checks['url_structure'])) {
      $results['url_structure'] = $this->checkUrlStructure($entity);
    }
    if (!empty($checks['heading_hierarchy'])) {
      $results['heading_hierarchy'] = $this->checkHeadingHierarchy($doc, $entity);
    }
    if (!empty($checks['image_optimization'])) {
      $results['image_optimization'] = $this->checkImageOptimization($doc);
    }
    if (!empty($checks['content_length'])) {
      $results['content_length'] = $this->checkContentLength($doc);
    }
    if (!empty($checks['link_profile'])) {
      $results['link_profile'] = $this->checkLinkProfile($doc);
    }
    if (!empty($checks['canonical_url'])) {
      $results['canonical_url'] = $this->checkCanonicalUrl($doc);
    }
    if (!empty($checks['open_graph'])) {
      $results['open_graph'] = $this->checkOpenGraph($doc);
    }
    if (!empty($checks['schema_org'])) {
      $results['schema_org'] = $this->checkSchemaOrg($doc);
    }

    return $results;
  }

  /**
   * Checks page title presence and length using the entity label.
   *
   * Drupal renders the entity label as the page title tag on canonical pages,
   * but the entity view builder does not include the <title> element. We use
   * the entity label directly to match what search engines see.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkTitleTag(EntityInterface $entity): array {
    $score = 0;
    $max = 3;
    $details = ['issues' => []];

    $title_text = trim((string) $entity->label());
    if (empty($title_text)) {
      $details['issues'][] = 'No page title found.';
      return ['score' => $score, 'max' => $max, 'details' => $details];
    }

    $length = mb_strlen($title_text);
    $details['title'] = $title_text;
    $details['length'] = $length;
    $score++;

    if ($length >= 30 && $length <= 60) {
      $score++;
    }
    elseif ($length < 30) {
      $details['issues'][] = sprintf('Title too short (%d characters); aim for 30 to 60.', $length);
    }
    else {
      $details['issues'][] = sprintf('Title too long (%d characters); search engines may truncate after 60.', $length);
    }

    if ($length <= 65) {
      $score++;
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Checks meta description presence and length.
   *
   * @param \DOMDocument $doc
   *   The parsed HTML document.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkMetaDescription(\DOMDocument $doc): array {
    $score = 0;
    $max = 3;
    $details = ['issues' => []];

    $metas = $doc->getElementsByTagName('meta');
    $description = '';
    foreach ($metas as $meta) {
      $name = strtolower($meta->getAttribute('name'));
      if ($name === 'description') {
        $description = $meta->getAttribute('content');
        break;
      }
    }

    $details['description'] = $description;
    $length = mb_strlen($description);
    $details['length'] = $length;

    if (!empty($description)) {
      $score++;
    }
    else {
      $details['issues'][] = 'No meta description found.';
      return ['score' => $score, 'max' => $max, 'details' => $details];
    }

    if ($length >= 120 && $length <= 160) {
      $score++;
    }
    elseif ($length < 70) {
      $details['issues'][] = sprintf('Meta description too short (%d characters); aim for 120 to 160.', $length);
    }
    elseif ($length > 160) {
      $details['issues'][] = sprintf('Meta description too long (%d characters); search engines may truncate after 160.', $length);
    }
    else {
      $score++;
    }

    if ($length <= 170) {
      $score++;
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Checks URL structure for SEO best practices.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkUrlStructure(EntityInterface $entity): array {
    $score = 0;
    $max = 3;
    $details = ['issues' => []];

    $url = '';
    if ($entity->hasLinkTemplate('canonical')) {
      $url = $entity->toUrl('canonical')->toString();
    }

    if (empty($url)) {
      $details['issues'][] = 'No canonical URL available for this entity.';
      return ['score' => $score, 'max' => $max, 'details' => $details];
    }

    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $details['path'] = $path;
    $details['path_length'] = strlen($path);

    if (strlen($path) <= 75) {
      $score++;
    }
    else {
      $details['issues'][] = sprintf('URL path too long (%d characters); keep under 75.', strlen($path));
    }

    if (!preg_match('/_/', $path)) {
      $score++;
    }
    else {
      $details['issues'][] = 'URL contains underscores; prefer hyphens for word separation.';
    }

    if ($path === strtolower($path) && preg_match('/^[a-z0-9\-\/\.]*$/', $path)) {
      $score++;
    }
    else {
      $details['issues'][] = 'URL contains uppercase characters or special characters.';
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Checks heading hierarchy for proper structure.
   *
   * Drupal renders the entity label as the page H1 via the page title block,
   * but the entity view builder does not include it. We count the entity label
   * as the H1 and scan the rendered fields for H2-H6.
   *
   * @param \DOMDocument $doc
   *   The parsed HTML document.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity (its label is the page H1).
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkHeadingHierarchy(\DOMDocument $doc, EntityInterface $entity): array {
    $headings = [];

    $entity_label = trim((string) $entity->label());
    if (!empty($entity_label)) {
      $headings[] = ['level' => 1, 'text' => $entity_label];
    }

    for ($level = 2; $level <= 6; $level++) {
      $elements = $doc->getElementsByTagName('h' . $level);
      foreach ($elements as $el) {
        $headings[] = [
          'level' => $level,
          'text' => trim($el->textContent),
        ];
      }
    }

    $score = 0;
    $max = 3;
    $h1_count = !empty($entity_label) ? 1 : 0;
    $details = [
      'heading_count' => count($headings),
      'h1_count' => $h1_count,
      'issues' => [],
    ];

    if (count($headings) > 0) {
      $score++;
    }
    else {
      $details['issues'][] = 'No headings found.';
    }

    if ($h1_count === 1) {
      $score++;
    }
    else {
      $details['issues'][] = 'No H1 heading found (entity has no title).';
    }

    $levels_used = array_unique(array_column($headings, 'level'));
    sort($levels_used);
    $has_skip = FALSE;
    if (count($levels_used) > 1) {
      for ($i = 1; $i < count($levels_used); $i++) {
        if ($levels_used[$i] - $levels_used[$i - 1] > 1) {
          $has_skip = TRUE;
          $details['issues'][] = sprintf('Heading level skip: H%d to H%d.', $levels_used[$i - 1], $levels_used[$i]);
        }
      }
    }
    if (!$has_skip && count($headings) > 1) {
      $score++;
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Checks image alt text coverage.
   *
   * @param \DOMDocument $doc
   *   The parsed HTML document.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkImageOptimization(\DOMDocument $doc): array {
    $score = 0;
    $max = 3;

    $images = $doc->getElementsByTagName('img');
    $total = $images->length;
    $with_alt = 0;
    $empty_alt = 0;
    $missing_alt = 0;

    foreach ($images as $img) {
      if (!$img->hasAttribute('alt')) {
        $missing_alt++;
      }
      elseif (trim($img->getAttribute('alt')) === '') {
        $empty_alt++;
      }
      else {
        $with_alt++;
      }
    }

    $details = [
      'total_images' => $total,
      'with_alt' => $with_alt,
      'empty_alt' => $empty_alt,
      'missing_alt' => $missing_alt,
      'issues' => [],
    ];

    if ($total === 0) {
      $details['issues'][] = 'No images found; consider adding relevant images.';
      return ['score' => $score, 'max' => $max, 'details' => $details];
    }

    $score++;

    $alt_ratio = $total > 0 ? $with_alt / $total : 0;

    if ($alt_ratio >= 0.5) {
      $score++;
    }
    else {
      $details['issues'][] = sprintf('Only %d%% of images have descriptive alt text.', (int) round($alt_ratio * 100));
    }

    if ($alt_ratio >= 0.9) {
      $score++;
    }
    elseif ($missing_alt > 0) {
      $details['issues'][] = sprintf('%d image(s) have no alt attribute.', $missing_alt);
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Checks content word count against configurable minimum.
   *
   * @param \DOMDocument $doc
   *   The parsed HTML document.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkContentLength(\DOMDocument $doc): array {
    $score = 0;
    $max = 2;

    $config = $this->configFactory->get('analyze_seo.settings');
    $min_words = (int) ($config->get('min_word_count') ?: 300);

    $body = $doc->getElementsByTagName('body');
    $text = '';
    if ($body->length > 0) {
      $text = $body->item(0)->textContent;
    }
    else {
      $text = $doc->textContent;
    }

    $text = preg_replace('/\s+/', ' ', trim($text)) ?? '';
    $word_count = str_word_count($text);

    $details = [
      'word_count' => $word_count,
      'min_word_count' => $min_words,
      'issues' => [],
    ];

    if ($word_count > 0) {
      $score++;
    }
    else {
      $details['issues'][] = 'No text content found.';
      return ['score' => $score, 'max' => $max, 'details' => $details];
    }

    if ($word_count >= $min_words) {
      $score++;
    }
    else {
      $details['issues'][] = sprintf('Content has %d words; aim for at least %d.', $word_count, $min_words);
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Checks link profile for internal and external links.
   *
   * @param \DOMDocument $doc
   *   The parsed HTML document.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkLinkProfile(\DOMDocument $doc): array {
    $score = 0;
    $max = 3;

    $links = $doc->getElementsByTagName('a');
    $internal_count = 0;
    $external_count = 0;
    $empty_anchor = 0;

    $request = $this->requestStack->getCurrentRequest();
    $site_host = $request ? $request->getHost() : '';

    foreach ($links as $link) {
      $href = $link->getAttribute('href');
      if (empty($href) || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
        continue;
      }

      $anchor_text = trim($link->textContent);
      if (empty($anchor_text) && !$link->getElementsByTagName('img')->length) {
        $empty_anchor++;
      }

      if ($this->isInternalUrl($href, $site_host)) {
        $internal_count++;
      }
      else {
        $external_count++;
      }
    }

    $details = [
      'internal_links' => $internal_count,
      'external_links' => $external_count,
      'empty_anchor_text' => $empty_anchor,
      'issues' => [],
    ];

    if ($internal_count >= 2) {
      $score++;
    }
    elseif ($internal_count > 0) {
      $details['issues'][] = sprintf('Only %d internal link(s); aim for at least 2.', $internal_count);
    }
    else {
      $details['issues'][] = 'No internal links found.';
    }

    if ($external_count > 0) {
      $score++;
    }

    if ($empty_anchor === 0) {
      $score++;
    }
    else {
      $details['issues'][] = sprintf('%d link(s) have empty anchor text.', $empty_anchor);
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Checks for canonical URL tag.
   *
   * @param \DOMDocument $doc
   *   The parsed HTML document.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkCanonicalUrl(\DOMDocument $doc): array {
    $score = 0;
    $max = 2;
    $details = ['issues' => []];

    $links = $doc->getElementsByTagName('link');
    $canonical = '';
    foreach ($links as $link) {
      if ($link->getAttribute('rel') === 'canonical') {
        $canonical = $link->getAttribute('href');
        break;
      }
    }

    $details['canonical'] = $canonical;

    if (!empty($canonical)) {
      $score++;
    }
    else {
      $details['issues'][] = 'No canonical URL tag found.';
      return ['score' => $score, 'max' => $max, 'details' => $details];
    }

    if (filter_var($canonical, FILTER_VALIDATE_URL) !== FALSE) {
      $score++;
    }
    else {
      $details['issues'][] = 'Canonical URL is not a valid absolute URL.';
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Checks Open Graph meta tag completeness.
   *
   * @param \DOMDocument $doc
   *   The parsed HTML document.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkOpenGraph(\DOMDocument $doc): array {
    $score = 0;
    $max = 4;

    $metas = $doc->getElementsByTagName('meta');
    $og = [];
    foreach ($metas as $meta) {
      $property = $meta->getAttribute('property');
      if (str_starts_with($property, 'og:')) {
        $og[$property] = $meta->getAttribute('content');
      }
    }

    $details = [
      'present' => array_keys($og),
      'missing' => [],
      'issues' => [],
    ];

    $required = ['og:title', 'og:description', 'og:type', 'og:image'];
    foreach ($required as $tag) {
      if (!empty($og[$tag])) {
        $score++;
      }
      else {
        $details['missing'][] = $tag;
      }
    }

    if (!empty($details['missing'])) {
      $details['issues'][] = 'Missing Open Graph tags: ' . implode(', ', $details['missing']) . '.';
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Checks for Schema.org structured data.
   *
   * @param \DOMDocument $doc
   *   The parsed HTML document.
   *
   * @return array{score: int, max: int, details: array<string, mixed>}
   *   Check result.
   */
  private function checkSchemaOrg(\DOMDocument $doc): array {
    $score = 0;
    $max = 2;
    $details = [
      'schemas_found' => [],
      'issues' => [],
    ];

    $scripts = $doc->getElementsByTagName('script');
    $json_ld_items = [];

    foreach ($scripts as $script) {
      if ($script->getAttribute('type') === 'application/ld+json') {
        $content = trim($script->textContent);
        $decoded = json_decode($content, TRUE);
        if ($decoded !== NULL) {
          $json_ld_items[] = $decoded;
        }
      }
    }

    if (!empty($json_ld_items)) {
      $score++;
    }
    else {
      $details['issues'][] = 'No JSON-LD structured data found.';
      return ['score' => $score, 'max' => $max, 'details' => $details];
    }

    $types = [];
    foreach ($json_ld_items as $item) {
      if (isset($item['@graph'])) {
        foreach ($item['@graph'] as $graph_item) {
          if (isset($graph_item['@type'])) {
            $types[] = $graph_item['@type'];
          }
        }
      }
      elseif (isset($item['@type'])) {
        $types[] = $item['@type'];
      }
    }
    $details['schemas_found'] = $types;

    $content_types = ['Article', 'BlogPosting', 'NewsArticle', 'HowTo', 'Product', 'Recipe', 'Review', 'Report', 'TechArticle', 'ScholarlyArticle', 'WebPage', 'FAQPage'];
    $has_content_type = FALSE;
    foreach ($types as $type) {
      $flat_types = is_array($type) ? $type : [$type];
      foreach ($flat_types as $t) {
        if (in_array($t, $content_types, TRUE)) {
          $has_content_type = TRUE;
        }
      }
    }
    if ($has_content_type) {
      $score++;
    }
    else {
      $details['issues'][] = 'No content-type schema found (Article, Product, WebPage, etc.).';
    }

    return ['score' => $score, 'max' => $max, 'details' => $details];
  }

  /**
   * Determines if a URL is internal.
   *
   * @param string $url
   *   The URL to classify.
   * @param string $site_host
   *   The site host.
   *
   * @return bool
   *   TRUE if the URL is internal.
   */
  private function isInternalUrl(string $url, string $site_host): bool {
    if (!preg_match('/^https?:\/\//i', $url)) {
      return TRUE;
    }
    $parsed = parse_url($url);
    if (!isset($parsed['host'])) {
      return TRUE;
    }
    return strcasecmp($parsed['host'], $site_host) === 0;
  }

  /**
   * Gets the plain text content of an entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   Plain text content.
   */
  public function getPlainText(EntityInterface $entity): string {
    $html = $this->renderEntity($entity);
    $text = strip_tags($html);
    $text = str_replace('&nbsp;', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    return trim($text);
  }

  /**
   * Generates a content hash for cache invalidation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   SHA-256 hash of the rendered content.
   */
  public function generateContentHash(EntityInterface $entity): string {
    $html = $this->renderEntity($entity);
    return hash('sha256', $html);
  }

  /**
   * Renders an entity and returns the HTML.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to render.
   *
   * @return string
   *   The rendered HTML.
   */
  private function renderEntity(EntityInterface $entity): string {
    $langcode = $entity->language()->getId();
    $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
    $view = $view_builder->view($entity, 'default', $langcode);
    $rendered = $this->renderer->renderInIsolation($view);
    return (string) $rendered;
  }

}
