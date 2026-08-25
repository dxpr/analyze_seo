<?php

/**
 * @file
 * Provide views data for analyze_seo module.
 */

/**
 * Implements hook_views_data().
 */
function analyze_seo_views_data() {
  $data = [];

  $data['analyze_seo_scores']['table']['group'] = t('SEO Analysis');
  $data['analyze_seo_scores']['table']['base'] = [
    'field' => 'entity_id',
    'title' => t('SEO Scores'),
    'help' => t('SEO analysis scores for content entities.'),
  ];

  $data['analyze_seo_scores']['entity_type'] = [
    'title' => t('Entity Type'),
    'help' => t('The type of entity that was analysed.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  $data['analyze_seo_scores']['entity_id'] = [
    'title' => t('Entity ID'),
    'help' => t('The ID of the analysed entity.'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
    'relationship' => [
      'id' => 'standard',
      'base' => 'node_field_data',
      'base field' => 'nid',
      'field' => 'entity_id',
      'label' => t('Content'),
      'title' => t('Content'),
      'help' => t('The content entity that was analysed.'),
    ],
  ];

  $data['analyze_seo_scores']['bundle'] = [
    'title' => t('Bundle'),
    'help' => t('The entity bundle.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'string'],
  ];

  $data['analyze_seo_scores']['overall_score'] = [
    'title' => t('SEO Score'),
    'help' => t('The overall SEO score (0-100).'),
    'field' => ['id' => 'numeric'],
    'filter' => ['id' => 'numeric'],
    'sort' => ['id' => 'standard'],
    'argument' => ['id' => 'numeric'],
  ];

  $data['analyze_seo_scores']['content_hash'] = [
    'title' => t('Content Hash'),
    'help' => t('SHA-256 hash of the rendered content.'),
    'field' => ['id' => 'standard'],
    'filter' => ['id' => 'string'],
    'sort' => ['id' => 'standard'],
  ];

  $data['analyze_seo_scores']['last_checked'] = [
    'title' => t('Last Checked'),
    'help' => t('When the entity was last analysed.'),
    'field' => [
      'id' => 'date',
      'click sortable' => TRUE,
    ],
    'filter' => ['id' => 'date'],
    'sort' => ['id' => 'date'],
    'argument' => ['id' => 'date'],
  ];

  return $data;
}
