<?php

/**
 * @package     Metagen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Metagen\Administrator\Generator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One feature of a classifier: a property, or a link to another classifier.
 *
 * LionCore M3 calls the two kinds Property and Link, and a Link is either a
 * Containment - the target is a child, stored inside this one - or a Reference,
 * which stores the target's key. That distinction is the whole reason a forms
 * generator can exist: a containment becomes a subform and a reference becomes
 * a dropdown, and nothing else about the two differs.
 *
 * A plain readonly object rather than the stored stdClass, because the stored
 * shape is a Joomla form's shape: `is_optional` is absent when its checkbox was
 * never ticked, `link` is nested one level down, and a feature that is a
 * property still carries an empty `link` group beside it. Reading that
 * correctly is a job with rules in it, and it is done once, here.
 *
 * @since  1.2.0
 */
final class Feature
{
    /**
     * A feature holding a value of some datatype.
     *
     * @since  1.2.0
     */
    public const PROPERTY = 'Property';

    /**
     * A feature pointing at another classifier.
     *
     * @since  1.2.0
     */
    public const LINK = 'Link';

    /**
     * A link whose target is a child of this one.
     *
     * @since  1.2.0
     */
    public const CONTAINMENT = 'Containment';

    /**
     * A link whose target is pointed at by key.
     *
     * @since  1.2.0
     */
    public const REFERENCE = 'Reference';

    /**
     * @param  string   $name        The feature's name, which is the form field's name.
     * @param  string   $key         Its LionWeb key.
     * @param  bool     $optional    Whether it may be left empty.
     * @param  string   $kind        `Property` or `Link`.
     * @param  string   $linkKind    `Containment` or `Reference`, for a link.
     * @param  bool     $multiple    Whether a link holds more than one target.
     * @param  string   $typeKey     The key of the datatype or classifier it points at.
     *
     * @since  1.2.0
     */
    private function __construct(
        public readonly string $name,
        public readonly string $key,
        public readonly bool $optional,
        public readonly string $kind,
        public readonly string $linkKind,
        public readonly bool $multiple,
        public readonly string $typeKey
    ) {
    }

    /**
     * Read one feature out of a stored classifier's `feature` group.
     *
     * @since  1.2.0
     */
    public static function fromNode(object $node): self
    {
        $kind = self::text($node, 'feature_type');
        $link = \is_object($node->link ?? null) ? $node->link : null;

        // A property's type sits on `property`, a link's on `link`. Both are
        // called `type`, which is the one thing about the two that is the same.
        $property = \is_object($node->property ?? null) ? $node->property : null;
        $typeFrom = $kind === self::LINK ? $link : $property;

        return new self(
            self::text($node, 'name'),
            self::text($node, 'key'),
            // A checkbox that was never ticked is absent rather than "0", so
            // the question is whether it is there and true, not what it holds.
            self::flag($node, 'is_optional'),
            $kind,
            $link === null ? '' : self::text($link, 'link_type'),
            $link !== null && self::flag($link, 'is_multiple'),
            $typeFrom === null ? '' : self::text($typeFrom, 'type')
        );
    }

    /**
     * Whether this feature holds a value rather than pointing at a classifier.
     *
     * @since  1.2.0
     */
    public function isProperty(): bool
    {
        return $this->kind === self::PROPERTY;
    }

    /**
     * Whether this feature's target is a child, stored inside this object.
     *
     * @since  1.2.0
     */
    public function isContainment(): bool
    {
        return $this->kind === self::LINK && $this->linkKind === self::CONTAINMENT;
    }

    /**
     * Whether this feature points at another classifier by key.
     *
     * @since  1.2.0
     */
    public function isReference(): bool
    {
        return $this->kind === self::LINK && $this->linkKind === self::REFERENCE;
    }

    /**
     * @since  1.2.0
     */
    private static function text(object $node, string $key): string
    {
        return property_exists($node, $key) && is_scalar($node->{$key}) ? (string) $node->{$key} : '';
    }

    /**
     * @since  1.2.0
     */
    private static function flag(object $node, string $key): bool
    {
        return property_exists($node, $key) && (bool) $node->{$key};
    }
}
