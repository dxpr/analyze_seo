<?php

namespace Drupal\analyze_seo\Hook;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\ViewExecutable;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
/**
 * Hook implementations for analyze_seo.
 */
class AnalyzeSeoHooks
{
    use StringTranslationTrait;
    /**
     * Implements hook_help().
     */
    #[Hook('help')]
    public function help($route_name)
    {
        if ($route_name === 'help.page.analyze_seo') {
            return '<p>' . $this->t('Analyses content for SEO: title tags, meta descriptions, heading hierarchy, image alt text, link profile, and optional AI-powered readability checks.') . '</p>';
        }
    }
    /**
     * Implements hook_entity_update().
     */
    #[Hook('entity_update')]
    public function entityUpdate(\Drupal\Core\Entity\EntityInterface $entity): void
    {
        if (_analyze_seo_is_supported_entity($entity)) {
            \Drupal::service('analyze_seo.storage')->deleteScores($entity);
        }
    }
    /**
     * Implements hook_entity_delete().
     */
    #[Hook('entity_delete')]
    public function entityDelete(\Drupal\Core\Entity\EntityInterface $entity): void
    {
        if (_analyze_seo_is_supported_entity($entity)) {
            \Drupal::service('analyze_seo.storage')->deleteScores($entity);
        }
    }
    /**
     * Implements hook_views_pre_view().
     */
    #[Hook('views_pre_view')]
    public function viewsPreView(\Drupal\views\ViewExecutable $view, $display_id, array &$args)
    {
        if ($view->id() === 'seo_results' && $display_id === 'page_1') {
            $current_user = \Drupal::currentUser();
            if ($current_user->hasPermission('administer analyze')) {
                $settings_url = \Drupal\Core\Url::fromRoute('analyze_seo.settings');
                if ($settings_url->access()) {
                    $link = \Drupal\Core\Link::fromTextAndUrl($this->t('Configure settings'), $settings_url);
                    $link = $link->toRenderable();
                    $link['#attributes']['class'][] = 'button';
                    $link['#attributes']['class'][] = 'button--small';
                    $link['#attributes']['class'][] = 'button--primary';
                    $view->attachment_before = [
                        '#type' => 'container',
                        '#attributes' => [
                            'class' => [
                                'form-actions',
                                'views-configure-actions',
                            ],
                        ],
                        '#weight' => -10,
                        'configure_link' => $link,
                        '#attached' => [
                            'library' => [
                                'system/base',
                            ],
                        ],
                    ];
                }
            }
        }
    }
    /**
     * Implements hook_views_color_scale_popover_alter().
     */
    #[Hook('views_color_scale_popover_alter')]
    public function viewsColorScalePopoverAlter(array &$content, array $context): void
    {
        if ($context['view']->storage->get('base_table') !== 'analyze_seo_scores') {
            return;
        }
        $content = [
            '#theme' => 'analyze_gauge',
            '#caption' => $context['field_options']['label'] ?? '',
            '#range_min_label' => $this->t('Poor'),
            '#range_mid_label' => $this->t('Fair'),
            '#range_max_label' => $this->t('Good'),
            '#range_min' => $context['min'],
            '#value' => $context['position'],
            '#display_value' => $context['display_value'],
            '#range_max' => $context['max'],
            '#attached' => [
                'library' => [
                    'analyze/gauge-popover',
                ],
            ],
        ];
    }
}
