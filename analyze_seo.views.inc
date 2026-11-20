<?php

/**
 * @file
 * Views hooks for Analyze SEO.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\analyze_seo\Hook\AnalyzeSeoViewsHooks;

/**
 * @file
 * Provide views data for analyze_seo module.
 */

/**
 * Implements hook_views_data().
 */
#[LegacyHook]
function analyze_seo_views_data() {
  return \Drupal::service(AnalyzeSeoViewsHooks::class)->viewsData();
}
