/**
 * Exporting a metalanguage as a package, on a real site: step 3.3.
 *
 * The unit suite builds a package, writes it to a zip and reads it back, and
 * that is the round-trip the plan asks for - but it does all of it with two
 * defined constants standing in for Joomla. Everything between the link and
 * the generator is untested up there: the route, the token, the controller
 * task, the ACL check, the headers, and whether a browser is handed a zip or a
 * page of HTML with a zip printed into it.
 *
 * That last one is not hypothetical. A Joomla controller that streams a file
 * and forgets `$app->close()` appends the whole administrator template to the
 * bytes it just sent, and the result is an archive that will not open - with
 * no error anywhere, because as far as the server is concerned the request
 * succeeded.
 *
 * The language is seeded by `tools/seed-metalanguage.php`.
 */

describe('exporting a metalanguage', () => {
  beforeEach(() => {
    cy.loginToAdmin();
  });

  /**
   * The link in the list is the one that is followed.
   *
   * Read off the page rather than typed here, which is this repository's habit
   * and the reason for it: a spec that visits a url it made up keeps passing
   * after the link beside it breaks, which is exactly what happened to the old
   * generate spec.
   */
  it('hands back a zip from the link in the list', () => {
    cy.visitMetagen('metalanguages');
    cy.shouldHaveRendered();

    cy.get('#adminForm a[href*="task=metalanguage.export"]').first().then(($link) => {
      // `prop`, not `attr`: the attribute is a site-root-relative path and
      // `cy.request` joins a path onto baseUrl, which on a Joomla in a
      // subdirectory produces /Meta-gen/joomla/Meta-gen/joomla/... The
      // property is the url the browser itself resolved.
      const href = $link.prop('href');

      expect(href, 'an export link exists').to.be.a('string');

      // `encoding: 'binary'` keeps the body as bytes; read as text the first
      // byte over 0x7f would be replaced and `PK` would still be there, so the
      // length is checked as well.
      cy.request({ url: href, encoding: 'binary', timeout: 60000 }).then((response) => {
        expect(response.status).to.eq(200);
        expect(response.headers['content-type']).to.contain('zip');
        expect(response.headers['content-disposition']).to.contain('.zip');

        // A zip's local file header, and nothing before it.
        expect(response.body.slice(0, 2)).to.eq('PK');
        expect(response.body.length).to.be.greaterThan(1000);

        // And nothing after it: a forgotten close() puts the administrator
        // template on the end, and a zip's central directory is at the end.
        expect(response.body).to.not.contain('</html>');
      });
    });
  });

  /**
   * The file it offers is named after the language and its version.
   *
   * Which is what identifies one from 3.4 on - a project records both - so two
   * exports of one language are two files rather than one overwriting the
   * other.
   */
  it('names the file after the language and its version', () => {
    cy.visitMetagen('metalanguages');

    cy.get('#adminForm a[href*="task=metalanguage.export"]').first().then(($link) => {
      cy.request({ url: $link.prop('href'), encoding: 'binary' }).then((response) => {
        expect(response.headers['content-disposition']).to.match(/filename="[A-Za-z0-9_-]+-[0-9.]+\.zip"/);
      });
    });
  });

  /**
   * Without a token it does nothing.
   *
   * A GET that runs a whole generation is worth somebody else's cpu on every
   * image tag pointing at it. Joomla answers an invalid token by redirecting
   * to the list, so what is checked is that no zip comes back.
   */
  it('refuses a request with no token', () => {
    cy.request({
      url: '/administrator/index.php?option=com_metagen&task=metalanguage.export&id=1',
      followRedirect: false,
      failOnStatusCode: false,
    }).then((response) => {
      expect(response.headers['content-type'] || '').to.not.contain('zip');
    });
  });
});
