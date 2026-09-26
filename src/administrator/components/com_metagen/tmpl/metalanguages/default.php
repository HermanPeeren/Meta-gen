<?php
/**
 * @package     Metagen

 * @subpackage  Metagen component
 * @version     0.9.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

// The generate button has to tell the one modal which language it means,
// before Bootstrap opens it. The library's, because Exten-gen's projects list
// needs the same twelve lines and two copies of one rule is how three defects
// got in.
//
// A library carries media but does not get its asset file registered the way
// the active component does, so it is asked for by name first - the same two
// lines the metalanguage edit screen uses for the reference element.
$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('lib_yepr_gen');
$wa->useScript('lib_yepr_gen.generation-modal');

$canChange  = true;
$assoc = Associations::isEnabled();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'a.ordering';

if ($saveOrder && !empty($this->items))
{
	$saveOrderingUrl = 'index.php?option=com_metagen&task=metalanguages.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
}
?>
<form action="<?php echo Route::_('index.php?option=com_metagen&view=metalanguages'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row">
		<?php if (!empty($this->sidebar)) : ?>
			<div id="j-sidebar-container" class="col-md-2">
				<?php echo $this->sidebar; ?>
			</div>
		<?php endif; ?>
		<div class="<?php if (!empty($this->sidebar)) {echo 'col-md-10'; } else { echo 'col-md-12'; } ?>">
			<div id="j-main-container" class="j-main-container">
				<?php
				/*
				 * A path on this site rather than an upload. JcbInOut writes its
				 * language to disk here, so the two components meet on the
				 * filesystem and there is no temporary file, no MIME guessing
				 * and no second copy of a language to keep in step.
				 */
				$suggested = \Yepr\Component\Metagen\Administrator\Model\LionwebModel::JCBINOUT;
				?>
				<details class="mb-3" <?php echo is_file(JPATH_ROOT . '/' . $suggested) ? 'open' : ''; ?>>
					<summary class="h5"><?php echo Text::_('COM_METAGEN_LIONWEB_IMPORT'); ?></summary>
					<p class="mt-2"><?php echo Text::_('COM_METAGEN_LIONWEB_INTRO'); ?></p>
					<label class="form-label" for="chunk"><?php echo Text::_('COM_METAGEN_LIONWEB_PATH'); ?></label>
					<input class="form-control" type="text" id="chunk" name="chunk"
						value="<?php echo is_file(JPATH_ROOT . '/' . $suggested)
							? htmlspecialchars($suggested, ENT_QUOTES, 'UTF-8') : ''; ?>"
						placeholder="<?php echo htmlspecialchars($suggested, ENT_QUOTES, 'UTF-8'); ?>">
					<small class="form-text"><?php echo Text::_('COM_METAGEN_LIONWEB_PATH_HELP'); ?></small>
				</details>

				<?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-warning">
						<?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
					<table class="table" id="metagenMetalanguages">
						<caption id="captionTable" class="sr-only">
							<?php echo Text::_('COM_METAGEN_METALANGUAGE_TABLE_CAPTION'); ?>, <?php echo Text::_('JGLOBAL_SORTED_BY'); ?>
						</caption>
						<thead>
							<tr>
								<th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
									<?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-menu-2'); ?>
								</th>
								<td style="width:1%" class="text-center">
									<?php echo HTMLHelper::_('grid.checkall'); ?>
								</td>
								<th scope="col" style="width:1%" class="text-center d-none d-md-table-cell">
									<?php echo HTMLHelper::_('searchtools.sort', 'COM_METAGEN_TABLE_TABLEHEAD_METALANGUAGE_NAME', 'a.name', $listDirn, $listOrder); ?>
								</th>
								<th scope="col" style="width:10%" class="d-none d-md-table-cell">
									<?php echo Text::_('COM_METAGEN_TABLE_TABLEHEAD_GENERATION'); ?>

								</th>
								<th scope="col">
									<?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
								</th>
							</tr>
						</thead>
						<tbody>
						<?php
						$n = count($this->items);
						foreach ($this->items as $i => $item) :
							?>
							<tr class="row<?php echo $i % 2; ?>">
								<td class="order text-center d-none d-md-table-cell">
									<?php
									$iconClass = '';
									if (!$canChange)
									{
										$iconClass = ' inactive';
									}
									elseif (!$saveOrder)
									{
										$iconClass = ' inactive tip-top hasTooltip" title="' . HTMLHelper::_('tooltipText', 'JORDERINGDISABLED');
									}
									?>
									<span class="sortable-handler<?php echo $iconClass; ?>">
										<span class="icon-menu" aria-hidden="true"></span>
									</span>
									<?php if ($canChange && $saveOrder) : ?>
										<input type="text" style="display:none" name="order[]" size="5"
											value="<?php echo $item->ordering; ?>" class="width-20 text-area-order">
									<?php endif; ?>
								</td>
								<td class="text-center">
									<?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
								</td>
								<th scope="row" class="has-context">
									<?php if ($item->checked_out) : ?>
										<?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'metalanguages.', true); ?>
									<?php endif; ?>
									<div>
										<?php echo $this->escape($item->name); ?>
									</div>
									<?php $editIcon = '<span class="fa fa-pencil-square mr-2" aria-hidden="true"></span>'; ?>
									<a class="hasTooltip" href="<?php echo Route::_('index.php?option=com_metagen&task=metalanguage.edit&id=' . (int) $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape(addslashes($item->name)); ?>">
										<?php echo $editIcon; ?><?php echo $this->escape($item->name); ?></a>
									<div class="small">
										<?php echo Text::_('JCATEGORY') . ': ' . $this->escape($item->category_title); ?>
									</div>

								</th>
                                <td class="text-center btns d-none d-md-table-cell">
                                    <a class="btn btn-info dynbutton"  data-bs-toggle="modal"  href="#generationModal" data-href="<?php echo Uri::root(); ?>administrator/index.php?option=com_metagen&view=generateForms&tmpl=component&metalanguage_id=<?php echo $item->id; ?>">
										<?php echo Text::_('COM_METAGEN_BUTTON_GENERATE'); ?>
                                    </a>
                                    <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_metagen&task=metalanguage.export&id=' . (int) $item->id . '&' . Session::getFormToken() . '=1'); ?>">
										<?php echo Text::_('COM_METAGEN_BUTTON_EXPORT'); ?>
                                    </a>
                                </td>
								<td class="d-none d-md-table-cell">
									<?php echo $item->id; ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<?php //echo $this->pagination->getListMetagenter(); ?>

					<?php echo HTMLHelper::_(
						'bootstrap.renderModal',
						'collapseModal',
						array(
							'title'  => Text::_('COM_METAGEN_BATCH_OPTIONS'),
							'footer' => $this->loadTemplate('batch_footer'),
						),
						$this->loadTemplate('batch_body')
					); ?>

					<?php echo HTMLHelper::_(
						'bootstrap.renderModal',
						'generationModal',
						array(
							'title'  => Text::_('COM_METAGEN_BUTTON_GENERATE'),
							'url' => "https://yepr.nl",
                            'height' => "500"
						)
					); ?><!--todo: some empty page for the generator, maybe with a spinner or animation... -->

				<?php endif; ?>
				<input type="hidden" name="task" value="">
				<input type="hidden" name="boxchecked" value="0">
				<?php echo HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>
