/**
 * Shared setup for every spec.
 */

import './commands';

// Joomla's administrator template emits its share of console noise, and a
// failed request during teardown is not what these specs are about. What they
// are about is whether a page renders, so an uncaught exception from the page
// under test must still fail - it is exactly the blank-page symptom.
Cypress.on('uncaught:exception', (err) => {
  // Let it fail the test.
  return !/ResizeObserver loop/.test(err.message);
});
