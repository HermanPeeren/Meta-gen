<?php

/**
 * @package     Metagen
 * @subpackage  Metagen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Metagen\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Forms;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Component\Metagen\Administrator\Generator\Target\MetaFormsTarget;
use Yepr\Component\Metagen\Administrator\Package\MetalanguagePackage;
use Yepr\Component\Metagen\Administrator\Repository\MetalanguageRepository;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Target\Target;

/**
 * Generating the forms of a modelled language.
 *
 * The other half of what `GenerateModel` does for projects: that one turns a
 * project into a component, this turns a language into the forms a project in
 * that language is edited with. Both run the shared `Pipeline` over a target
 * and write the whole file set afterwards, so a run that fails part way
 * through leaves nothing behind.
 *
 * **What was here before could not have run.** It called a `Metalanguages`
 * generator whose live code opened `foreach ($metalanguage->datamodel as
 * $entity)` - a metalanguage has `languageEntities` and no `datamodel` at all -
 * so the first statement of the only thing this model did would have thrown.
 * Nothing had noticed because nothing could reach this model either: the link
 * said `view=generateform`, the directory was `GenerateProjectForm`, and the
 * view class inside it declared `View\GenerateForm`. Three names for one
 * screen, no two of them the same.
 *
 * @since  1.2.0
 */
class GenerateFormsModel extends AdminModel
{
    /**
     * A log of what the generators produced.
     *
     * @var array
     */
    public array $log = [];

    /**
     * The (internal) id of the metalanguage to generate from.
     *
     * @var int
     */
    protected int $metalanguageId;

    /**
     * Set the metalanguage id.
     *
     * @param   int  $metalanguageId  The row to generate from.
     *
     * @return  void
     */
    public function setMetalanguageId(int $metalanguageId): void
    {
        $this->metalanguageId = $metalanguageId;
    }

    /**
     * Generate the forms of this language, and write them out.
     *
     * @return  void
     */
    public function generate(): void
    {
        $model = $this->loadConceptModel();

        $this->write($this->package($model), $model);
    }

    /**
     * The package for this language, in memory.
     *
     * Separated from `generate()` at 3.3 because exporting and generating are
     * the same run with a different destination - one writes the archive under
     * the component and hands back a log, the other hands the bytes to a
     * browser. Two code paths producing "the package" would be two package
     * formats the day one of them changed.
     *
     * @param   ConceptModel|null  $model  The language, when the caller has already read it.
     *
     * @return  FileCollection
     */
    public function package(?ConceptModel $model = null): FileCollection
    {
        $model ??= $this->loadConceptModel();

        $target     = new MetaFormsTarget();
        $generators = $target->generators();

        $files = (new Pipeline())->run($model, new Target(
            $target->id(),
            $target->label(),
            $target->validator(),
            ...$generators
        ));

        foreach ($generators as $generator) {
            // Keeping a log is this project's habit rather than something the
            // shared GeneratorInterface promises, so it is asked for where it
            // exists instead of widening that interface for it.
            if ($generator instanceof Forms) {
                $this->log = array_merge($this->log, $generator->log());
            }
        }

        return $files;
    }

    /**
     * What a downloaded package is called.
     *
     * The language and its version, which is what identifies one - 3.4 has a
     * project record both - so two exports of one language are two files in a
     * downloads folder rather than one overwriting the other.
     *
     * @param   ConceptModel  $model  The language.
     *
     * @return  string
     */
    public function packageName(ConceptModel $model): string
    {
        return MetalanguagePackage::slug($model->name())
            . '-' . MetalanguagePackage::versionSlug($model->version()) . '.zip';
    }

    /**
     * The stored metalanguage, as a language, for a caller that needs it too.
     *
     * @return  ConceptModel
     */
    public function conceptModel(): ConceptModel
    {
        return $this->loadConceptModel();
    }

    /**
     * Put the generated forms on disk, beside the generated components.
     *
     * **Not into the component's own `forms/` directory**, which is where the
     * old generator wrote them, one `mkdir` and `save()` at a time as it went.
     * Generating forms then edited the running component from inside itself,
     * and a run that failed half way left a language half replaced. Installing
     * a generated language is a step of its own - 3.4 - and until there is one,
     * the output is a package and a tree somebody can read, exactly as a
     * generated component is.
     *
     * @param   FileCollection  $files  What was generated.
     * @param   ConceptModel    $model  The language it came from.
     *
     * @return  void
     */
    private function write(FileCollection $files, ConceptModel $model): void
    {
        $name      = MetalanguagePackage::slug($model->name());
        $version   = MetalanguagePackage::versionSlug($model->version());
        $generated = JPATH_ROOT . '/administrator/components/com_metagen/generated/metalanguages/'
            . $name . '/' . $version;

        // The tree, not its parent: `ZipWriter::writeToDirectory()` refuses a
        // root that is not there rather than creating one, which is what keeps
        // a mistyped path from scattering a file set across the filesystem.
        $tree = $generated . '/tree';

        if (!is_dir($tree) && !mkdir($tree, 0755, true) && !is_dir($tree)) {
            throw new \RuntimeException('Cannot create ' . $tree);
        }

        $writer  = new ZipWriter();
        $archive = $generated . '/' . $this->packageName($model);

        $writer->write($files, $archive);
        $writer->writeToDirectory($files, $tree);

        $this->log[] = '&nbsp;';
        $this->log[] = '<b>' . count($files) . ' files</b>';
        $this->log[] = 'package: ' . $archive;
        $this->log[] = 'unpacked: ' . $tree;
    }

    /**
     * The stored metalanguage, as a language.
     *
     * @return  ConceptModel
     */
    private function loadConceptModel(): ConceptModel
    {
        // The id is set on this model by the view, not taken from the request:
        // the model is told which record it is working on.
        $id  = (int) $this->metalanguageId;
        $row = (new MetalanguageRepository($this->getDatabase()))->findRaw($id);

        if ($row === null) {
            throw new \RuntimeException(sprintf('Cannot read project form %d.', $id));
        }

        return ConceptModel::fromObject($row);
    }

    /**
     * The table this model reads, which is the Metalanguage table.
     *
     * Without it, AdminModel asks for a table named after the model - there is
     * no GenerateFormsTable and there is no reason for one.
     *
     * @param   string  $name     The table name.
     * @param   string  $prefix   The class prefix.
     * @param   array   $options  Configuration for the table.
     *
     * @return  \Joomla\CMS\Table\Table
     */
    public function getTable($name = 'Metalanguage', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * This model has no form of its own; it is a button, not a screen to fill in.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  Whether the form loads its own data.
     *
     * @return  \Joomla\CMS\Form\Form|boolean
     */
    public function getForm($data = [], $loadData = true)
    {
        return false;
    }
}
