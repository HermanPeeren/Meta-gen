<?php

/**
 * Read a set of Joomla forms back into a modelled language: step 3.5.
 *
 *   php tools/import-forms.php --base=../Exten-gen/src \
 *       --root=administrator/components/com_extengen/forms/project_er1.xml \
 *       --name=ER1 --version=1.0 --out=tests/Fixtures/languages/er1.json
 *
 * **This is a migration aid, not a feature.** 3.5 asks for ER1 modelled in
 * LionCore M3, and ER1 is twenty-six form files with about a hundred and fifty
 * fields in them. Typing that as JSON by hand would produce something nobody
 * could review and everybody would have to trust; deriving it from the forms
 * produces something that can be read beside the files it came from.
 *
 * **What that does and does not prove.** Generating forms from a model this
 * tool derived *from* forms is a round trip through two pieces of code, so
 * agreement says the two are inverses - it does not say the model is a good
 * model. Two things keep it honest. The input is hand-written: those files were
 * not produced by the generator, so anything the generator does differently
 * shows up as a difference rather than being absorbed. And this reader is
 * deliberately naive - it maps what a form says and refuses to guess - so a
 * convention the generator happens to use cannot be quietly encoded here to
 * make a diff go away. The model still has to be read by a person, which is the
 * point of producing one small enough to read.
 *
 * Every judgement it makes is reported. Run it, read the notes, then read the
 * model.
 */

declare(strict_types=1);

\defined('_JEXEC') || \define('_JEXEC', 1);

$options = [
    'base'    => '',
    'root'    => '',
    'name'    => '',
    'version' => '1.0',
    'out'     => '',
    'rootname' => '',
];

foreach (\array_slice($argv, 1) as $argument) {
    if (preg_match('/^--([a-z]+)=(.*)$/', $argument, $matched) && \array_key_exists($matched[1], $options)) {
        $options[$matched[1]] = $matched[2];

        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(1);
}

foreach (['base', 'root', 'name'] as $required) {
    if ($options[$required] === '') {
        fwrite(STDERR, "--{$required} is required.\n");
        exit(1);
    }
}

$base = rtrim(str_replace('\\', '/', (string) realpath($options['base'])), '/');

if ($base === '') {
    fwrite(STDERR, "No such directory: {$options['base']}\n");
    exit(1);
}

$importer = new FormSetImporter($base);
$model    = $importer->read($options['root'], $options['name'], $options['version'], $options['rootname']);

foreach ($importer->notes() as $note) {
    fwrite(STDERR, $note . "\n");
}

$json = json_encode($model, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR) . "\n";

if ($options['out'] === '') {
    echo $json;

    exit(0);
}

file_put_contents($options['out'], $json);

echo 'wrote ', $options['out'], ' (', \strlen($json), " bytes)\n";

/**
 * A set of Joomla forms, read as a language.
 *
 * One form file is one classifier. A subform field is a containment to the
 * classifier its `formsource` names; a `type="Reference"` field is a reference
 * to the classifier its `objecttype` names; everything else is a property whose
 * datatype comes from the input type.
 */
final class FormSetImporter
{
    /**
     * How a Joomla input type reads back as a datatype.
     *
     * The inverse of `FormXml::PRIMITIVES`, and deliberately not a perfect one:
     * several inputs mean `String`, so the round trip through here and back is
     * lossy in a way the notes report rather than hide.
     *
     * @var array<string, string>
     */
    private const DATATYPES = [
        'text'     => 'String',
        'textarea' => 'Text',
        'editor'   => 'Text',
        'checkbox' => 'Boolean',
        'number'   => 'Integer',
        'calendar' => 'Date',
        'url'      => 'Url',
        'email'    => 'Email',
        'media'    => 'Image',
        'file'     => 'File',
        'hidden'   => 'String',
    ];

    /** @var array<string, SimpleXMLElement>  site-relative path => the form */
    private array $forms = [];

    /** @var array<string, array<string, mixed>>  classifier key => the classifier being built */
    private array $classifiers = [];

    /**
     * Datatypes by name.
     *
     * @var array<string, array{key: string, literals: array<int, array{name: string, key: string}>}>
     */
    private array $dataTypes = [];

    /** @var string[] */
    private array $notes = [];

    /** @var array<string, string>  site-relative form path => classifier key */
    private array $keyForForm = [];

    public function __construct(private readonly string $base)
    {
    }

    /**
     * @return string[]
     */
    public function notes(): array
    {
        return $this->notes;
    }

    /**
     * The whole set, as the JSON this component stores a metalanguage in.
     *
     * @return array<string, mixed>
     */
    public function read(string $root, string $name, string $version, string $rootName = ''): array
    {
        $rootKey = $this->classifierFor($root, $rootName);

        $this->classifiers[$rootKey]['partition'] = true;

        $entities = [];
        $index    = 0;

        foreach ($this->dataTypes as $dataTypeName => $dataType) {
            if ($dataType['literals'] === []) {
                $datatype = [
                    'dataType_type' => 'PrimitiveType',
                    'primitiveType' => ['LIonWeb_key' => 'DataType.PrimitiveType'],
                ];
            } else {
                $literals = [];
                $literal  = 0;

                foreach ($dataType['literals'] as $one) {
                    $literals['literals' . $literal++] = [
                        'name'        => $one['name'],
                        'key'         => $one['key'],
                        'LIonWeb_key' => 'EnumerationLiteral',
                    ];
                }

                $datatype = [
                    'dataType_type' => 'Enumeration',
                    'enumeration'   => [
                        'literals'    => $literals,
                        'LIonWeb_key' => 'DataType.Enumeration',
                    ],
                ];
            }

            $entities['languageEntities' . $index++] = [
                'name'                => $dataTypeName,
                'key'                 => $dataType['key'],
                'languageEntity_type' => 'DataType',
                'datatype'            => $datatype,
                'LIonWeb_key'         => 'LanguageEntity',
            ];
        }

        foreach ($this->classifiers as $key => $classifier) {
            $features = [];
            $feature  = 0;

            foreach ($classifier['features'] as $built) {
                $features['feature' . $feature++] = $built;
            }

            $entities['languageEntities' . $index++] = [
                'name'                => $classifier['name'],
                'key'                 => $key,
                'languageEntity_type' => 'Classifier',
                'classifier'          => [
                    'classifier_type' => 'Concept',
                    'concept'         => [
                        'abstract'    => $classifier['abstract'] ? '1' : '0',
                        'partition'   => $classifier['partition'] ? '1' : '0',
                        'extends'     => $classifier['extends'],
                        'implements'  => (object) [],
                        'LIonWeb_key' => 'LanguageEntity.Classifier.Concept',
                    ],
                    'feature'         => $features === [] ? (object) [] : $features,
                ],
                'LIonWeb_key'         => 'LanguageEntity',
            ];
        }

        return [
            'name'             => $name,
            'version'          => $version,
            'LIonWeb_key'      => 'Language',
            'languageEntities' => $entities,
        ];
    }

    /**
     * The classifier for one form file, reading it if this is the first ask.
     */
    private function classifierFor(string $path, string $called = ''): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (isset($this->keyForForm[$path])) {
            return $this->keyForForm[$path];
        }

        // A form file is usually named after its concept, and the root one is
        // not: `project_er1.xml` holds a Project, and carries that suffix only
        // because this component also ships a `project_chrome.xml`. The caller
        // says so rather than this guessing at suffixes.
        $name = $called !== '' ? $called : ucfirst(basename($path, '.xml'));
        $key  = 'c-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $name) ?? $name);

        // Recorded before the fields are read, because a form may reach itself
        // through a subform and reading it again would never end.
        $this->keyForForm[$path]  = $key;
        $this->classifiers[$key]  = [
            'name'      => $name,
            'abstract'  => false,
            'partition' => false,
            'extends'   => '',
            'features'  => [],
        ];

        $form = $this->form($path);

        if ($form === null) {
            $this->notes[] = 'warning: ' . $path . ' is named by a subform and is not there.';

            return $key;
        }

        $this->classifiers[$key]['features'] = $this->featuresOf($form, $path, $name, $key);

        return $key;
    }

    /**
     * One form file, or null when it cannot be read.
     */
    private function form(string $path): ?SimpleXMLElement
    {
        if (\array_key_exists($path, $this->forms)) {
            return $this->forms[$path];
        }

        $file = $this->base . '/' . $path;

        if (!is_file($file)) {
            return $this->forms[$path] = null;
        }

        $xml = simplexml_load_file($file);

        return $this->forms[$path] = $xml === false ? null : $xml;
    }

    /**
     * Every feature one form declares.
     *
     * @return array<int, array<string, mixed>>
     */
    private function featuresOf(SimpleXMLElement $form, string $path, string $owner, string $ownerKey): array
    {
        // Where this form's own field classes live, for any field whose
        // type no primitive matches. Read off the fieldset rather than
        // assumed, because a form may declare more than one.
        $prefixes = $form->xpath('//*[@addfieldprefix]/@addfieldprefix') ?: [];
        $prefix   = $prefixes === [] ? '' : (string) $prefixes[0];

        $fields       = $form->xpath('//field') ?: [];
        $discriminated = $this->subtypesIn($fields, $path, $ownerKey);
        $features     = [];

        foreach ($fields as $field) {
            $fieldName = (string) $field['name'];
            $type      = (string) $field['type'];

            if ($fieldName === '' || isset($discriminated[$fieldName])) {
                continue;
            }

            // A hidden `<thing>_id` is an identity or a pointer at the row
            // above. The first belongs in the model, because `Naming` finds the
            // identity property by exactly this convention; the second does
            // not, because the generator derives a parent key from where a
            // classifier sits rather than from a field.
            if ($type === 'hidden' && str_ends_with($fieldName, '_id')) {
                $subject = substr($fieldName, 0, -3);

                // A `<x>_id` beside a reference dropdown called `<x>` is that
                // dropdown's hidden backup - what 1.9 stores the chosen id in.
                // It is neither an identity nor a pointer at the row above, and
                // the generated forms produce it from the reference itself.
                if ($this->hasReferenceNamed($fields, $subject)) {
                    $this->notes[] = 'note: ' . $path . ' carries ' . $fieldName
                        . ', the hidden backup of the ' . $subject . ' dropdown; left out.';

                    continue;
                }

                if (strcasecmp($subject, $owner) !== 0) {
                    $this->notes[] = 'note: ' . $path . ' carries ' . $fieldName
                        . ', which points at the row above rather than describing this one; left out.';

                    continue;
                }
            }

            $feature = $this->featureFor($field, $path, $owner, $fieldName, $type, $prefix);

            if ($feature !== null) {
                $features[] = $feature;
            }
        }

        return $features;
    }

    /**
     * Whether these conditional subforms are one per choice, covering all of them.
     *
     * @param  SimpleXMLElement[]  $answered
     * @param  SimpleXMLElement[]  $options
     */
    private function partitions(array $answered, array $options, string $on): bool
    {
        $values = [];

        foreach ($options as $option) {
            $values[] = (string) $option['value'];
        }

        $covered = [];

        foreach ($answered as $subform) {
            $showon = (string) $subform['showon'];
            $shown  = explode(',', substr($showon, \strlen($on) + 1));

            if (\count($shown) !== 1) {
                return false;
            }

            $covered[] = $shown[0];
        }

        sort($values);
        sort($covered);

        return $values === $covered && \count($covered) === \count(array_unique($covered));
    }

    /**
     * Whether a form has a reference dropdown by this name.
     *
     * @param  SimpleXMLElement[]  $fields
     */
    private function hasReferenceNamed(array $fields, string $name): bool
    {
        foreach ($fields as $field) {
            if (
                strcasecmp((string) $field['type'], 'Reference') === 0
                && strcasecmp((string) $field['name'], $name) === 0
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The subtyping one form declares, as field names to ignore afterwards.
     *
     * A radio or list whose options are answered by sibling subforms with a
     * matching `showon` is not a property - it is the question "which kind of
     * thing is this row", and the subforms are the kinds. That is exactly what
     * `LanguageStructure` emits, read backwards.
     *
     * @param  SimpleXMLElement[]  $fields
     *
     * @return array<string, true>  field names that are structure rather than content
     */
    private function subtypesIn(array $fields, string $path, string $ownerKey): array
    {
        $consumed = [];

        foreach ($fields as $field) {
            $name    = (string) $field['name'];
            $options = $field->xpath('option') ?: [];

            if ($name === '' || $options === []) {
                continue;
            }

            $answered = [];

            foreach ($fields as $candidate) {
                $showon = (string) $candidate['showon'];

                if ($showon === '' || !str_starts_with($showon, $name . ':')) {
                    continue;
                }

                if ((string) $candidate['type'] !== 'subform') {
                    continue;
                }

                $values = explode(',', substr($showon, \strlen($name) + 1));

                if (\count($values) > 1) {
                    $this->notes[] = 'note: ' . $path . ' shows ' . (string) $candidate['name']
                        . ' for more than one value of ' . $name . ' (' . implode(', ', $values)
                        . '); a modelled language has one kind per choice, so it becomes one.';
                }

                $answered[] = $candidate;
            }

            if ($answered === []) {
                continue;
            }

            // Two different things wear this syntax, and only one of them is
            // subtyping.
            //
            // `field.xml` offers property or reference and answers each with
            // exactly one subform: every choice is covered, no subform serves
            // two, and the subform *is* the content for that kind. That is a
            // classifier with two subtypes.
            //
            // `page.xml` offers index, details and subform pages, and then
            // shows `filters` and `presentationcolumns` for an index page and
            // `editfields` for the other two. Those are not kinds of page -
            // they are ordinary containments that only apply sometimes, and a
            // page still has a name and an entity whichever it is.
            //
            // So it is subtyping only when the subforms partition the choices:
            // one each, all covered. Anything else is conditional visibility,
            // which LionCore M3 as modelled here cannot express at all - a
            // feature has no "applies when". That is a model gap and it is
            // reported as one rather than bent into extends.
            if (!$this->partitions($answered, $options, $name)) {
                $this->notes[] = 'gap: ' . $path . ' shows ' . \count($answered)
                    . ' fields conditionally on ' . $name . ', which does not partition its '
                    . \count($options) . ' choices. They are read as ordinary features and the '
                    . 'condition is lost; a modelled language cannot say "only when".';

                continue;
            }

            $consumed[$name] = true;

            foreach ($answered as $subform) {
                $consumed[(string) $subform['name']] = true;

                $source = (string) $subform['formsource'];

                if ($source === '') {
                    continue;
                }

                $subtype = $this->classifierFor($source);

                $this->classifiers[$subtype]['extends'] = $ownerKey;
            }

            // A row that is only ever one of its kinds is abstract. The
            // hand-written forms cannot say so, and defaulting to concrete
            // would put the parent in its own list of choices - which reads as
            // an option nobody means.
            $this->classifiers[$ownerKey]['abstract'] = true;

            $this->notes[] = 'note: ' . $path . ' splits on ' . $name . ' into '
                . \count($answered) . ' kinds; recorded as subtyping, and ' . $path
                . "'s own classifier marked abstract.";
        }

        return $consumed;
    }

    /**
     * One field, as a feature.
     *
     * @return array<string, mixed>|null
     */
    private function featureFor(
        SimpleXMLElement $field,
        string $path,
        string $owner,
        string $fieldName,
        string $type,
        string $prefix = ''
    ): ?array {
        $key      = 'f-' . strtolower($owner) . '-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $fieldName) ?? $fieldName);
        $optional = strtolower((string) $field['required']) !== 'true';

        // A subform's `min` says how few rows it may have, which is
        // multiplicity and not presentation: min="1" means the thing always has
        // at least one of these. `is_optional` is where this language keeps
        // that, so it is read into it rather than dropped with the widths.
        if ($type === 'subform' && (int) ($field['min'] ?? 0) > 0) {
            $optional = false;
        }

        $feature = [
            'name'           => $fieldName,
            'key'            => $key,
            'label'          => $this->labelOf($field),
            'description'    => '',
            'is_optional'    => $optional ? '1' : '0',
            // A hidden input is a value nobody types. That is what these forms
            // use for a surrogate identity, and a generated form has to hide it
            // too - otherwise an internal number appears on screen in a box
            // somebody can edit.
            'is_assigned'    => $type === 'hidden' ? '1' : '0',
            'classifier_key' => '',
            'LIonWeb_key'    => 'Feature',
        ];

        if ($type === 'subform') {
            $source = (string) $field['formsource'];

            if ($source === '') {
                $this->notes[] = 'warning: ' . $path . ' has a subform ' . $fieldName
                    . ' with no formsource; left out.';

                return null;
            }

            $feature['feature_type'] = 'Link';
            $feature['link']         = [
                'type'        => $this->classifierFor($source),
                'link_type'   => 'Containment',
                'is_multiple' => strtolower((string) $field['multiple']) === 'true' ? '1' : '0',
                'LIonWeb_key' => 'Feature.Link',
            ];

            return $feature;
        }

        if (strcasecmp($type, 'Reference') === 0) {
            $target = (string) $field['objecttype'];

            if ($target === '') {
                $this->notes[] = 'warning: ' . $path . ' has a reference ' . $fieldName
                    . ' with no objecttype; left out.';

                return null;
            }

            $feature['feature_type'] = 'Link';
            $feature['link']         = [
                'type'        => 'c-' . strtolower($target),
                'link_type'   => 'Reference',
                'is_multiple' => '0',
                'LIonWeb_key' => 'Feature.Link',
            ];

            return $feature;
        }

        $feature['feature_type'] = 'Property';
        $feature['property']     = [
            'type'              => $this->enumerationFor($field, $owner, $fieldName)
                ?? $this->dataTypeFor($type, $field, $path, $fieldName),
            'typeReference_key' => '',
            // A form's `default` is what a new row holds, which is a fact
            // about the language rather than about the form - so unlike `size`
            // and `min` it survives the trip.
            'default_value'     => trim((string) $field['default']),
            'LIonWeb_key'       => 'Feature.Property',
        ];

        // A field type no primitive matches is a class the component wrote, and
        // generating a text box for it loses whatever it did. ER1's Slot picker
        // reads its choices from a catalogue; a text box reads nothing.
        if (!isset(self::DATATYPES[strtolower($type)]) && ($field->xpath('option') ?: []) === []) {
            $feature['property']['field_type']       = $type;
            $feature['property']['field_prefix']     = $prefix;
            $feature['property']['field_parameters'] = $this->parametersOf($field);
        }

        $feature['property']['typeReference_key'] = $feature['property']['type'];

        return $feature;
    }

    /**
     * The attributes a custom field class reads.
     *
     * Everything that is not one of Joomla's own. `owner="Entity"` on ER1's
     * Slot picker is the reason this exists: without it the field resolves and
     * offers nothing, which reads as "there are no slots" rather than as a
     * mistake.
     *
     * The list below is what the generator writes itself or what presentation
     * covers. Anything else the form said, the field class asked for.
     *
     * @return array<string, array{name: string, value: string, LIonWeb_key: string}>
     */
    private function parametersOf(SimpleXMLElement $field): array
    {
        $own = [
            'name', 'type', 'label', 'description', 'id', 'class', 'size', 'default',
            'required', 'multiple', 'buttons', 'layout', 'showon', 'formsource',
            'objecttype', 'filter', 'validate', 'min', 'max', 'hint', 'readonly',
            'disabled', 'addfieldprefix', 'addruleprefix', 'value',
        ];

        $parameters = [];
        $index      = 0;

        foreach ($field->attributes() ?? [] as $name => $value) {
            if (\in_array((string) $name, $own, true)) {
                continue;
            }

            $parameters['parameter' . $index++] = [
                'name'        => (string) $name,
                'value'       => (string) $value,
                'LIonWeb_key' => 'FieldParameter',
            ];
        }

        return $parameters;
    }

    /**
     * The enumeration a field's own options describe, when it has any.
     *
     * A `list` or `radio` that got this far is one the form offers a closed set
     * of answers for and did not use to choose between subtypes - which is what
     * an enumeration is. Reading it as a String would generate a text box where
     * the hand-written form has a dropdown, and lose the answers with it.
     *
     * The literals keep the option's own value and text, so the generated
     * `<option value="...">` is the one that was there.
     *
     * @return string|null  The datatype's key, or null when this is not a closed list.
     */
    private function enumerationFor(SimpleXMLElement $field, string $owner, string $fieldName): ?string
    {
        $options = $field->xpath('option') ?: [];

        if ($options === []) {
            return null;
        }

        $literals = [];

        foreach ($options as $option) {
            $value = (string) $option['value'];

            $literals[] = [
                'name' => trim((string) $option) === '' ? $value : trim((string) $option),
                'key'  => $value,
            ];
        }

        $name = $this->enumerationName($owner, $fieldName);

        if (isset($this->dataTypes[$name]) && $this->dataTypes[$name]['literals'] !== $literals) {
            // Two fields wanting one name with different answers. Keeping the
            // first silently would give the second a dropdown holding somebody
            // else's choices, which is worse than an ugly name.
            $name .= 'Of' . ucfirst($owner);
        }

        if (!isset($this->dataTypes[$name])) {
            $this->dataTypes[$name] = [
                'key'      => 'dt-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $name) ?? $name),
                'literals' => $literals,
            ];
        }

        return $this->dataTypes[$name]['key'];
    }

    /**
     * What to call the enumeration a field's options describe.
     *
     * The field's own name, in PascalCase: `page_type` is a PageType and
     * `link_type` is a LinkType. A field whose name is one bare word is too
     * generic to stand alone - `type` on a Property is a PropertyType, not a
     * Type - so that one takes the owner in front.
     */
    private function enumerationName(string $owner, string $fieldName): string
    {
        $parts = array_values(array_filter(preg_split('/[^A-Za-z0-9]+/', $fieldName) ?: []));
        $name  = implode('', array_map('ucfirst', $parts));

        return \count($parts) > 1 ? $name : ucfirst($owner) . $name;
    }

    /**
     * The datatype key for one input type.
     */
    private function dataTypeFor(string $type, SimpleXMLElement $field, string $path, string $fieldName): string
    {
        $name = self::DATATYPES[strtolower($type)] ?? null;

        if ($name === null) {
            // A field type this component knows nothing about - one of the
            // consuming component's own classes. The property is still a string
            // underneath, and `field_type` beside it says what edits it, so the
            // generated form keeps the class rather than replacing it with a
            // text box.
            $this->notes[] = 'note: ' . $path . ' uses field type "' . $type . '" for ' . $fieldName
                . '; the value reads as String and the field class is kept.';

            $name = 'String';
        }

        if (!isset($this->dataTypes[$name])) {
            $this->dataTypes[$name] = ['key' => 'dt-' . strtolower($name), 'literals' => []];
        }

        return $this->dataTypes[$name]['key'];
    }

    /**
     * What a field calls itself, when it says so in words.
     *
     * A label that is a language constant is not text - it is a name for text
     * kept somewhere else, and the generated set defines its own constants from
     * the labels in the model. So a constant reads as no label at all and the
     * feature falls back to its name, which is exactly what it did before.
     */
    private function labelOf(SimpleXMLElement $field): string
    {
        $label = trim((string) $field['label']);

        if ($label === '' || preg_match('/^[A-Z][A-Z0-9_]*$/', $label) === 1) {
            return '';
        }

        return $label;
    }
}
