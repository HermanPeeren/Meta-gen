<?php

/**
 * @package     Metagen
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Install script for Meta-gen.
 *
 * Two jobs: refuse an environment the component cannot run in, and make sure
 * the shared library is there.
 *
 * The library carries the generation engine and the packages it needs, and is
 * shared with Exten-gen, Meta-gen and Plug-gen so a site holds one copy rather
 * than one per extension. Joomla has no way for a package manifest to declare a
 * dependency on it, so the package carries a copy and this installs it when the
 * site has none or has an older one. Regular Labs and Akeeba do the same, for
 * the same reason.
 *
 * The check runs on update as well as install: a site can be updated to a
 * version of Meta-gen that needs a newer library than the one already there.
 */
class Com_MetagenInstallerScript
{
    /**
     * The library this component cannot work without.
     */
    private const LIBRARY = 'yepr_gen';

    /**
     * The oldest library release that has everything this version calls.
     *
     * 0.11.0 for `Package\AncestryCheck` and `Metalanguage\Ancestry`, which 4.5
     * needs so a language can say what it derives from - and for the package
     * format that carries it, which this version of Meta-gen writes.
     *
     * `composer.json` has to ask for the same thing, and `ReleaseTest` is what
     * says the two agree. They did not once: this said 0.6.0 while composer
     * asked for ^0.8, which is a site that installs the component, accepts the
     * library it is handed, and cannot open the import screen.
     */
    private const LIBRARY_MINIMUM = '0.11.0';

    /**
     * The oldest Joomla this runs on.
     *
     * Six: the component's own code uses APIs older versions do not have, and
     * the generators it models are for Joomla 6 targets.
     *
     * @var string
     */
    private $minimumJoomlaVersion = '6.0';

    /**
     * The oldest PHP this runs on, which is Joomla 6's own minimum.
     *
     * @var string
     */
    private $minimumPHPVersion = '8.3';

    /**
     * Refuse an environment that cannot run this.
     *
     * @param   string            $type    install, update, discover_install or uninstall
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean  False stops the installation.
     */
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            $this->say(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion), 'error');

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            $this->say(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion), 'error');

            return false;
        }

        return true;
    }

    /**
     * Put the shared library in place if it is missing or too old.
     *
     * @param   string            $type    install, update, discover_install or uninstall
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean  True, always: a failure here is reported rather than fatal.
     */
    public function postflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $installed = $this->installedLibraryVersion();

        if ($installed !== null && version_compare($installed, self::LIBRARY_MINIMUM, '>=')) {
            return true;
        }

        $directory = $parent->getParent()->getPath('source') . '/library';
        $archives  = is_dir($directory) ? (glob($directory . '/*.zip') ?: []) : [];

        if ($archives === []) {
            $this->say('The Yepr Gen library is not in this package, so it could not be installed.', 'warning');

            return true;
        }

        // The package carries the library as a zip, and Installer::install()
        // wants a directory with a manifest in it - handed the zip it reports
        // "Can't find XML setup file", which is true and unhelpful. Unpacking
        // first is what Joomla does everywhere it installs from an archive.
        $unpacked = InstallerHelper::unpack((string) $archives[0], true);

        if ($unpacked === false) {
            $this->say('The Yepr Gen library archive could not be unpacked.', 'warning');

            return true;
        }

        $installer = new Installer();
        $installer->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));

        // Not named $installed: that already holds the version this site had,
        // and the message below distinguishes an install from an update by it.
        $success = $installer->install($unpacked['extractdir']);

        // Whether it worked or not, the unpacked copy is temporary.
        InstallerHelper::cleanupInstall((string) $archives[0], $unpacked['extractdir']);

        if ($success) {
            $this->say(
                $installed === null
                    ? 'The Yepr Gen library was installed.'
                    : 'The Yepr Gen library was updated from ' . $installed . '.',
                'message'
            );

            return true;
        }

        // Not fatal. The component is installed; it simply will not generate
        // until the library is there, and saying so is more use than rolling
        // back everything the user just did.
        $this->say('The Yepr Gen library could not be installed. Meta-gen needs it in order to generate.', 'warning');

        return true;
    }

    /**
     * The version of the shared library this site has, or null when it has none.
     *
     * @return  string|null
     */
    private function installedLibraryVersion()
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $element = self::LIBRARY;

        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('library'))
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':element', $element, ParameterType::STRING);

        $db->setQuery($query);

        $manifest = $db->loadResult();

        if (!is_string($manifest) || $manifest === '') {
            return null;
        }

        $decoded = json_decode($manifest, true);

        return is_array($decoded) && isset($decoded['version']) ? (string) $decoded['version'] : null;
    }

    /**
     * Tell the user something, if there is anybody to tell.
     *
     * @param   string  $message  What happened.
     * @param   string  $type     message, warning or error.
     *
     * @return  void
     */
    private function say($message, $type)
    {
        $app = Factory::getApplication();

        if ($app) {
            $app->enqueueMessage($message, $type);
        }
    }

    /**
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean
     */
    public function install($parent): bool
    {
        return true;
    }

    /**
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean
     */
    public function update($parent): bool
    {
        return true;
    }

    /**
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean
     */
    public function uninstall($parent): bool
    {
        // The library is deliberately left in place. The other extensions in
        // the family share it, and removing something they depend on because
        // this one was uninstalled is how a working site breaks.
        return true;
    }
}
