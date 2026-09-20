# Developing Meta-gen

What Meta-gen is *for* is in the [README](../README.md); where the family is
going, step by step, is in Exten-gen's
[rework-plan.md](https://github.com/HermanPeeren/Exten-gen/blob/main/docs/rework-plan.md).

## Where this came from

Meta-gen was split out of Exten-gen. Everything about modelling a language
lived there: the LionCore M3 forms, the CRUD around a stored language, the
reference index for it, and the forms generator built at step 3.2. None of it
was coupled to Exten-gen's own side — the ER1 tree referenced it exactly once,
in a stale comment — so the split was a move rather than an untangling.

## Layout

`src/` is the root of the installable package, so every file sits at the path
it will occupy on a Joomla site.

```
src/
  metagen.xml                            the manifest the installer reads
  script.php                             install script: PHP, Joomla and library checks
  administrator/components/com_metagen/
    forms/LIonCore_M3/                    the meta-model, as Joomla form XML
    forms/filter_metalanguages.xml
    src/Generator/Model/                  a metalanguage, as a type
    src/Generator/Meta/                   the forms generator
    src/Generator/Target/                 what runs
    src/Reference/LionCoreM3.php          what M3 offers a reference dropdown
    src/{Controller,Model,Table,View}/    the CRUD around a stored metalanguage
    tmpl/ language/ services/ sql/
tests/Unit/                              the suite
tests/Fixtures/languages/                er1.json, lioncore-m3.json
build/build.php                          assembles the installable zip
```

## The entity is called a Metalanguage, and that is not arbitrary

It was called a `ModelLanguage` for about an hour, and Joomla would not have it.

`BaseModel::getName()` matches `/Model(.*)/i` against the **fully qualified**
class name — so it matches the `Model` namespace segment first — and then
strips every lowercase `model` from what it captured. `...\Model\ModelLanguageModel`
came out as `language`, so Joomla looked for a `language` table, did not find
one, and the edit screen died with *"Table language not supported. File not
found."* `ListModel::getFilterForm()` has its own version of the same
assumption — `explode('Model', static::class)`, take the second piece — which
gave a lone backslash, so the list view asked for `filter_.xml` and died inside
Joomla's own searchtools layout on a null `filterForm`.

Neither failure names the class that caused it. The rule: **an entity name must
not contain "model", in any case.** `JoomlaNamingTest` is that rule written
down, over every model, table and controller in the component.

## Two more naming rules, same family

Both were found the same way — by something that worked here and would not have
worked on a Linux server.

- **A view class directory is `ucfirst` of the view name, exactly.** Joomla
  applies `ucfirst` and nothing else, so `view=metalanguages` needs
  `View/Metalanguages/`. `view=metalanguages` with a directory called
  `MetaLanguages` resolves on a case-insensitive filesystem and 404s on a
  case-sensitive one.
- **A tmpl directory is `strtolower` of the view name, exactly.**
  `AbstractView::getName()` lowercases the last namespace segment, so
  `View/Metalanguages/` needs `tmpl/metalanguages/` — all lowercase, even
  though the class directory is not.

`ResolvableNamesTest` covers the other half of PSR-4: a file declares the
namespace its path implies, *and* a class named after the file. Both halves are
one comparison each, and between them they are what PSR-4 actually says. The
second was added after a rename moved `GenerateProjectForm/HtmlView.php` to
`GenerateForms/` and left the model class inside called something else — a 500
that named neither name.

## How a reference field works

The mechanism is the shared library's, not this component's:

```
Yepr\Gen\Core\Reference\ReferenceIndex     what a stored model offers, read from a table
Yepr\Gen\Core\Reference\ReferenceMarkup    the markup, which is the contract with the script
Yepr\Gen\Joomla\Form\Field\ReferenceField  the Joomla adapter
media/lib_yepr_gen/js/reference.js         <yepr-reference>
src/Reference/LionCoreM3.php               ← the only part that is Meta-gen's
```

Three components edit models with reference dropdowns, and what differs between
them is a **table**: where each object type lives in the stored model, and how
the browser finds its rows. So the table is data the component owns and
everything else is shared.

**Loading the script has one trap in it.** A library's asset file is not
registered the way the active component's is, so the layout asks for it by
name:

```php
$wa->getRegistry()->addExtensionRegistryFile('lib_yepr_gen');
$wa->useScript('lib_yepr_gen.reference');
```

And the `uri` inside that asset file is `lib_yepr_gen/reference.js`, **not**
`lib_yepr_gen/js/reference.js`: Joomla's relative resolution inserts the `js/`
folder itself, so the longer spelling is looked for at
`media/lib_yepr_gen/js/js/reference.js`, is not found, and the asset is dropped
without a word. No exception, no tag in the head — and every dropdown keeps
whatever the server rendered, which looks like a working form until somebody
adds a row. That is the same silent failure 3.1 spent a step on, arriving by a
new route.

## The development site

`Meta-gen/joomla`, git-ignored, served at `http://localhost/Meta-gen/joomla`
with the administrator at `/administrator`. **Its own Joomla, with its own
database** — not a link to Exten-gen's. Installing this component into
Exten-gen's site puts a `#__metagen_*` table in Exten-gen's database and makes
two repositories share one set of state, which is exactly as confusing as it
sounds.

```bash
composer install-local                  # build and install into ./joomla
php tools/seed-metalanguage.php         # er1, something to open
php tools/seed-metalanguage.php lioncore-m3
npm run cypress
```

The seed tool boots Joomla and uses the site's own database driver rather than
reading `configuration.php` into a `JConfig` and opening a mysqli by hand. That
is not tidiness: `JConfig` is a class Joomla writes at install time and no
source file declares, so a tool built that way cannot be analysed.

**Reinstalling does not re-run the install SQL.** Joomla treats an install at
the same version as an update, and update SQL comes from `sql/updates/mysql/`.
After changing a table name, uninstall the component before installing it again
or the table will not be there.

## Quality gates

```
composer test        # phpunit
composer analyse     # phpstan level 5, needs /joomla
composer cs          # phpcs
npx cypress run      # against the real Joomla, when anything touches a screen
```

**What each gate can and cannot see.** Everything above `cypress` reads source
or runs generation with two constants standing in for Joomla; none of it boots
the framework. Every naming defect described on this page passed all three and
was found by the browser — twice as a 500, once as a dropdown holding a raw
uuid.
