/**
 * Reading a metalanguage in from LionWeb, on a real site.
 *
 * The conversion is pinned twice already - in the library, and in Meta-gen's
 * unit suite against `ConceptModel` and the forms generator - and both of
 * those run with two constants standing in for Joomla. Everything between the
 * button and the converter is untested by them: the route, the token, the
 * controller task, whether the path field reaches the model, whether a row is
 * actually stored, and whether what comes back is a metalanguage the edit
 * screen can open.
 *
 * The chunk is written here rather than committed, because a fixture under the
 * site would have to be installed to be found, and the point of the path field
 * is that it reads a file somebody else's component left on the same site.
 * This writes one where JcbInOut would.
 */

/**
 * A language as LionWeb serialises one, small enough to read.
 *
 * Deliberately not JCB's: 1082 nodes proves the converter and tells you
 * nothing about the screen. What matters here is that a chunk goes in one end
 * and a metalanguage comes out the other.
 */
const chunk = () => {
  const m3 = { language: 'LionCore-M3', version: '2024.1' };
  const named = {
    property: {
      language: 'LionCore-builtins',
      version: '2024.1',
      key: 'LionCore-builtins-INamed-name',
    },
  };

  const node = (id, kind, props = {}, conts = {}, refs = {}) => ({
    id,
    classifier: { ...m3, key: kind },
    properties: Object.entries(props).map(([key, value]) =>
      key === 'name'
        ? { ...named, value }
        : { property: { ...m3, key }, value }),
    containments: Object.entries(conts).map(([key, children]) => ({
      containment: { ...m3, key },
      children,
    })),
    references: Object.entries(refs).map(([key, targets]) => ({
      reference: { ...m3, key },
      targets: targets.map((t) => ({ resolveInfo: null, reference: t })),
    })),
    annotations: [],
    parent: null,
  });

  return {
    serializationFormatVersion: '2024.1',
    languages: [{ key: 'LionCore-M3', version: '2024.1' }],
    nodes: [
      node('lang', 'Language',
        { name: 'Cypress', 'IKeyed-key': 'cypress', 'Language-version': '1.0' },
        { 'Language-entities': ['thing', 'part'] }),

      node('thing', 'Concept',
        { name: 'Widget', 'IKeyed-key': 'k-widget', 'Concept-partition': 'true' },
        { 'Classifier-features': ['f-title', 'f-parts'] }),

      node('f-title', 'Property',
        { name: 'title', 'IKeyed-key': 'k-title' },
        {},
        { 'Property-type': ['LionCore-builtins-String-2024-1'] }),

      node('f-parts', 'Containment',
        { name: 'parts', 'IKeyed-key': 'k-parts', 'Link-multiple': 'true' },
        {},
        { 'Link-type': ['part'] }),

      node('part', 'Concept',
        { name: 'Cog', 'IKeyed-key': 'k-cog' },
        { 'Classifier-features': ['f-label'] }),

      node('f-label', 'Property',
        { name: 'label', 'IKeyed-key': 'k-label' },
        {},
        { 'Property-type': ['LionCore-builtins-String-2024-1'] }),
    ],
  };
};

// Under the site, because that is the only place the field will read from.
const chunkPath = 'media/cypress-lionweb.json';

/**
 * Open the import panel.
 *
 * It is a `<details>`, closed unless JcbInOut has left a language on this
 * site - and on a site without JcbInOut, which this is, that means closed. So
 * the summary is clicked the way a person would, rather than the field being
 * reached around it: a spec that types into a hidden input would keep passing
 * if the panel stopped opening.
 */
const openImportPanel = () => {
  cy.contains('summary', 'Import LionWeb language').click();
  cy.get('#chunk').should('be.visible');
};

describe('importing a metalanguage from LionWeb', () => {
  beforeEach(() => {
    // Nothing left over from an earlier run. The import makes a new row each
    // time rather than updating one, so the site had reached twenty of them -
    // and a test that looks for its language in a list is satisfied by the
    // nineteen copies already there, before the import has done anything.
    cy.exec('php tools/forget-imported-test-languages.php');

    cy.writeFile(`joomla/${chunkPath}`, chunk());
    cy.visitMetagen('metalanguages');
  });

  it('offers the import, with the path already filled in when there is one', () => {
    cy.shouldHaveRendered();

    cy.get('#toolbar').contains('Import LionWeb language').should('be.visible');

    // The field suggests where JcbInOut leaves its language on this site. On a
    // site without JcbInOut it is a placeholder rather than a value, which is
    // the case here - so what must be true is that the field exists and says
    // what it wants.
    openImportPanel();
    cy.get('#chunk').should('exist');
    cy.get('#chunk').invoke('attr', 'placeholder').should('contain', 'jcbinout');
  });

  it('reads a chunk and stores it as a metalanguage', () => {
    openImportPanel();
    cy.get('#chunk').clear().type(chunkPath);
    cy.get('#toolbar').contains('Import LionWeb language').click();

    // It says what it read, and the count is the point: a message that only
    // said "imported" would not tell you whether six entities or none arrived.
    cy.get('#system-message-container', { timeout: 30000 })
      .invoke('text')
      .should('match', /Imported Cypress 1\.0, holding \d+ language entities/);

    // And it lands on the new language, open for editing - which is the thing
    // that proves a row was stored rather than a message enqueued.
    // Joomla rewrites the edit task into a view, which is the router doing
    // its job - what matters is that it landed on a language with an id.
    cy.url().should('match', /view=metalanguage&layout=edit&id=\d+/);
    cy.shouldHaveRendered();
  });

  /**
   * The converted language is a metalanguage like any other from here on.
   *
   * Asserted through the list rather than the database: what matters is that
   * the rest of the component can see it.
   */
  it('puts the imported language in the list, where it can be generated from', () => {
    openImportPanel();
    cy.get('#chunk').clear().type(chunkPath);
    cy.get('#toolbar').contains('Import LionWeb language').click();

    cy.url().should('match', /view=metalanguage&layout=edit/);

    cy.visitMetagen('metalanguages');

    cy.contains('a', 'Cypress 1.0').should('be.visible');
  });

  // -- when it cannot -------------------------------------------------------

  it('says so when the path is empty', () => {
    openImportPanel();
    cy.get('#chunk').clear();
    cy.get('#toolbar').contains('Import LionWeb language').click();

    cy.get('#system-message-container', { timeout: 30000 })
      .invoke('text')
      .should('contain', 'Give the path of a LionWeb chunk');
  });

  it('says so when there is no file there', () => {
    openImportPanel();
    cy.get('#chunk').clear().type('media/there-is-no-such-file.json');
    cy.get('#toolbar').contains('Import LionWeb language').click();

    cy.get('#system-message-container', { timeout: 30000 })
      .invoke('text')
      .should('contain', 'There is no file at');
  });

  /**
   * A chunk holding a model rather than a language is a reasonable mistake to
   * make - they are the same kind of file - so it gets an answer rather than a
   * stack trace.
   */
  it('says so when the chunk holds a model rather than a language', () => {
    cy.writeFile('joomla/media/cypress-not-a-language.json', {
      serializationFormatVersion: '2024.1',
      languages: [{ key: 'cypress', version: '1.0' }],
      nodes: [{
        id: 'n1',
        classifier: { language: 'cypress', version: '1.0', key: 'k-widget' },
        properties: [],
        containments: [],
        references: [],
        annotations: [],
        parent: null,
      }],
    });

    openImportPanel();
    cy.get('#chunk').clear().type('media/cypress-not-a-language.json');
    cy.get('#toolbar').contains('Import LionWeb language').click();

    cy.get('#system-message-container', { timeout: 30000 })
      .invoke('text')
      .should('contain', 'no LionCore Language node');
  });

  /**
   * The field reads a file under the site and nothing else. An administrator
   * can reach the filesystem by other means, so this is not a wall - but a
   * field that reads any path the web server can is a worse habit than one
   * that does not.
   */
  it('refuses a path outside the site', () => {
    openImportPanel();
    cy.get('#chunk').clear().type('../../../etc/hosts');
    cy.get('#toolbar').contains('Import LionWeb language').click();

    cy.get('#system-message-container', { timeout: 30000 })
      .invoke('text')
      .should('match', /has to be a file under this site|There is no file at/);
  });
});
