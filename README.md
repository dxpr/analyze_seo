> **Analyze SEO** scores content against traditional SEO best practices,
> deterministically. An [Analyze](https://www.drupal.org/project/analyze)
> ecosystem plugin by [DXPR](https://dxpr.com).
>
> [Getting Started](https://dxpr.com/c/marketing-cms) |
> [Pricing](https://dxpr.com/pricing) |
> [Try Free Demo](https://try.dxpr.com)

# SEO Analysis: Deterministic SEO Scoring for Drupal

Automated SEO analysis covering title tags, meta descriptions, heading
hierarchy, image alt text, link profiles, structured data, and more.

## Features

- **12 Deterministic Checks**: Title tag, meta description, URL structure,
  heading hierarchy, image optimisation, content length, link profile,
  canonical URL, Open Graph, Schema.org, plus optional AI checks
- **Visual Feedback**: Gauge displays with percentage scores and
  progressive-disclosure full reports grouped by severity
- **Educational Reports**: Each failing check explains why it matters and
  what to do, teaching SEO alongside scoring
- **Analyze Framework Integration**: Consistent UI and reporting across all
  analysis tools
- **Batch Processing**: Analyse large content volumes efficiently via GUI or
  Drush
- **Smart Caching**: Content-hash-based cache invalidation; results are only
  recomputed when content changes

## Requirements

- [Analyze](https://www.drupal.org/project/analyze) framework (>=1.3.0)
- [Views Color Scales](https://www.drupal.org/project/views_color_scales)
  (>=1.1.0)

Optional:
- [AI](https://www.drupal.org/project/ai) module with configured provider
  (enables keyword coherence and readability checks)

## Installation

```bash
composer require drupal/analyze_seo
drush en analyze_seo
```

## Configuration

### Basic Setup
1. Enable per content type at `/admin/config/content/analyze-settings`
2. Configure checks at `/admin/config/analyze/seo`
3. Configure permissions at
   `/admin/people/permissions#module-analyze_seo`

### Optional AI Checks
1. Configure an AI provider at `/admin/config/ai/providers`
2. Enable AI checks at `/admin/config/analyze/seo`
3. Two additional checks become available: keyword coherence and readability

### Check Reference
| Check | What it scores |
|---|---|
| Title Tag | Presence and length (30 to 60 characters) |
| Meta Description | Presence and length (120 to 160 characters) |
| URL Structure | Length, hyphens vs underscores, lowercase |
| Heading Hierarchy | Single H1, proper nesting, no skipped levels |
| Image Optimisation | Alt text coverage across all images |
| Content Length | Word count against configurable minimum |
| Link Profile | Internal and external links, anchor text |
| Canonical URL | Presence of canonical link tag |
| Open Graph | og:title, og:description, og:type, og:image |
| Schema.org | JSON-LD structured data with content types |
| Keyword Coherence | AI: topic alignment and keyword distribution |
| Readability | AI: sentence complexity and reading level |

## AI Coding Assistant Integration

The SEO module includes a built-in
[Agent Skills](https://agentskills.io) file (via the base
Analyze module) that teaches AI coding assistants how to run
SEO analysis through natural language. Run
`drush analyze:setup-ai` to enable, then ask naturally:

```
"Analyse SEO on all articles"
"Check heading hierarchy on the about page"
"Run SEO and brand voice analysis together on blog posts"
```

Batch processing is available via the centralised Analyze
batch system:

```bash
# Check analysis coverage
drush analyze:batch --status

# Run this analyzer on all enabled content types
drush analyze:batch \
  --analyzers=analyze_seo_checker

# Run on specific content types with limit
drush analyze:batch \
  --analyzers=analyze_seo_checker \
  --types=node:article --limit=50

# Force re-analysis of already analysed content
drush analyze:batch \
  --analyzers=analyze_seo_checker --force
```

Compatible with Claude Code, Codex CLI, Gemini CLI, GitHub
Copilot, Cursor, and other tools supporting the
[Agent Skills standard](https://agentskills.io/specification).

## Analysis

Results show SEO scores with visual gauge progression and
progressive-disclosure full reports. Cache invalidation is automatic;
content is only re-analysed when it changes.

### Display Options
- Gauge visualisation with clear progression
- Full report with severity-grouped checks (Needs attention / Can be
  improved / Passed)
- Views integration for custom reports
- Colour-coded results with Views Color Scales module

## Development

### Docker Commands
```bash
# Lint code
docker compose run --rm drupal-lint

# Check deprecations
docker compose run --rm drupal-check

# Auto-fix issues
docker compose run --rm drupal-lint-auto-fix
```

### Pre-commit Hook
Automatically runs linting checks before commits:
- Auto-fixes coding standard violations
- Blocks commits with remaining issues
- Provides coloured output for feedback

## Related Modules

Required dependencies; this module directly uses their APIs:

- [Analyze](https://www.drupal.org/project/analyze) - Content analysis
  framework; this module extends AnalyzePluginBase and implements
  BatchableAnalyzerInterface
- [Views Color Scales](https://www.drupal.org/project/views_color_scales) -
  Colour-coded score display in the bundled SEO Views report

Optional integration:

- [AI](https://www.drupal.org/project/ai) - LLM provider layer; enables
  keyword coherence and readability checks

Sibling Analyze plugins:

- [AI Optimization Analysis](https://www.drupal.org/project/analyze_aio) -
  AI answer engine readiness scoring
- [AI Brand Voice](https://www.drupal.org/project/analyze_ai_brand_voice) -
  Brand voice consistency scoring
- [AI Sentiments Analysis](https://www.drupal.org/project/analyze_ai_sentiments) -
  Multi-dimensional content tone analysis
- [Broken Links Analyzer](https://www.drupal.org/project/analyze_broken_links) -
  Link health monitoring per page
- [Search Console Analyzer](https://www.drupal.org/project/analyze_search_console) -
  Google Search performance per page
