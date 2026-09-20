<?php

/**
 * @package     Metagen
 * @subpackage  Services
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Component\Metagen\Administrator\Extension\MetagenComponent;

/**
 * The Meta-gen service provider.
 *
 * No category factory, no associations, no router. Exten-gen registers all
 * three and uses none of them; a provider that registers what a component
 * actually has is one you can read to find out what it has.
 */
return new class () implements ServiceProviderInterface {
	/**
	 * Register the component with the container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 */
	public function register(Container $container)
	{
		$container->registerServiceProvider(new MVCFactory('\Yepr\Component\Metagen'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('\Yepr\Component\Metagen'));

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new MetagenComponent($container->get(ComponentDispatcherFactoryInterface::class));

				$component->setRegistry($container->get(Registry::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));

				return $component;
			}
		);
	}
};
