<?php

/**
 * @package     Metagen
 * @subpackage  Lionweb
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Metagen\Administrator\Lionweb;

use Yepr\Component\Metagen\Administrator\Generator\Model\Feature;
use Yepr\Gen\Core\Lionweb\Chunk;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * How a LionWeb chunk says a property is edited over several lines: step 6.4.
 *
 * LionCore M3 has no word for it. A Property has a name, a key, a type and
 * whether it is optional, and a `String` holding a PHP method looks exactly
 * like a `String` holding a title. So the language that wants its method
 * bodies in a textarea says so the way LionWeb lets anything be said about a
 * node that its own metamodel does not cover: **an annotation on the Property
 * node**, an instance of an Annotation from a small language of this family's.
 *
 * ```
 * language   yepr-forms, version 1
 * annotation yepr-forms-Editor             annotates LionCore-M3 Property
 * property   yepr-forms-Editor-value       String: "text" or "code"
 * ```
 *
 * One annotation node, its `parent` the Property node and its id listed in
 * that node's `annotations`, with the chunk's `languages` naming `yepr-forms`.
 * No annotation is one line, so every chunk written before this - JcbInOut's
 * included - still reads as it did, and a LionWeb tool that has never heard of
 * `yepr-forms` keeps the annotation as one more node it carries without
 * understanding. That is what annotations are for, and why this is one rather
 * than a fifth reserved key or a property M3 does not have.
 *
 * **Why here and not in the library's `LionCoreLanguage`**, where the rest of
 * the conversion is: putting it there is a library release and a constraint
 * raised in three components, for a word only this component reads - Exten-gen
 * gets textareas through the generated forms in the package, not through the
 * chunk. When the library next moves for another reason this can move with
 * it, and the keys below are the contract either way.
 *
 * **Keyed by the feature's key, not by node id.** The stored language keeps no
 * node ids, and LionWeb holds feature keys unique language-wide, so the key is
 * the one thing both sides have.
 *
 * @since  0.7.0
 */
final class EditorAnnotation
{
    /**
     * The language the annotation belongs to.
     *
     * @since  0.7.0
     */
    public const LANGUAGE = 'yepr-forms';

    /**
     * @since  0.7.0
     */
    public const VERSION = '1';

    /**
     * The Annotation's key: what an annotation node's classifier points at.
     *
     * @since  0.7.0
     */
    public const ANNOTATION = 'yepr-forms-Editor';

    /**
     * Its one property's key, holding `text` or `code`.
     *
     * @since  0.7.0
     */
    public const VALUE = 'yepr-forms-Editor-value';

    /**
     * LionCore's own keys, as far as this reads them.
     */
    private const M3       = 'LionCore-M3';
    private const PROPERTY = 'Property';
    private const KEY      = 'IKeyed-key';

    /** @var list<array{severity: string, code: string, message: string}> */
    private array $diagnostics = [];

    public function __construct(private readonly Chunk $chunk)
    {
    }

    /**
     * Which features the chunk marks, by feature key.
     *
     * @return array<string, string>  Feature key => `text` or `code`.
     *
     * @since  0.7.0
     */
    public function editors(): array
    {
        $editors           = [];
        $this->diagnostics = [];

        foreach ($this->chunk->nodes() as $id => $node) {
            $classifier = \is_array($node['classifier'] ?? null) ? $node['classifier'] : [];

            if (($classifier['language'] ?? null) !== self::LANGUAGE || ($classifier['key'] ?? null) !== self::ANNOTATION) {
                continue;
            }

            $id     = (string) $id;
            $parent = \is_string($node['parent'] ?? null) ? $node['parent'] : '';
            $value  = strtolower(trim($this->chunk->property($id, self::VALUE) ?? ''));

            if (!$this->annotatesProperty($parent)) {
                $this->diag(
                    'ANNOTATION_NOT_ON_PROPERTY',
                    'The editor annotation ' . $id . ' is not on a Property node, so there is nothing for it to say.'
                );

                continue;
            }

            if (!\in_array($value, [Feature::EDITOR_TEXT, Feature::EDITOR_CODE], true)) {
                // `line` is what no annotation means already; anything else is
                // a word this generator does not know, and one line is the
                // smaller mistake.
                if ($value !== Feature::EDITOR_LINE) {
                    $this->diag(
                        'EDITOR_UNKNOWN',
                        'The editor annotation ' . $id . ' says "' . $value . '"; only "text" and "code" are known,'
                        . ' so the property stays one line.'
                    );
                }

                continue;
            }

            $key = $this->chunk->property($parent, self::KEY) ?? '';

            if ($key !== '') {
                $editors[$key] = $value;
            }
        }

        return $editors;
    }

    /**
     * Write what the chunk marks into a language converted from it.
     *
     * The stored shape is a Joomla form's, so the value goes where the
     * Metalanguage form's own radio puts it: `editor` on the property group of
     * the feature. A feature the chunk marks that the language does not have
     * changes nothing.
     *
     * @param  array<string, mixed>  $stored  What `LionCoreLanguage::toStoredModel()` returned.
     *
     * @return array<string, mixed>
     *
     * @since  0.7.0
     */
    public function applyTo(array $stored): array
    {
        $editors = $this->editors();

        if ($editors === [] || !\is_array($stored['languageEntities'] ?? null)) {
            return $stored;
        }

        foreach ($stored['languageEntities'] as $entityName => $entity) {
            if (!\is_array($entity['classifier']['feature'] ?? null)) {
                continue;
            }

            foreach ($entity['classifier']['feature'] as $featureName => $feature) {
                $key = \is_array($feature) ? (string) ($feature['key'] ?? '') : '';

                if (!isset($editors[$key]) || ($feature['feature_type'] ?? '') !== Feature::PROPERTY) {
                    continue;
                }

                $stored['languageEntities'][$entityName]['classifier']['feature'][$featureName]['property']['editor']
                    = $editors[$key];
            }
        }

        return $stored;
    }

    /**
     * The annotation node a writer puts in a chunk, for one Property node.
     *
     * What JcbInOut's `LanguageBuilder` has to emit, spelled out once so that
     * the tests here and the writer there agree on more than a description.
     * The writer still lists the id in the Property node's `annotations` and
     * names `yepr-forms` in the chunk's `languages`.
     *
     * @return array<string, mixed>
     *
     * @since  0.7.0
     */
    public static function node(string $id, string $propertyNodeId, string $editor): array
    {
        $pointer = static fn (string $key): array => [
            'language' => self::LANGUAGE,
            'version'  => self::VERSION,
            'key'      => $key,
        ];

        return [
            'id'           => $id,
            'classifier'   => $pointer(self::ANNOTATION),
            'properties'   => [['property' => $pointer(self::VALUE), 'value' => $editor]],
            'containments' => [],
            'references'   => [],
            'annotations'  => [],
            'parent'       => $propertyNodeId,
        ];
    }

    /**
     * @return list<array{severity: string, code: string, message: string}>
     *
     * @since  0.7.0
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * Whether a node is a LionCore Property, which is all this annotates.
     */
    private function annotatesProperty(string $id): bool
    {
        if ($id === '' || !$this->chunk->has($id)) {
            return false;
        }

        $classifier = $this->chunk->nodes()[$id]['classifier'] ?? [];

        return \is_array($classifier)
            && ($classifier['language'] ?? null) === self::M3
            && ($classifier['key'] ?? null) === self::PROPERTY;
    }

    private function diag(string $code, string $message): void
    {
        $this->diagnostics[] = ['severity' => 'warning', 'code' => $code, 'message' => $message];
    }
}
