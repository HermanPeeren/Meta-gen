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

## A language can come from somewhere else

**Import LionWeb language**, on the metalanguages list, reads a LionWeb
serialization chunk and stores it as a metalanguage. From then on it is a
metalanguage like any other - forms are generated from it, a package carries
it, Exten-gen imports that - and nothing downstream can tell where it came
from, which is the point.

[LionWeb](https://lionweb.io) is how a model moves between tools that were not
written for each other. Both it and this component model LionCore M3, but a
chunk is a flat list of nodes addressed by metapointer while a stored
metalanguage is a Joomla form's shape, so the two could not read each other
until there was a translation. That lives in the shared library, as
`Yepr\Gen\Core\Lionweb`, because Exten-gen needs the same reader for the models
written in these languages and two implementations of one format drift.

It reads a path under the site rather than an upload: JcbInOut writes JCB's
language to disk on the same site, so the two components meet on the filesystem
and the field is filled in for you when that file is there.

Anything the stored shape has no room for is reported rather than dropped - an
interface extending more than one interface, a datatype kind with no
equivalent, a type from a language that is not in the chunk. The LionCore
builtins are the exception: a property typed `String` points into *that*
language, and since a stored metalanguage cannot depend on another one, the
builtins a language uses are materialised as primitive types of its own.

It is checked against JCB: 1082 nodes derived by reflection from a component
nobody wrote for this family become 139 language entities with no diagnostics,
and 126 forms are generated from them.

## Install

Download `com_metagen-<version>.zip` from
[Releases](https://github.com/HermanPeeren/Meta-gen/releases) and install it
through **System → Install → Extensions**. It needs Joomla 6 and PHP 8.3.

The package carries the shared library, `lib_yepr_gen`, and installs it when the
site has none or an older one — so there is one thing to install, not two. After
that the component's update server offers new versions the ordinary way.

## Status

0.1.0. It generates a language's forms and exports them as a package — a zip
holding the concept model, the forms, the reference table, a language file and a
manifest naming the language, its version and its root classifier.

Both siblings read one. Exten-gen imports a package and edits projects through
its forms; ER1 itself is now a generated package rather than twenty-four
hand-written form files, so the language Exten-gen has always spoken goes
through the same reader as any other. Gen-gen imports one too, and a generator
bound to a language offers that language's concepts where a rule says what to
select.

What is not done: a generator is still written for one language at a time.
Exten-gen refuses to run its generators over a project written in something
other than ER1, because the rules are about ER1 by name — a refusal rather than
a silence, and the honest edge of where this has got to. The shared
[rework plan](https://github.com/HermanPeeren/Exten-gen/blob/main/docs/rework-plan.md)
has the rest.

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
