<?php

/**
 * @package     Metagen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Metagen\Administrator\Generator\Target;

use Yepr\Component\Metagen\Administrator\Generator\Meta\Forms;
use Yepr\Component\Metagen\Administrator\Generator\Meta\PackageFiles;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ValidatorInterface;
use Yepr\Gen\Core\Target\TargetInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The forms a modelled language is edited with.
 *
 * A target is a structure metamodel plus emitters plus a template set, and 2.3
 * made the point that a generator happens to be describable that way, so it is
 * one. The same holds here: a set of Joomla forms is a structure, the DOM
 * builder is its emitter, and it needs no template set at all - which is why
 * this target takes no template root and constructs no renderer.
 *
 * It sits beside `Joomla6Target` rather than inside it because the two consume
 * different models. `Joomla6Target` turns a project into a component;
 * this turns a language into the forms that project was written with.
 *
 * @since  1.2.0
 */
final class MetaFormsTarget implements TargetInterface
{
    /**
     * The stable identifier for this target.
     *
     * @since  1.2.0
     */
    public function id(): string
    {
        return 'metaforms';
    }

    /**
     * How this target is named to somebody choosing one.
     *
     * @since  1.2.0
     */
    public function label(): string
    {
        return 'Joomla forms for a modelled language';
    }

    /**
     * What a concept model must satisfy before this target will generate.
     *
     * Nothing, for now. What would be refused here - a language whose root
     * reaches nothing, a reference pointing at a classifier no model can hold -
     * is reported by the generator in its log instead, because every one of
     * them is an ordinary state for a model to be in while somebody is still
     * writing it. A validator that refused those would refuse to generate the
     * forms somebody needs in order to finish.
     *
     * @since  1.2.0
     */
    public function validator(): ?ValidatorInterface
    {
        return null;
    }

    /**
     * The generators, in the order they run.
     *
     * The order matters, which is not true of every target: `PackageFiles`
     * writes a manifest hashing everything already in the collection, so it
     * has to be last. First, it would describe an empty package and nothing
     * would complain - every hash in the manifest would be correct, because
     * there would be none of them.
     *
     * @return GeneratorInterface[]
     *
     * @since  1.2.0
     */
    public function generators(): array
    {
        return [new Forms(), new PackageFiles()];
    }
}
