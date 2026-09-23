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

## What a metalanguage package is

A zip, produced by the Export button in the list and by the generate screen,
holding one language and everything needed to edit a model written in it.

```
manifest.json                 the language, its version, its root classifier, a hash per file
model.json                    the concept model it was generated from
forms/<classifier>.xml        one form per classifier
forms/references.json         the table the reference dropdowns read
language/en-GB/<lang>.ini     every label on those forms
```

**Nothing in it names a component.** A package is consumed by com_extengen
*and* by com_gengen, so a language key spelled `COM_EXTENGEN_*` is wrong
whichever of the two loads it — and so is a path under
`administrator/components/com_metagen/`, which is where 3.2 put every generated
file. Constants are scoped by the language instead: `YEPR_LIONCORE_M3_*`.

**It declares where it expects to live, and it has to.** Joomla resolves a
subform's `formsource` as `JPATH_ROOT . '/' . $formsource`
(`SubformField::__set()`) and nothing else — there is no package-relative
spelling — so the install directory is baked into the XML at generation time.
It is

```
media/yepr_metalanguages/<language>/<version>/
```

`media/` because it is the one shared site directory no single extension's
uninstall owns; `<language>/<version>/` because from 3.4 on a project records
both, which only means anything if two versions can sit side by side.
Installing is then a plain unpack with no XML rewritten on the way in, which is
what makes the round-trip worth proving: the forms that run are the bytes that
were generated. The root is *recorded* in the manifest rather than assumed, so
an importer that has to put a language elsewhere can see the mismatch and
regenerate instead of unpacking a set of forms whose subforms all point at a
directory that is not there.

**The manifest hashes every other file**, which is the only way "the forms in
it are the forms the generator produced" is a question with an answer. The
failure it stands for is a truncated download: a form file missing its last
bytes still parses far enough for Joomla to render a fieldset with nothing in
it, and reports nothing anywhere.

**The format itself lives in the library**, as `Yepr\Gen\Core\Package\*`. It
was written here at 3.3 and moved at 3.4, when Exten-gen and Gen-gen became
readers too — a format three components agree on is a mechanism, which is
where the reference dropdown went for the same reason.

`PackageReader` reads one back, from a zip or from an unpacked tree, and
`problems()` lists what is wrong with it rather than throwing on the first
thing — a person choosing a file to import wants to be told what is wrong with
the one they picked. `model()` hands back decoded JSON and stops; turning that
into a `ConceptModel` is this component's job, and that seam is the whole
reason the format could move.

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

## Releasing

Pushing a `v*` tag is the whole procedure. `.github/workflows/release.yml`
checks the tag against the manifest, regenerates `updates.xml` and fails if that
changes anything, runs every gate a runner can run, builds the package, asserts
what is inside it, and publishes a GitHub release with the zip attached.

Three numbers have to agree and only one of them is edited by hand:

| | |
|---|---|
| `src/metagen.xml` `<version>` | the one you edit |
| the tag | checked against it by the workflow |
| `updates.xml` | generated from it by `build/update-xml.php` |

So a release is: bump the manifest, run `composer update-xml`, commit, push,
tag. Tagging a commit whose `updates.xml` is stale fails the workflow rather
than publishing something that points at a download nobody uploaded.

A fourth number lives beside them and is not part of the tag. `LIBRARY_MINIMUM`
in `src/script.php` says which `lib_yepr_gen` release this version needs, and
`composer.json` has to ask for the same thing — `ReleaseTest` fails when they
disagree, which they did right up to 0.1.0: composer asked for `^0.8`, where
`Lionweb\ChunkBuilder` arrived, while the script still said `0.6.0` from before
the LionWeb import existed. The build reads `LIBRARY_MINIMUM` to decide what to
bundle, so on a runner — where there is no sibling checkout — the released
package carries exactly that library version, fetched from its own release. A
local build takes the newest library lying beside it instead, as long as it is
at least that, which is why the zip you build here and the zip CI publishes can
carry different library versions and both be right.

**`updates.xml` is served from `main`, not from the release.** The manifest
points at `raw.githubusercontent.com/.../main/updates.xml`, so a site learns
about a new version the moment the commit lands, whether or not the tag was ever
pushed. Commit and tag together.

**What the gates still cannot see.** No gate installs the package on a site that
has never had this component. `composer install-local` builds the real zip and
installs it, so the ordinary route is exercised — but always onto a site that
already has the tables, which means the install SQL runs as an update. A table
rename needs an uninstall first; the same trap as everywhere else on this page.
