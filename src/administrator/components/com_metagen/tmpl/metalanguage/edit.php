<?php
/**
 * @package     Metagen

 * @subpackage  Metagen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');

// <yepr-reference>, and the reference index the view put in the page.
//
// The script is the shared library's: one copy for the three components that
// edit models. A library's asset file is not registered automatically the way
// the active component's is, so it is asked for by name here.
//
// The uri inside that asset file is `lib_yepr_gen/reference.js` and not
// `lib_yepr_gen/js/reference.js`, which is the whole of why this took a while:
// Joomla's relative resolution *inserts* the `js/` folder, so the longer
// spelling is looked for at media/lib_yepr_gen/js/js/reference.js, is not
// found, and the asset is dropped without a word. Nothing raises, no tag is
// emitted, and every dropdown keeps whatever the server rendered - which is
// the same silent failure 3.1 spent a step on, arriving by a new route.
$wa = $this->getDocument()->getWebAssetManager();

$wa->getRegistry()->addExtensionRegistryFile('lib_yepr_gen');
$wa->useScript('lib_yepr_gen.reference');

$app = Factory::getApplication();
$input = $app->getInput();

$assoc = Associations::isEnabled();

$this->ignore_fieldsets = array('item_associations');
$this->useCoreUI = true;

// In case of modal
$isModal = $input->get('layout') == 'modal' ? true : false;
$layout  = $isModal ? 'modal' : 'edit';
$tmpl    = $isModal || $input->get('tmpl', '', 'cmd') === 'component' ? '&tmpl=component' : '';
?>
<form action="<?php echo Route::_('index.php?option=com_metagen&view=metalanguage&layout=' . $layout . $tmpl . '&id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="metalanguage-form" class="form-validate">

    <?php echo $this->getForm()->renderField('name'); ?>

	<div>

		<?php if (($this->item->id)>0): ?>
            <div class="row">
                <div class="col-md-12 btns">
                    <a class="btn btn-info"  data-bs-toggle="modal"  href="#MetalanguageModal">
						<?php echo Text::_('COM_METAGEN_BUTTON_METALANGUAGE_DIAGRAM'); ?>
                    </a>
                    <p>&nbsp;</p>
                </div>
            </div>
		<?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="row">
                    <div class="col-md-12">
						<?php echo $this->getForm()->renderField('languageEntities'); ?>
                    </div>
                </div>
            </div>
        </div>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>

<?php
if (($this->item->id)>0)
{
    // Make a forms-diagram
	echo HTMLHelper::_(
		'bootstrap.renderModal',
		'MetalanguageModal',
		array(
			'title'  => Text::_('COM_METAGEN_BUTTON_METALANGUAGE_DIAGRAM'),
			'url' => Uri::root() . "administrator/index.php?option=com_metagen&view=FormsDiagram&tmpl=component&metalanguage_id=" .  $this->item->id,
			'height' => "700",
			'width' => "700"
		)
    ); // todo: adjust height and width to screen
}
 ?>
