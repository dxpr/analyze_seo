<?php

namespace Drupal\analyze_seo\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
/**
 * Hook implementations for analyze_seo.
 */
class AnalyzeSeoViewsHooks
{
    use StringTranslationTrait;
    /**
     * @file
     * Provide views data for analyze_seo module.
     */
    /**
     * Implements hook_views_data().
     */
    #[Hook('views_data')]
    public function viewsData()
    {
        $data = [
        ];
        $data['analyze_seo_scores']['table']['group'] = $this->t('SEO Analysis');
        $data['analyze_seo_scores']['table']['base'] = [
            'field' => 'entity_id',
            'title' => $this->t('SEO Scores'),
            'help' => $this->t('SEO analysis scores for content entities.'),
        ];
        $data['analyze_seo_scores']['entity_type'] = [
            'title' => $this->t('Entity Type'),
            'help' => $this->t('The type of entity that was analysed.'),
            'field' => [
                'id' => 'standard',
            ],
            'filter' => [
                'id' => 'string',
            ],
            'sort' => [
                'id' => 'standard',
            ],
            'argument' => [
                'id' => 'string',
            ],
        ];
        $data['analyze_seo_scores']['entity_id'] = [
            'title' => $this->t('Entity ID'),
            'help' => $this->t('The ID of the analysed entity.'),
            'field' => [
                'id' => 'numeric',
            ],
            'filter' => [
                'id' => 'numeric',
            ],
            'sort' => [
                'id' => 'standard',
            ],
            'argument' => [
                'id' => 'numeric',
            ],
            'relationship' => [
                'id' => 'standard',
                'base' => 'node_field_data',
                'base field' => 'nid',
                'field' => 'entity_id',
                'label' => $this->t('Content'),
                'title' => $this->t('Content'),
                'help' => $this->t('The content entity that was analysed.'),
            ],
        ];
        $data['analyze_seo_scores']['bundle'] = [
            'title' => $this->t('Bundle'),
            'help' => $this->t('The entity bundle.'),
            'field' => [
                'id' => 'standard',
            ],
            'filter' => [
                'id' => 'string',
            ],
            'sort' => [
                'id' => 'standard',
            ],
            'argument' => [
                'id' => 'string',
            ],
        ];
        $data['analyze_seo_scores']['overall_score'] = [
            'title' => $this->t('SEO Score'),
            'help' => $this->t('The overall SEO score (0-100).'),
            'field' => [
                'id' => 'numeric',
            ],
            'filter' => [
                'id' => 'numeric',
            ],
            'sort' => [
                'id' => 'standard',
            ],
            'argument' => [
                'id' => 'numeric',
            ],
        ];
        $data['analyze_seo_scores']['content_hash'] = [
            'title' => $this->t('Content Hash'),
            'help' => $this->t('SHA-256 hash of the rendered content.'),
            'field' => [
                'id' => 'standard',
            ],
            'filter' => [
                'id' => 'string',
            ],
            'sort' => [
                'id' => 'standard',
            ],
        ];
        $data['analyze_seo_scores']['last_checked'] = [
            'title' => $this->t('Last Checked'),
            'help' => $this->t('When the entity was last analysed.'),
            'field' => [
                'id' => 'date',
                'click sortable' => TRUE,
            ],
            'filter' => [
                'id' => 'date',
            ],
            'sort' => [
                'id' => 'date',
            ],
            'argument' => [
                'id' => 'date',
            ],
        ];
        return $data;
    }
}
