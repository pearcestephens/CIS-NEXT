# Closed-Loop UI Fixer - Setup and Usage Guide

## Overview

The **Closed-Loop UI Fixer** is a comprehensive automated testing and fixing system that:

1. **Crawls & Interacts** - Uses Playwright to visit pages, test interactions, capture console errors
2. **Detects Problems** - Runs accessibility (axe-core), performance (Lighthouse), and link checks
3. **Triages & Classifies** - Labels issues by type, severity, and risk level
4. **Proposes/Auto-fixes** - Generates fixes via GPT Actions API, applies low-risk fixes automatically
5. **Re-tests** - Validates fixes work before committing changes

## Architecture

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Playwright    │    │   Lighthouse CI  │    │   Linkinator    │
│  (Interactions) │    │  (Performance)   │    │  (Broken Links) │
└─────────┬───────┘    └────────┬─────────┘    └─────────┬───────┘
          │                     │                        │
          └─────────────────────┼────────────────────────┘
                                │
                    ┌───────────▼───────────┐
                    │   Autopatcher Bot     │
                    │  (GPT Actions API)    │
                    └───────────┬───────────┘
                                │
                ┌───────────────┼───────────────┐
                │               │               │
        ┌───────▼───────┐   ┌───▼───┐   ┌─────▼─────┐
        │   Auto-Apply  │   │  PR   │   │   Skip    │
        │  (Low Risk)   │   │(High) │   │ (Manual)  │
        └───────────────┘   └───────┘   └───────────┘
```

## Quick Start

### 1. Dependencies Installed ✅
```bash
# Already completed:
npm i -D @playwright/test @axe-core/playwright linkinator @lhci/cli typescript
npx playwright install --with-deps
```

### 2. Run the Complete Pipeline
```bash
# Set your base URL
export BASE_URL="https://cis.dev.ecigdis.co.nz"

# Run the full closed-loop system
npm run ui-fixer
```

### 3. Run Individual Components
```bash
# Just Playwright tests
npm test

# Just Lighthouse audit
npm run lighthouse

# Just link checking
npm run check-links

# Just autopatcher (process existing reports)
npm run autopatcher
```

## Configuration Files

### `playwright.config.ts`
- Multi-browser testing (Chrome, Firefox, Safari, Mobile)
- Trace capture on failure
- Screenshots and videos
- Health endpoint integration

### `lighthouserc.json`
- Performance budgets (70% min)
- Accessibility requirements (90% min)
- Core Web Vitals thresholds
- SEO and best practices

### `tools/ui-bot/check-links.sh`
- Recursive link crawling
- JSON reporting
- Broken link classification

### `tools/ui-bot/autopatcher.php`
- GPT Actions API integration
- Risk assessment (low/medium/high)
- Automatic fix application
- Pull request creation

## What Gets Fixed Automatically

### ✅ Auto-Applied (Low Risk)
- Broken internal links
- Missing alt text on images
- Missing aria-labels
- Duplicate IDs
- Simple CSS issues
- Obvious typos

### 🔄 Pull Request (High Risk)
- JavaScript errors
- PHP controller changes
- Database queries
- Complex CSS/layout changes
- Performance optimizations requiring code changes

### ⚠️ Manual Review Required
- Security-related changes
- Breaking API changes
- Complex business logic
- Multi-file refactors

## Reports & Artifacts

All reports are generated in `./reports/`:

```
reports/
├── test-results.json          # Playwright test results
├── test-artifacts/            # Screenshots, videos, traces
├── lighthouse/               # Performance audit reports
├── links.json               # Link check raw results
├── link-issues.json         # Broken link issues
├── link-summary.json        # Link check summary
└── ui-fixer-summary.json    # Consolidated pipeline report
```

## Risk Guardrails

### Automatic Safety Checks
- **Backup before apply** - All files backed up before changes
- **Test verification** - Fixes verified by re-running tests
- **Rollback on failure** - Automatic restoration if fix fails
- **Low-risk only** - Only safe changes applied automatically

### Approval Workflows
- **High-risk → PR** - Complex changes require human review
- **Budget failures** - Performance/accessibility regressions freeze auto-fixes
- **New console errors** - Any new JavaScript errors halt automation

## Integration with Your Workflow

### Manual Usage
```bash
# Run before deployment
npm run ui-fixer

# Check specific issues
npm test -- --grep "accessibility"
npm run lighthouse
```

### CI/CD Integration
Add to your pipeline:

```yaml
# .github/workflows/ui-quality.yml
- name: UI Quality Check
  run: |
    export BASE_URL="${{ env.STAGING_URL }}"
    npm run ui-fixer
  continue-on-error: true  # Don't block deployment on UI issues
```

### Scheduled Runs
```bash
# Add to crontab for nightly runs
0 2 * * * cd /var/www/cis.dev.ecigdis.co.nz/public_html && npm run ui-fixer
```

## GPT Actions API Integration

The autopatcher connects to your existing GPT Actions API at:
`https://staff.vapeshed.co.nz/gpt_actions.php`

### Required Actions
- `generate_fix` - Generate code fixes for issues
- `create_pull_request` - Create PRs for high-risk fixes
- `read_file` - Get file context for fixes
- `write_file` - Apply file changes (with backups)

### Environment Variables
```bash
export GPT_ACTIONS_URL="https://staff.vapeshed.co.nz/gpt_actions.php"
export BASE_URL="https://cis.dev.ecigdis.co.nz"
```

## Monitoring & Alerts

### Success Metrics
- **Fix Success Rate** - % of auto-applied fixes that pass verification
- **Issue Detection Rate** - New issues caught per run
- **False Positive Rate** - Issues flagged incorrectly

### Alert Conditions
- **Budget Violations** - Performance/accessibility scores below thresholds
- **High Error Count** - More than 10 console errors detected
- **Broken Links** - Any 404s or 500s on critical paths

## Customization

### Add New Test Paths
Edit `tests/smoke.spec.ts`:
```typescript
const CRITICAL_PATHS = [
  { path: '/', name: 'Home Page' },
  { path: '/your-new-page', name: 'Your New Page' },
  // ...
];
```

### Adjust Performance Budgets
Edit `lighthouserc.json`:
```json
{
  "ci": {
    "assert": {
      "assertions": {
        "categories:performance": ["error", {"minScore": 0.8}]
      }
    }
  }
}
```

### Customize Fix Types
Edit `tools/ui-bot/autopatcher.php`:
```php
private function isLowRisk(array $issue): bool {
    $lowRiskTypes = [
        'your_custom_issue_type',
        // ...
    ];
    // ...
}
```

## Troubleshooting

### Common Issues

**Playwright tests fail to connect**
```bash
# Check base URL is accessible
curl -I $BASE_URL

# Check Playwright browser installation
npx playwright install --with-deps
```

**Lighthouse fails with timeout**
```bash
# Increase timeout in lighthouserc.json
"collect": {
  "settings": {
    "maxWaitForLoad": 45000
  }
}
```

**Autopatcher doesn't connect to GPT Actions**
```bash
# Test GPT Actions API directly
curl -X POST https://staff.vapeshed.co.nz/gpt_actions.php \
  -H "Content-Type: application/json" \
  -d '{"action": "health_check"}'
```

## Next Steps

1. **Run first pipeline**: `npm run ui-fixer`
2. **Review reports** in `./reports/`
3. **Configure alerts** for your monitoring system
4. **Add to CI/CD** pipeline
5. **Customize** for your specific needs

The system is now **ready for production use**! 🚀
