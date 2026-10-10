<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Forms;
use Yepr\Component\Metagen\Administrator\Generator\Meta\LanguageStructure;
use Yepr\Component\Metagen\Administrator\Generator\Model\Classifier;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Component\Metagen\Administrator\Generator\Target\MetaFormsTarget;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Package\AncestryCheck;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Package\PackageManifest;
use Yepr\Gen\Core\Package\PackageReader;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Target\Target;

/**
 * LedgerLang: a language that derives from ER1 and adds accounting to it.
 *
 * The demo language for the JWC talk, written by `tools/make-ledgerlang.php`
 * from `er1-full.json`. What is pinned here is what the demo depends on: that
 * Exten-gen's import will accept it as a child of the ER1 it ships, that a
 * project in it opens on ER1's three tabs plus three of its own, that a posting
 * line's role dropdown can find the roles, and that the demo model is a model
 * of *this* language rather than of something that looks like it.
 *
 * @since  0.5.1
 */
final class LedgerLangTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $rubbish = [];

    protected function tearDown(): void
    {
        foreach ($this->rubbish as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private static function language(string $name): ConceptModel
    {
        return ConceptModel::fromJson(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/' . $name . '.json')
        );
    }

    /**
     * The demo project, decoded as the screens store it.
     *
     * @return array<string, mixed>
     */
    private static function demo(): array
    {
        return json_decode(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/projects/balloonledger.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    private function package(ConceptModel $model): FileCollection
    {
        $target = new MetaFormsTarget();

        return (new Pipeline())->run($model, new Target(
            $target->id(),
            $target->label(),
            $target->validator(),
            ...$target->generators()
        ));
    }

    private function manifest(ConceptModel $model): PackageManifest
    {
        $path = sys_get_temp_dir() . '/metagen-ledgerlang-' . bin2hex(random_bytes(6)) . '.zip';

        $this->rubbish[] = $path;

        (new ZipWriter())->write($this->package($model), $path);

        return PackageReader::fromZip($path)->manifest();
    }

    /**
     * It derives from the ER1 this repository has, at the version it has.
     *
     * When ER1 moves, this is the test that says so: LedgerLang is built from
     * ER1, so the fix is running the tool again rather than editing either.
     */
    public function testItDerivesFromTheEr1InThisRepository(): void
    {
        $this->assertSame(
            [['key' => 'ER1', 'version' => self::language('er1-full')->version()]],
            self::language('ledgerlang')->dependsOn(),
            'ER1 has moved: run php tools/make-ledgerlang.php'
        );
    }

    /**
     * The guard Exten-gen runs at import accepts it.
     *
     * Run on the manifests both packages actually carry, because that is what
     * the import compares - concepts and their effective features.
     */
    public function testTheAncestryGuardAcceptsIt(): void
    {
        $parent = $this->manifest(self::language('er1-full'));
        $child  = $this->manifest(self::language('ledgerlang'));

        $this->assertSame([], AncestryCheck::problems($parent->concepts, $child->concepts, 'ER1'));
        $this->assertSame([['key' => 'ER1', 'version' => $parent->version]], $child->dependsOn);
    }

    /**
     * And it would refuse one that renamed something, so the pass above means something.
     */
    public function testTheGuardWouldRefuseARename(): void
    {
        $parent = $this->manifest(self::language('er1-full'));
        $child  = $this->manifest(self::language('ledgerlang'));

        $renamed = array_map(
            static fn (array $concept): array => $concept['name'] === 'Entity' ? ['name' => 'Thing'] + $concept : $concept,
            $child->concepts
        );

        $this->assertNotSame([], AncestryCheck::problems($parent->concepts, $renamed, 'ER1'));
    }

    /**
     * What it adds is there, and every one of them gets a form with nothing reported missing.
     */
    public function testItGeneratesAFormPerClassifierWithNothingMissing(): void
    {
        $model     = self::language('ledgerlang');
        $files     = new FileCollection();
        $generator = new Forms();

        $generator->generate($model, $files);

        foreach (['AccountRole', 'PostingRule', 'PostingLine', 'LocalisationPack', 'StateMachine', 'State', 'Transition'] as $added) {
            $this->assertTrue($files->has(MetalanguagePackage::formPath($added)), 'no form for ' . $added);
        }

        $forms = array_filter(
            $files->paths(),
            static fn (string $path): bool => str_starts_with($path, MetalanguagePackage::FORMS) && str_ends_with($path, '.xml')
        );

        $this->assertCount(\count($model->classifiers()), $forms);

        $warnings = array_filter($generator->log(), static fn (string $line): bool => str_starts_with($line, 'warning:'));

        $this->assertSame([], array_values($warnings), implode("\n  ", $warnings));
    }

    /**
     * A project opens on ER1's three tabs, then LedgerLang's three.
     *
     * Exten-gen makes a tab of each named fieldset of the root form, in order,
     * so the order here is the order on the screen.
     */
    public function testAProjectOpensOnEr1sTabsAndThenItsOwn(): void
    {
        $files = new FileCollection();

        (new Forms())->generate(self::language('ledgerlang'), $files);

        $project = simplexml_load_string($files->get(MetalanguagePackage::formPath('Project')));

        $this->assertNotFalse($project);

        $named = [];

        foreach ($project->xpath('/form/fieldset[@name]') ?: [] as $fieldset) {
            $named[(string) $fieldset['name']] = array_map(
                static fn (\SimpleXMLElement $field): string => (string) $field['name'],
                $fieldset->xpath('field') ?: []
            );
        }

        $this->assertSame([
            'entities'      => ['datamodel'],
            'pages'         => ['pages'],
            'extensions'    => ['extensions'],
            'account_roles' => ['account_roles'],
            'posting_rules' => ['posting_rules'],
            'localisation'  => ['localisation_packs'],
        ], $named);
    }

    /**
     * A posting line's role dropdown offers the roles, keyed by the role's own key.
     *
     * And a transition's states are found where they are stored, which is the
     * reason `state_machine` repeats: a single containment is not a step in a
     * reference path, and the states would be looked for one level too high.
     */
    public function testTheReferenceDropdownsFindWhatTheyPointAt(): void
    {
        $table = json_decode(
            $this->package(self::language('ledgerlang'))->get(MetalanguagePackage::REFERENCES),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $this->assertSame(['account_roles'], $table['AccountRole']['path']);
        $this->assertSame('key', $table['AccountRole']['idKey']);
        $this->assertSame('name', $table['AccountRole']['nameKey']);

        $this->assertSame(['datamodel', 'state_machine', 'states'], $table['State']['path']);
        $this->assertSame('key', $table['State']['idKey']);
    }

    /**
     * The demo project holds only what the language declares, wherever it is.
     *
     * Walked from the root through the language, so a misspelt key - a
     * `posting_rule` for `posting_rules` - is a failure here rather than a tab
     * that opens empty on stage.
     */
    public function testTheDemoModelIsAModelOfThisLanguage(): void
    {
        $model     = self::language('ledgerlang');
        $structure = LanguageStructure::of($model);
        $root      = $model->root();

        $this->assertNotNull($root);
        $this->assertSame([], $this->strays($structure, $root, self::demo(), 'project', ['name']));
    }

    /**
     * The posting rules the talk shows, with lines that point at declared roles.
     */
    public function testTheDemoHasTheBalloonPostingRules(): void
    {
        $demo  = self::demo();
        $roles = array_column(array_values($demo['account_roles']), 'key');
        $rules = array_column(array_values($demo['posting_rules']), null, 'event_name');

        $this->assertSame(['VoucherSold', 'FlightFlown', 'VoucherExpired', 'FlightCancelledDueToWind'], array_keys($rules));
        $this->assertSame([], $rules['FlightCancelledDueToWind']['lines'], 'wind posts nothing: the voucher stays a liability');

        foreach ($rules as $event => $rule) {
            $debit  = 0;
            $credit = 0;

            foreach ($rule['lines'] as $line) {
                $this->assertContains($line['role'], $roles, $event . ' posts to a role nobody declared');

                $line['side'] === 'debit' ? $debit++ : $credit++;
            }

            $this->assertSame($rule['lines'] === [], $debit === 0 && $credit === 0);
            $this->assertSame($debit > 0, $credit > 0, $event . ' has a debit without a credit, or the other way round');
        }

        // The rule added live on stage needs these two to be there already.
        $this->assertContains('expense.landowner', $roles);
        $this->assertContains('cash', $roles);
    }

    /**
     * The ER1 half is the six entities the fit analysis says ER1 can express.
     */
    public function testTheDemoHasTheEr1Entities(): void
    {
        $this->assertSame(
            ['Account', 'Journal', 'Party', 'TaxCode', 'JournalEntry', 'EntryLine'],
            array_column(array_values(self::demo()['datamodel']), 'entity_name')
        );
    }

    /**
     * Every key in a stored row that the row's concept does not declare.
     *
     * @param  array<string, mixed>  $row
     * @param  string[]              $extra  Keys that are not the language's: the project's own chrome.
     *
     * @return string[]
     */
    private function strays(LanguageStructure $structure, Classifier $concept, array $row, string $at, array $extra = []): array
    {
        $model    = $structure->model();
        $features = [];

        foreach ($model->featuresOf($concept) as $feature) {
            $features[$feature->name] = $feature;
        }

        $subtypes = [];

        foreach ($structure->subtypesOf($concept) as $subtype) {
            $subtypes[$structure->groupOf($subtype)] = $subtype;
        }

        $strays = [];

        foreach ($row as $key => $value) {
            $here = $at . '.' . $key;

            if (\in_array($key, $extra, true)) {
                continue;
            }

            if ($subtypes !== [] && $key === $structure->discriminatorOf($concept)) {
                $this->assertContains($value, array_map(static fn (Classifier $c): string => $c->name, $subtypes), $here);

                continue;
            }

            if (isset($subtypes[$key])) {
                $strays = array_merge($strays, $this->strays($structure, $subtypes[$key], $value, $here));

                continue;
            }

            $feature = $features[$key] ?? null;

            if ($feature === null) {
                $strays[] = $here;

                continue;
            }

            if (!$feature->isContainment()) {
                continue;
            }

            $type = $model->classifier($feature->typeKey);

            $this->assertNotNull($type, $here);

            foreach ($feature->multiple ? $value : [$value] as $i => $child) {
                $strays = array_merge($strays, $this->strays($structure, $type, $child, $here . ($feature->multiple ? '.' . $i : '')));
            }
        }

        return $strays;
    }
}
