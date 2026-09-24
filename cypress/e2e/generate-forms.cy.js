/**
 * Generating the forms of a modelled language, on a real site.
 *
 * Step 3.2 built the generator; this is the only thing that says the button
 * reaches it. Nothing in the unit suite can: it calls the generator directly,
 * with two defined constants standing in for Joomla, so the controller, the
 * view, the model, the repository and the filesystem are all untested above.
 *
 * That gap was the whole problem here. Before 3.2 there were *three* names for
 * this one screen and no two of them agreed - the link said `view=generateform`,
 * the directory was `GenerateMetalanguage`, and the class inside it declared
 * `View\GenerateForm` - so the button led nowhere, and underneath it a
 * generator that opened `foreach ($metalanguage->datamodel as $entity)` would
 * have thrown on its first statement if anything had ever reached it. A screen
 * nobody can open is a screen nobody finds out is broken.
 *
 * The metalanguage is seeded by `tools/seed-metalanguage.php`, which is the same
 * ER1 fixture the unit tests read.
 */

describe('generating the forms of a language', () => {
  beforeEach(() => {
    cy.loginToAdmin();
  });

  /**
   * The link in the list is the one the modal loads, and it resolves.
   *
   * Taken from the list rather than typed here on purpose: a spec that visits
   * a url it made up would keep passing after the link beside it broke, which
   * is exactly what happened to the old one.
   */
  it('is reachable from the project forms list', () => {
    cy.visitMetagen('metalanguages');
    cy.shouldHaveRendered();

    cy.get('#adminForm a[data-href*="view=generateForms"]').first().then(($link) => {
      const href = $link.attr('data-href');

      expect(href, 'a generate link exists').to.be.a('string');

      cy.visit(href);
    });

    cy.get('body', { timeout: 60000 }).should('contain.text', 'files');
  });

  /**
   * And pressing the button actually points the modal at that language.
   *
   * The test above reads `data-href` and visits it, which is the right way to
   * check the URL and no check at all on the thing between the click and the
   * dialog. There is one modal for the whole list, so the click has to say
   * which language it means before Bootstrap opens it - and nothing had ever
   * exercised that. It was twelve lines of inline script in the template.
   *
   * Now it is `media/com_metagen/js/generation-modal.js`, whose rules
   * `composer test-js` checks over plain objects. What is left for a browser is
   * exactly this: that the listener is attached, to the right elements, and
   * that the iframe it finds is the one in the dialog.
   */
  it('points the modal at the language whose button was pressed', () => {
    cy.visitMetagen('metalanguages');
    cy.shouldHaveRendered();

    cy.get('#adminForm a.dynbutton[data-href*="view=generateForms"]').first().then(($button) => {
      const expected = $button.attr('data-href');

      cy.wrap($button).click();

      cy.get('#generationModal iframe').should('have.attr', 'src', expected);
    });
  });

  it('generates the forms and reports what it wrote', () => {
    cy.visit('/administrator/index.php?option=com_metagen&view=generateForms'
      + '&tmpl=component&metalanguage_id=1');

    cy.get('body', { timeout: 60000 }).should('contain.text', 'FORMS FOR ER1');

    // One form per classifier the language holds, and the table beside them.
    cy.get('body').should('contain.text', 'entity.xml');
    cy.get('body').should('contain.text', 'field.xml');
    cy.get('body').should('contain.text', 'references.json');

    // And it says where the output went.
    cy.get('body').should('contain.text', '.zip');
  });

  /**
   * ER1's sketch has no partition, so nothing can hold a classifier yet. The
   * generator has to say that rather than produce a reference table whose
   * every dropdown would come up empty.
   */
  it('says what the language does not reach yet', () => {
    cy.visit('/administrator/index.php?option=com_metagen&view=generateForms'
      + '&tmpl=component&metalanguage_id=1');

    cy.get('body', { timeout: 60000 }).should('contain.text', 'nothing contains');
  });

  it('runs without a fatal error or a warning', () => {
    cy.visit('/administrator/index.php?option=com_metagen&view=generateForms'
      + '&tmpl=component&metalanguage_id=1');

    cy.get('body', { timeout: 60000 }).should('contain.text', 'files');
    cy.get('body').should('not.contain', 'Fatal error');
    cy.get('body').should('not.contain', 'Warning:');
    cy.get('body').should('not.contain', 'Deprecated:');
  });

  /**
   * Generating forms must not touch the component's own forms directory.
   *
   * The old generator wrote straight into
   * `administrator/components/com_metagen/forms/Metalanguages/`, with a
   * `mkdir` and a `save()` per file as it went - so pressing generate edited
   * the running component from inside itself, and a run that failed half way
   * left a language half replaced. The metalanguage list still opening
   * afterwards is what says that is no longer happening.
   */
  it('leaves the component it runs inside alone', () => {
    cy.visit('/administrator/index.php?option=com_metagen&view=generateForms'
      + '&tmpl=component&metalanguage_id=1');
    cy.get('body', { timeout: 60000 }).should('contain.text', 'files');

    cy.visitMetagen('metalanguages');
    cy.shouldHaveRendered();
    cy.get('#adminForm').should('exist');

    // And the meta-model forms still work, which is what the editing screen
    // proves by having its dropdowns filled.
    cy.visit('/administrator/index.php?option=com_metagen&task=metalanguage.edit&id=1');
    cy.shouldHaveRendered();
    cy.get('#jform_name').should('have.value', 'ER1');
  });
});
