/**
 * Speed Matrix - Remove Unused CSS
 *
 * WARNING: This is an experimental feature and may break styling.
 * Use with caution and test thoroughly!
 */
document.addEventListener('DOMContentLoaded', function () {
  // Only run if enabled
  if (
    typeof speedMatrixRemoveUnusedCSS === 'undefined' ||
    !speedMatrixRemoveUnusedCSS
  ) {
    return;
  }

  const links = document.querySelectorAll(
    'link[data-css-handle][rel="stylesheet"]'
  );
  const usageThreshold = 0.1; // Keep CSS if at least 10% of selectors are used

  /**
   * Check if a selector is used in the document
   */
  function isSelectorUsed(selector) {
    // Skip pseudo-selectors, media queries, and complex selectors
    if (
      selector.includes(':') ||
      selector.includes('@') ||
      selector.includes('[') ||
      selector.includes('>') ||
      selector.includes('+') ||
      selector.includes('~')
    ) {
      return true; // Keep complex selectors to be safe
    }

    try {
      return document.querySelector(selector) !== null;
    } catch (e) {
      // Invalid selector, keep it to be safe
      return true;
    }
  }

  /**
   * Extract selectors from CSS text
   */
  function extractSelectors(cssText) {
    const selectors = new Set();

    // Remove comments
    cssText = cssText.replace(/\/\*[\s\S]*?\*\//g, '');

    // Extract selectors (simple regex - not perfect but safer)
    const matches = cssText.match(/([^{}]+)\s*\{[^}]*\}/g) || [];

    matches.forEach(match => {
      const selectorPart = match.split('{')[0].trim();

      // Split by comma for multiple selectors
      selectorPart.split(',').forEach(sel => {
        sel = sel.trim();
        if (sel && sel.length > 0) {
          selectors.add(sel);
        }
      });
    });

    return Array.from(selectors);
  }

  /**
   * Analyze CSS usage
   */
  function analyzeCSSUsage(link) {
    const cssHref = link.getAttribute('href');

    // Skip certain critical CSS files
    const criticalPatterns = [
      'admin-bar',
      'dashicons',
      'style.css', // Theme main stylesheet
      'critical',
      'inline'
    ];

    const shouldSkip = criticalPatterns.some(
      pattern =>
        cssHref.includes(pattern) || link.dataset.cssHandle.includes(pattern)
    );

    if (shouldSkip) {
      console.log(
        '[Speed Matrix] Skipping critical CSS:',
        link.dataset.cssHandle
      );
      return;
    }

    fetch(cssHref)
      .then(response => {
        if (!response.ok) throw new Error('Failed to fetch');
        return response.text();
      })
      .then(cssText => {
        const selectors = extractSelectors(cssText);
        let usedCount = 0;
        let totalCount = selectors.length;

        if (totalCount === 0) {
          console.log(
            '[Speed Matrix] No selectors found in:',
            link.dataset.cssHandle
          );
          return;
        }

        // Check usage
        selectors.forEach(selector => {
          if (isSelectorUsed(selector)) {
            usedCount++;
          }
        });

        const usageRate = usedCount / totalCount;

        console.log('[Speed Matrix] CSS Analysis:', {
          handle: link.dataset.cssHandle,
          total: totalCount,
          used: usedCount,
          rate: (usageRate * 100).toFixed(2) + '%'
        });

        // Only remove if usage is below threshold
        if (usageRate < usageThreshold) {
          console.warn(
            '[Speed Matrix] Removing unused CSS:',
            link.dataset.cssHandle,
            'Usage:',
            (usageRate * 100).toFixed(2) + '%'
          );
          link.remove();
        }
      })
      .catch(err => {
        console.error(
          '[Speed Matrix] Error analyzing CSS:',
          link.dataset.cssHandle,
          err
        );
      });
  }

  // Analyze each stylesheet
  links.forEach(link => {
    // Delay analysis to avoid blocking page load
    setTimeout(() => analyzeCSSUsage(link), 1000);
  });
});
