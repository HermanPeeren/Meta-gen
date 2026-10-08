/**
 * Every string on screen is translated: step 5.1.
 *
 * `LanguageStringsTest` reads the source. This reads what is rendered, which is
 * the only place a constant built at run time shows - and the menu, which reads
 * the `.sys.ini` alone.
 */

describe('language strings', () => {
  it('shows the metalanguages page with every string translated', () => {
    cy.visitMetagen('metalanguages');
    cy.shouldHaveRendered();
    cy.shouldShowNoRawConstants();
  });

  it('shows a metalanguage with every string translated', () => {
    cy.loginToAdmin();
    cy.visit('/administrator/index.php?option=com_metagen&task=metalanguage.edit&id=1');
    cy.shouldHaveRendered();
    cy.shouldShowNoRawConstants();
  });

  it('shows the menu entries translated', () => {
    cy.visitMetagen('metalanguages');
    cy.get('#sidebarmenu a[href*="option=com_metagen"]').each(($link) => {
      expect($link.text().trim(), 'a menu entry').to.not.match(/^COM_METAGEN_/);
    });
  });
});
