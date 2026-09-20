<?php

/**
 * @package     Metagen
 * @subpackage  Extension
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Metagen\Administrator\Extension;

use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Psr\Container\ContainerInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The component.
 *
 * The guard above is `_JEXEC`. Exten-gen's was `JPATH_PLATFORM`, a constant
 * Joomla 6 removed, and the result was every view returning HTTP 200 with a
 * zero-byte body and nothing in any log - because `die` is not an error. Once
 * is enough to learn from.
 *
 * @since  0.1.0
 */
class MetagenComponent extends MVCComponent implements BootableExtensionInterface
{
	/**
	 * Where setRegistry() comes from.
	 *
	 * The service provider calls it, and MVCComponent does not have it - so
	 * without this the component builds fine right up until something asks
	 * the container for it, and then dies with "undefined method". Nothing
	 * static sees it, because the call is in a closure in a file Joomla
	 * includes at run time.
	 */
	use HTMLRegistryAwareTrait;

	/**
	 * Booting the extension.
	 *
	 * Nothing to do yet. The method exists because the interface is what says
	 * "this component gets a chance to set itself up", and adding it later
	 * means changing the class declaration rather than filling in a body.
	 *
	 * @param   ContainerInterface  $container  The container.
	 *
	 * @return  void
	 *
	 * @since   0.1.0
	 */
	public function boot(ContainerInterface $container)
	{
	}
}
