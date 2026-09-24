/**
 * A saved metalanguage survives being opened and saved again.
 *
 * Every spec in this directory opens a screen and reads it. None of them had
 * ever pressed Save, and a model that is stored as one JSON blob is exactly the
 * shape where that matters: `save()` does `json_encode($data)` over what the
 * form posted, so anything the form failed to render is not merely missing from
 * the screen - it is gone from the record the moment somebody saves.
 *
 * Exten-gen had that defect and nothing caught it for months, because its specs
 * read screens too. There the form was missing its whole model half and a
 * project went from 18941 bytes to 63 on one click. Meta-gen builds its form
 * differently - the LionCore M3 forms are static XML, not merged in from a
 * package at runtime - so the cause cannot be the same one. That is a reason to
 * check rather than a reason not to: what is being guarded here is the
 * consequence, and the consequence does not care which cause produced it.
 *
 * Run against the seeded ER1, which `tools/seed-metalanguage.php` writes and
 * which `MetalanguageReferenceTest` indexes, so a failure here can be read
 * against a model those tests already describe.
 */

// The eight language entities the seeded ER1 carries.
const ENTITIES = ['String', 'Boolean', 'INamed', 'Entity', 'Field'];

const openER1 = () => {
  // Straight to the edit task, as the other specs do: clicking through the
  // list would be testing the list.
  cy.visit('/administrator/index.php?option=com_metagen&task=metalanguage.edit&id=1');
  cy.get('#metalanguage-form', { timeout: 20000 }).should('exist');
};

/**
 * The entity names the form is actually showing.
 *
 * Off the live inputs, never out of a `<template>`: a repeatable subform keeps
 * the markup for one row in one, and what Joomla clones says nothing about what
 * it bound. That distinction is the whole subject of this file.
 */
const entityNamesOnScreen = (doc) =>
  [...doc.querySelectorAll('#metalanguage-form [name^="jform[languageEntities]"][name$="[name]"]')]
    .map((input) => input.value)
    .filter((value) => value !== '');

describe('a saved metalanguage', () => {
  beforeEach(() => {
    cy.loginToAdmin();
    // Put ER1 back as the fixture has it, because the last test here saves it.
    // A spec about losing data has to own its data.
    cy.exec('php tools/seed-metalanguage.php');
  });

  it('opens with its language entities on the screen', () => {
    openER1();
    cy.shouldHaveRendered();

    cy.document().then((doc) => {
      const names = entityNamesOnScreen(doc);

      expect(names, 'the entities of the stored model').to.include.members(ENTITIES);
    });
  });

  /**
   * And keeps them through a save.
   *
   * The assertion that matters, and the one that is easy to write wrongly.
   * Waiting on a selector that exists on both pages passes against the document
   * that has not navigated yet - which is how the equivalent test in Exten-gen
   * went green while the save was destroying the record behind it. So the wait
   * is for a marker on `window` to disappear, and the model is then read back
   * from a fresh request rather than from the page that was just posted.
   */
  it('still has them after being opened and saved', () => {
    openER1();

    cy.window().then((win) => {
      win.__beforeSave = true;
      win.Joomla.submitbutton('metalanguage.apply');
    });

    cy.window({ timeout: 20000 }).should((win) => {
      expect(win.__beforeSave, 'the save has reloaded the page').to.be.undefined;
    });

    cy.get('body').should('not.contain', 'Fatal error');

    openER1();

    cy.document().then((doc) => {
      const names = entityNamesOnScreen(doc);

      expect(names, 'the model survived the round trip').to.include.members(ENTITIES);
    });
  });
});
