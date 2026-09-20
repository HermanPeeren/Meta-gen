import { defineConfig } from 'cypress';

/**
 * Cypress runs against a real Joomla with Meta-gen installed.
 *
 * There is no way around that. tools/smoke.php goes a long way without a
 * browser - it boots the component through its own provider and builds every
 * form - and it still cannot tell you whether a screen renders. Exten-gen's
 * views passed every check it had and returned 200 with an empty body.
 *
 * It also needs a target installed, or the list is correctly empty and there
 * is nothing to look at. Exten-gen is the one this is developed against.
 *
 * The site and the login come from `cypress.env.json`, which is git-ignored.
 * Copy `cypress.env.json.dist` and fill it in.
 */
export default defineConfig({
  e2e: {
    // Overridden by `baseUrl` in cypress.env.json.
    baseUrl: 'http://localhost/Meta-gen/joomla',
    supportFile: 'cypress/support/e2e.js',
    specPattern: 'cypress/e2e/**/*.cy.js',
    video: false,
    screenshotOnRunFailure: true,

    // Joomla's admin is one origin and one session; nothing here talks to a
    // third party, so the browser need not police it.
    chromeWebSecurity: false,

    setupNodeEvents(on, config) {
      on('task', {
        log(message) {
          // eslint-disable-next-line no-console
          console.log(message);

          return null;
        },
      });

      if (config.env.baseUrl) {
        config.baseUrl = config.env.baseUrl;
      }

      if (!config.env.adminUser || !config.env.adminPassword) {
        throw new Error(
          'cypress.env.json needs adminUser and adminPassword. Copy cypress.env.json.dist and fill it in.',
        );
      }

      return config;
    },
  },
});
