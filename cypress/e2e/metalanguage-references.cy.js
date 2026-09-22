/**
 * The meta-model's reference dropdowns, on a real Joomla.
 *
 * Step 3.1 moved the LionCore M3 dropdowns off six field classes that each
 * loaded the stored metalanguage from the database, onto the `<metagen-reference>`
 * mechanism 1.9 built for projects. Everything about that is checkable without a
 * browser except the part that matters: whether a dropdown filled by a script,
 * from an index in the page plus what the form holds right now, actually comes
 * up with the right names in it.
 *
 * The metalanguage is seeded by `tools/seed-metalanguage.php` and is the same
 * fixture `MetalanguageReferenceTest` indexes, so what is asserted here is what
 * those tests describe.
 */

const options = (selector) => cy.get(selector).find('option').then(($o) => [...$o]
  .map((o) => ({ value: o.value, text: o.textContent.trim() }))
  .filter((o) => o.value !== ''));

describe('a metalanguage', () => {
  beforeEach(() => {
    // Straight to the edit task, which is exactly what the list's own link is.
    // Clicking through the list would be testing the list.
    cy.loginToAdmin();
    cy.visit('/administrator/index.php?option=com_metagen&task=metalanguage.edit&id=1');
  });

  it('opens for editing', () => {
    cy.shouldHaveRendered();
    cy.get('#jform_name').should('have.value', 'ER1');
  });

  /**
   * The six language entities the fixture holds, each with its key and kind.
   */
  it('shows the language entities it holds', () => {
    cy.get('.languageEntityName').should('have.length.greaterThan', 5);

    cy.get('#jform_languageEntities__languageEntities3__name').should('have.value', 'Entity');
    cy.get('#jform_languageEntities__languageEntities3__key').should('have.value', 'c-entity');
  });

  /**
   * The point of the step. A Concept's "extends" offers concepts - not the
   * concept interface, not the annotation, not the two datatypes - and the
   * list is built in the browser rather than rendered from a query.
   */
  it('offers concepts to a concept, and nothing else', () => {
    options('#jform_languageEntities__languageEntities4__classifier__concept__extends').then((opts) => {
      const texts = opts.map((o) => o.text);

      expect(texts, 'the concepts').to.include.members(['Entity', 'Field']);
      expect(texts, 'not the concept interface').to.not.include('INamed');
      expect(texts, 'not the annotation').to.not.include('Deprecated');
      expect(texts, 'not a datatype').to.not.include('String');
    });

    // And it holds what the model says it holds.
    cy.get('#jform_languageEntities__languageEntities4__classifier__concept__extends')
      .should('have.value', 'c-entity');
  });

  /**
   * A property's type offers datatypes, which is the complementary filter.
   */
  it('offers datatypes to a property', () => {
    options('#jform_languageEntities__languageEntities2__classifier__feature__feature0__property__type')
      .then((opts) => {
        const texts = opts.map((o) => o.text);

        expect(texts, 'the datatypes').to.include.members(['String', 'Boolean']);
        expect(texts, 'not a concept').to.not.include('Entity');
      });
  });

  /**
   * Annotations were not reachable at all before 3.1: `classifier.xml` offered
   * the choice and pointed at an `annotation.xml` that was never written, so
   * choosing it rendered an empty box.
   */
  it('has a form for an annotation, with the classifier it annotates', () => {
    cy.get('#jform_languageEntities__languageEntities5__classifier__annotation__annotates')
      .should('exist')
      .should('have.value', 'c-entity');

    options('#jform_languageEntities__languageEntities5__classifier__annotation__annotates').then((opts) => {
      const texts = opts.map((o) => o.text);

      // Any classifier: concepts, concept interfaces and annotations alike.
      expect(texts).to.include.members(['Entity', 'Field', 'INamed', 'Deprecated']);
      expect(texts, 'but not a datatype').to.not.include('String');
    });
  });

  /**
   * A name typed now shows up in the dropdowns now.
   *
   * This is what the six old field classes could not do at all: their options
   * came from the database, so a concept renamed on screen kept its old name
   * in every list until the form was saved and reopened.
   */
  it('follows a rename without saving', () => {
    // Re-queried between commands rather than chained. Chained, this failed
    // about two runs in three with "the page updated as a result of this
    // command": the subject was detached between clear() and type(). It was
    // worth ruling out something worse first - the screenshots showed the
    // dashboard, which would have meant clearing a field threw away an
    // unsaved model - and it is not that. It does not reproduce by hand, it
    // does not reproduce in four consecutive runs, and the run straight after
    // a reinstall took 19s for this one test against the usual three. A slow
    // admin page re-rendering the subform under the command is what fits, and
    // breaking the chain is what Cypress's own error says to do about it.
    const name = '#jform_languageEntities__languageEntities3__name';

    cy.get(name).clear();
    cy.get(name).type('Thing');
    cy.get(name).blur();

    options('#jform_languageEntities__languageEntities4__classifier__concept__extends').then((opts) => {
      const texts = opts.map((o) => o.text);

      expect(texts, 'the new name').to.include('Thing');
      expect(texts, 'not the old one').to.not.include('Entity');
    });
  });

  it('renders without a JavaScript error', () => {
    cy.get('body').should('not.contain', 'Fatal error');
    cy.get('.alert-danger, #system-message-container .alert-error').should('not.exist');
  });
});
