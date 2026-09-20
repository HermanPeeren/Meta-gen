# Meta-gen

A Joomla 6 component that models **metalanguages** — and generates the forms a
model written in one is edited with.

```
a metalanguage, in LionCore M3  ──▶  Joomla form XML + a reference table
   (concepts, features, links)         (one form per classifier)
```

Part of a family of four:

| | |
|---|---|
| [Exten-gen](https://github.com/HermanPeeren/Exten-gen) | models extensions, generates them |
| **Meta-gen** | models metalanguages, generates their forms |
| [Gen-gen](https://github.com/HermanPeeren/Gen-gen) | models generators |
| [generator-core](https://github.com/HermanPeeren/generator-core) | the shared engine, `Yepr\Gen`, installed as `lib_yepr_gen` |

## What a metalanguage is

Exten-gen holds *projects*, and a project is written in a language: ER1, whose
vocabulary is entities, fields and pages. Meta-gen holds the languages
themselves. A metalanguage says what kinds of object exist, what each one holds,
and what may point at what — and from that, Meta-gen generates the forms
somebody edits a model of that language with.

It is modelled in **LionCore M3**, which is the LionWeb meta-metamodel: a
language is a set of language entities, each a Classifier or a DataType; a
Classifier is a Concept, a ConceptInterface or an Annotation; and a Classifier
carries Features, each a Property or a Link, a Link being a Containment or a
Reference.

## What it generates

For every classifier, one Joomla form file, plus one `references.json`
describing what a reference dropdown may offer.

- **Subtyping becomes nesting.** A classifier that others extend gets a radio
  saying which one this row is and a subform each, which is how one repeating
  group holds five kinds of thing.
- **Containment becomes a subform**, repeating when the link is.
- **A reference becomes `<field type="Reference" objecttype="Concept" />`**,
  the shared dropdown that fills itself from what the form holds right now — so
  a concept added a minute ago can be pointed at without saving first.

The reference table is the other half of that dropdown, and generating it is
the point: it says both where the server looks in a stored model and how the
browser finds the same rows in the form, and two hand-written halves of one
contract are what drift.

**It is checked against itself.** `tests/Fixtures/languages/lioncore-m3.json`
is LionCore M3 modelled in LionCore M3, so the expected output already exists:
the meta-model this component ships, written by hand. Generating from it
reproduces that table exactly, and an ER1-shaped language reproduces Exten-gen's
too.

## Status

0.1.0, and early. It generates; it does not yet export. What is missing is the
package a generated language travels in — see the shared
[rework plan](https://github.com/HermanPeeren/Exten-gen/blob/main/docs/rework-plan.md),
stage 3.

## Development

```
composer install
composer test           # phpunit
composer analyse        # phpstan level 5, needs /joomla
composer cs             # coding standard
composer install-local  # build and install into ./joomla
npm run cypress         # the browser specs, against that install
```

`docs/development.md` has the rest: the layout, the quality gates and what each
one can and cannot see.

## Licence

GNU General Public License version 3 or later.
