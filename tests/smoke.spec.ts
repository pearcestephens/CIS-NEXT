import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Closed-Loop UI Fixer - Comprehensive Smoke Test Suite
 * Crawls pages, captures errors, tests accessibility, validates interactions
 */

const BASE_URL = process.env.BASE_URL || 'https://cis.dev.ecigdis.co.nz';

// Critical paths to test
const CRITICAL_PATHS = [
  { path: '/', name: 'Home Page' },
  { path: '/login', name: 'Login Page' },
  { path: '/health', name: 'Health Check' },
  { path: '/ready', name: 'Readiness Check' },
];

// Additional paths for full crawl
const EXTENDED_PATHS = [
  { path: '/stock-transfer', name: 'Stock Transfer' },
  { path: '/sophisticated_transfers', name: 'Sophisticated Transfers' },
  { path: '/admin', name: 'Admin Dashboard' },
];

test.describe('UI Smoke Tests - Critical Paths', () => {
  for (const { path, name } of CRITICAL_PATHS) {
    test(`${name} - Console errors, accessibility, interactions`, async ({ page }) => {
      const errors: string[] = [];
      const warnings: string[] = [];
      const networkErrors: string[] = [];

      // Set up error capture
      page.on('console', msg => {
        if (msg.type() === 'error') {
          errors.push(`Console Error: ${msg.text()}`);
        } else if (msg.type() === 'warning') {
          warnings.push(`Console Warning: ${msg.text()}`);
        }
      });

      page.on('pageerror', error => {
        errors.push(`Page Error: ${error.message}`);
      });

      page.on('response', response => {
        if (response.status() >= 400) {
          networkErrors.push(`Network Error: ${response.status()} ${response.url()}`);
        }
      });

      // Navigate to page
      try {
        await page.goto(BASE_URL + path, { 
          waitUntil: 'networkidle',
          timeout: 30000 
        });
      } catch (error) {
        throw new Error(`Failed to load ${path}: ${error}`);
      }

      // Wait for page to stabilize
      await page.waitForTimeout(2000);

      // Run accessibility scan with axe-core
      try {
        const accessibilityScanResults = await new AxeBuilder({ page })
          .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
          .analyze();
        
        if (accessibilityScanResults.violations.length > 0) {
          console.warn(`Accessibility violations found on ${path}:`, accessibilityScanResults.violations);
          // Store violations for reporting but don't fail test
        }
      } catch (axeError) {
        console.warn(`Accessibility scan failed on ${path}:`, axeError);
      }

      // Test interactive elements
      const clickableElements = page.locator('a, button, [role="button"], input[type="submit"]');
      const clickableCount = await clickableElements.count();

      for (let i = 0; i < Math.min(clickableCount, 20); i++) {
        const element = clickableElements.nth(i);
        
        try {
          await element.scrollIntoViewIfNeeded();
          
          // Get element info
          const tagName = await element.evaluate(el => el.tagName);
          const href = await element.getAttribute('href');
          const text = await element.textContent();
          
          // Skip external links
          if (href && /^https?:\/\//.test(href) && !href.startsWith(BASE_URL)) {
            continue;
          }
          
          // Test if element is clickable (trial click)
          try {
            await element.click({ trial: true, timeout: 5000 });
            console.log(`✓ Clickable: ${tagName} "${text?.slice(0, 50)}"`);
          } catch (clickError) {
            warnings.push(`Unclickable element: ${tagName} "${text?.slice(0, 50)}" - ${clickError}`);
          }
          
        } catch (elementError) {
          warnings.push(`Element test failed: ${elementError}`);
        }
      }

      // Test form elements if present
      const forms = page.locator('form');
      const formCount = await forms.count();
      
      for (let i = 0; i < formCount; i++) {
        const form = forms.nth(i);
        const inputs = form.locator('input, select, textarea');
        const inputCount = await inputs.count();
        
        console.log(`Form ${i + 1}: ${inputCount} input elements`);
        
        // Test form validation if submit button exists
        const submitButton = form.locator('input[type="submit"], button[type="submit"]');
        if (await submitButton.count() > 0) {
          try {
            // Try submitting empty form to test validation
            await submitButton.first().click({ trial: true });
            console.log(`✓ Form ${i + 1}: Submit button accessible`);
          } catch (submitError) {
            warnings.push(`Form ${i + 1}: Submit button issue - ${submitError}`);
          }
        }
      }

      // Performance checks
      const performanceEntries = await page.evaluate(() => {
        return JSON.stringify(performance.getEntriesByType('navigation'));
      });
      
      const navigation = JSON.parse(performanceEntries)[0];
      if (navigation) {
        const loadTime = navigation.loadEventEnd - navigation.fetchStart;
        console.log(`Page load time: ${loadTime}ms`);
        
        if (loadTime > 5000) {
          warnings.push(`Slow page load: ${loadTime}ms (threshold: 5000ms)`);
        }
      }

      // Store results for reporting
      await page.evaluate((results) => {
        (window as any).testResults = results;
      }, {
        path,
        errors: errors.length,
        warnings: warnings.length,
        networkErrors: networkErrors.length,
        clickableElements: clickableCount,
        forms: formCount,
        timestamp: new Date().toISOString()
      });

      // Assert critical failures only
      if (errors.length > 0) {
        console.error(`Errors found on ${path}:`, errors);
        expect(errors, `Critical errors on ${path}:\\n${errors.join('\\n')}`).toHaveLength(0);
      }

      // Log warnings but don't fail
      if (warnings.length > 0) {
        console.warn(`Warnings on ${path}:`, warnings);
      }

      if (networkErrors.length > 0) {
        console.warn(`Network issues on ${path}:`, networkErrors);
      }
    });
  }
});

test.describe('UI Extended Tests - Full Site Crawl', () => {
  test('Extended paths crawl and validation', async ({ page }) => {
    const allErrors: Array<{path: string, errors: string[]}> = [];
    
    for (const { path, name } of EXTENDED_PATHS) {
      const pathErrors: string[] = [];
      
      page.on('console', msg => {
        if (msg.type() === 'error') {
          pathErrors.push(`Console Error: ${msg.text()}`);
        }
      });

      page.on('pageerror', error => {
        pathErrors.push(`Page Error: ${error.message}`);
      });

      try {
        const response = await page.goto(BASE_URL + path, { 
          waitUntil: 'networkidle',
          timeout: 20000 
        });
        
        if (!response || response.status() >= 400) {
          pathErrors.push(`HTTP Error: ${response?.status()} on ${path}`);
        }
        
        // Quick accessibility check
        try {
          const axeResults = await new AxeBuilder({ page }).analyze();
          if (axeResults.violations.length > 0) {
            console.warn(`A11y violations on ${path}:`, axeResults.violations.length);
          }
        } catch (axeError) {
          console.warn(`A11y scan failed on ${path}: ${axeError}`);
        }
        
      } catch (error) {
        pathErrors.push(`Navigation failed: ${error}`);
      }
      
      if (pathErrors.length > 0) {
        allErrors.push({ path, errors: pathErrors });
      }
      
      // Clear listeners for next iteration
      page.removeAllListeners('console');
      page.removeAllListeners('pageerror');
      
      // Small delay between pages
      await page.waitForTimeout(1000);
    }
    
    // Report all findings
    if (allErrors.length > 0) {
      console.warn('Extended crawl findings:', allErrors);
      // Don't fail extended tests, just log findings
    }
    
    console.log(`✓ Extended crawl completed: ${EXTENDED_PATHS.length} pages tested`);
  });
});

test.describe('Health Check Integration', () => {
  test('Health endpoints respond correctly', async ({ page }) => {
    // Test /health endpoint
    const healthResponse = await page.goto(BASE_URL + '/health');
    expect(healthResponse?.status()).toBe(200);
    
    // Test /ready endpoint  
    const readyResponse = await page.goto(BASE_URL + '/ready');
    expect(readyResponse?.status()).toBe(200);
    
    console.log('✓ Health endpoints operational');
  });
});
