<?php

/**
 * Write LedgerLang: ER1 plus what an accounting model needs.
 *
 *   php tools/make-ledgerlang.php      # tests/Fixtures/languages/ledgerlang.json
 *
 * The demo language for "Model driven Joomla development" (JWC, 16 Oct 2026):
 * a language that grows. ER1 can say what an accounting system *stores* -
 * accounts, journals, entries, lines - and nothing about what makes it
 * accounting. LedgerLang derives from ER1 (step 4.5) and adds the concepts the
 * accounting domain model is built on:
 *
 * - `AccountRole`: a stable key such as `liability.vouchers`, with a nature and
 *   a normal balance. A posting rule posts to a role, never to an account
 *   number, which is what keeps a plugin working with every chart of accounts.
 * - `PostingRule` and `PostingLine`: a business event and the entry it becomes,
 *   as role + debit or credit + an amount expression.
 * - `LocalisationPack`: what differs per country or standard. A container only,
 *   in this version.
 * - `StateMachine`, `State`, `Transition`, on an Entity: a general concept
 *   rather than an accounting one, which is why it hangs off ER1's Entity.
 *
 * **Built from `er1-full.json` rather than written by hand**, for the reason
 * Exten-gen's `make-derived-package.php` gives: a derived language has to be a
 * genuine superset of its parent or `AncestryCheck` refuses it, and a
 * hand-kept copy of twenty-seven ER1 concepts is a copy that drifts. When ER1
 * moves, run this again; `LedgerLangTest` fails until somebody does.
 *
 * It only adds. Three features on Project, each on a fieldset of its own so
 * that Exten-gen shows them as tabs beside ER1's three, and one on Entity.
 *
 * `state_machine` is a single containment: an entity has one life cycle. Its
 * states are a repeating group inside it, which the reference table spells as
 * one dotted step, `state_machine.states`, so a saved transition keeps the
 * names of its states.
 */

declare(strict_types=1);

$root   = \dirname(__DIR__);
$source = $root . '/tests/Fixtures/languages/er1-full.json';
$target = $root . '/tests/Fixtures/languages/ledgerlang.json';

$er1 = json_decode((string) file_get_contents($source), false, 512, \JSON_THROW_ON_ERROR);

/**
 * A property, in the shape the feature form stores.
 */
function property(string $owner, string $name, string $label, string $type, bool $optional, string $default, string $description): object
{
    return (object) [
        'name'           => $name,
        'key'            => 'f-' . $owner . '-' . str_replace('_', '-', $name),
        'label'          => $label,
        'description'    => $description,
        'is_optional'    => $optional ? '1' : '0',
        'is_assigned'    => '0',
        'classifier_key' => '',
        'LIonWeb_key'    => 'Feature',
        'feature_type'   => 'Property',
        'property'       => (object) [
            'type'              => $type,
            'typeReference_key' => $type,
            'default_value'     => $default,
            'LIonWeb_key'       => 'Feature.Property',
        ],
    ];
}

/**
 * A link, in the shape the feature form stores. A fieldset only when given.
 */
function linkTo(
    string $owner,
    string $name,
    string $label,
    string $type,
    string $kind,
    bool $multiple,
    string $description,
    string $fieldset = ''
): object {
    $feature = [
        'name'        => $name,
        'key'         => 'f-' . $owner . '-' . str_replace('_', '-', $name),
        'label'       => $label,
        'description' => $description,
    ];

    if ($fieldset !== '') {
        $feature['fieldset'] = $fieldset;
    }

    return (object) ($feature + [
        'is_optional'    => '1',
        'is_assigned'    => '0',
        'classifier_key' => '',
        'LIonWeb_key'    => 'Feature',
        'feature_type'   => 'Link',
        'link'           => (object) [
            'type'        => $type,
            'link_type'   => $kind,
            'is_multiple' => $multiple ? '1' : '0',
            'LIonWeb_key' => 'Feature.Link',
        ],
    ]);
}

/**
 * A concrete concept holding the given features.
 *
 * @param  object[]  $features
 */
function concept(string $name, array $features): object
{
    $keyed = [];

    foreach ($features as $i => $feature) {
        $keyed['feature' . $i] = $feature;
    }

    return (object) [
        'name'                => $name,
        'key'                 => 'c-' . strtolower($name),
        'languageEntity_type' => 'Classifier',
        'classifier'          => (object) [
            'classifier_type' => 'Concept',
            'concept'         => (object) [
                'abstract'    => '0',
                'partition'   => '0',
                'extends'     => '',
                'implements'  => new stdClass(),
                'LIonWeb_key' => 'LanguageEntity.Classifier.Concept',
            ],
            'feature'         => (object) $keyed,
        ],
        'LIonWeb_key'         => 'LanguageEntity',
    ];
}

/**
 * An enumeration, its literals keyed as stored and named as shown.
 *
 * @param  array<string, string>  $literals
 */
function enumeration(string $name, array $literals): object
{
    $keyed = [];

    foreach (array_keys($literals) as $i => $key) {
        $keyed['literals' . $i] = (object) ['name' => $literals[$key], 'key' => $key, 'LIonWeb_key' => 'EnumerationLiteral'];
    }

    return (object) [
        'name'                => $name,
        'key'                 => 'dt-' . strtolower($name),
        'languageEntity_type' => 'DataType',
        'datatype'            => (object) [
            'dataType_type' => 'Enumeration',
            'enumeration'   => (object) ['literals' => (object) $keyed],
        ],
        'LIonWeb_key'         => 'LanguageEntity',
    ];
}

/**
 * Append a feature to a stored concept, after the ones it has.
 */
function append(object $concept, object $feature): void
{
    $features = (array) $concept->classifier->feature;

    $features['feature' . \count($features)] = $feature;

    $concept->classifier->feature = (object) $features;
}

$entities = (array) $er1->languageEntities;

foreach ($entities as $entity) {
    if ($entity->key === 'c-project') {
        append($entity, linkTo(
            'project',
            'account_roles',
            'Account roles',
            'c-accountrole',
            'Containment',
            true,
            'The stable keys a posting rule posts to. A chart of accounts maps each role to a real account.',
            'account_roles'
        ));
        append($entity, linkTo(
            'project',
            'posting_rules',
            'Posting rules',
            'c-postingrule',
            'Containment',
            true,
            'A business event and the journal entry it becomes, in roles rather than account numbers.',
            'posting_rules'
        ));
        append($entity, linkTo(
            'project',
            'localisation_packs',
            'Localisation packs',
            'c-localisationpack',
            'Containment',
            true,
            'What differs per country or standard.',
            'localisation'
        ));
    }

    if ($entity->key === 'c-entity') {
        append($entity, linkTo(
            'entity',
            'state_machine',
            'State machine',
            'c-statemachine',
            'Containment',
            false,
            'The states a row of this entity goes through, and the transitions between them.'
        ));
    }
}

$added = [
    enumeration('Nature', ['asset' => 'Asset', 'liability' => 'Liability', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expense']),
    enumeration('Side', ['debit' => 'Debit', 'credit' => 'Credit']),
    concept('AccountRole', [
        property('accountrole', 'key', 'Key', 'dt-string', false, '', 'A stable key such as liability.vouchers. Posting rules point at it.'),
        property('accountrole', 'name', 'Name', 'dt-string', false, '', ''),
        property('accountrole', 'nature', 'Nature', 'dt-nature', false, 'asset', ''),
        property('accountrole', 'normal_balance', 'Normal balance', 'dt-side', false, 'debit', ''),
    ]),
    concept('PostingRule', [
        property('postingrule', 'event_name', 'Event', 'dt-string', false, '', 'The business event, such as VoucherSold.'),
        property('postingrule', 'description', 'Description', 'dt-text', true, '', ''),
        linkTo('postingrule', 'lines', 'Lines', 'c-postingline', 'Containment', true, 'No lines means the event posts nothing.'),
    ]),
    concept('PostingLine', [
        linkTo('postingline', 'role', 'Account role', 'c-accountrole', 'Reference', false, ''),
        property('postingline', 'side', 'Side', 'dt-side', false, 'debit', ''),
        property(
            'postingline',
            'amount',
            'Amount expression',
            'dt-string',
            false,
            'amount',
            'An expression over the event data, such as amount - vat.'
        ),
    ]),
    concept('LocalisationPack', [
        property('localisationpack', 'code', 'Country or standard code', 'dt-string', false, '', 'Such as NL, DE-SKR03 or UK-GAAP.'),
        property('localisationpack', 'name', 'Name', 'dt-string', false, '', ''),
        property(
            'localisationpack',
            'terminology_locale',
            'Terminology locale',
            'dt-string',
            true,
            '',
            'The accounting terminology, which is a separate setting from the interface language.'
        ),
    ]),
    concept('StateMachine', [
        property('statemachine', 'name', 'Name', 'dt-string', false, 'status', 'Such as status.'),
        linkTo('statemachine', 'initial_state', 'Initial state', 'c-state', 'Reference', false, ''),
        linkTo('statemachine', 'states', 'States', 'c-state', 'Containment', true, ''),
        linkTo('statemachine', 'transitions', 'Transitions', 'c-transition', 'Containment', true, ''),
    ]),
    concept('State', [
        property('state', 'key', 'Key', 'dt-string', false, '', 'Such as draft, posted or reversed.'),
        property('state', 'name', 'Name', 'dt-string', true, '', ''),
    ]),
    concept('Transition', [
        property('transition', 'name', 'Name', 'dt-string', false, '', 'The action, such as post or reverse.'),
        linkTo('transition', 'from', 'From', 'c-state', 'Reference', false, ''),
        linkTo('transition', 'to', 'To', 'c-state', 'Reference', false, ''),
    ]),
];

foreach ($added as $entity) {
    $entities['languageEntities' . \count($entities)] = $entity;
}

// In the order the language form writes them: dependsOn after the version.
$ledger = [
    'name'             => 'LedgerLang',
    'version'          => '0.1',
    'dependsOn'        => (object) ['dependsOn0' => (object) ['language' => 'ER1|' . $er1->version]],
    'LIonWeb_key'      => $er1->LIonWeb_key,
    'languageEntities' => (object) $entities,
];

$flags = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR;

file_put_contents($target, json_encode($ledger, $flags) . "\n");

printf("LedgerLang 0.1, deriving from ER1 %s: %d language entities -> %s\n", $er1->version, \count($entities), $target);
