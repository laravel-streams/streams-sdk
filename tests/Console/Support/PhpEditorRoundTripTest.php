<?php

namespace Streams\Sdk\Tests\Console\Support;

use Illuminate\Support\Facades\File;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * Round-trip golden tests against the verbatim GroupVitals reference files.
 *
 * Design notes 6c/8: parsing and printing a file nobody asked to edit must be
 * byte-identical, and a real edit must re-print only the nodes it changed.
 */
class PhpEditorRoundTripTest extends SdkTestCase
{
    /**
     * The four PHP reference snapshots, relative to the v1 reference root.
     */
    protected function referenceFiles(): array
    {
        return [
            'app/Domains/People/Panel/Pages/ListPeople.php',
            'app/Domains/People/Panel/Tables/PeopleTable.php',
            'app/Domains/People/Panel/Actions/AddPersonModal.php',
            'app/Domains/People/Panel/Resources/PeoplePanelResource.php',
        ];
    }

    public function test_noop_round_trip_is_byte_identical_for_every_reference_fixture()
    {
        foreach ($this->referenceFiles() as $relative) {
            $original = $this->reference($relative);

            $editor = PhpEditor::fromString($original, $relative);

            $this->assertSame($original, $editor->print(), "no-op print changed {$relative}");
            $this->assertSame($editor->print(), $editor->print(), "repeated print changed {$relative}");
        }

        $registry = File::get(__DIR__.'/../../Fixtures/PhpEditor/registry.php');

        $this->assertSame($registry, PhpEditor::fromString($registry, 'registry.php')->print());
    }

    public function test_adding_a_mountable_action_to_the_page_touches_only_the_actions_array()
    {
        $original = $this->reference('app/Domains/People/Panel/Pages/ListPeople.php');

        $editor = PhpEditor::fromString($original, 'ListPeople.php');

        $action = new ArrayItem(new StaticCall(new Name('ExportPeopleModal'), new Identifier('resolve')));

        $this->assertTrue($editor->appendToArray($this->actionsArrayLocator(), $action));

        $diff = $editor->diff($original);

        // The only +/- line is the new action; nothing else in the page moves.
        $changed = $this->changedLines($diff);
        $this->assertCount(1, $changed);
        $this->assertStringContainsString('ExportPeopleModal::resolve(),', $changed[0]);

        // The commented-out action that was already there survives.
        $this->assertStringContainsString('// UploadPeopleModal::resolve(),', $editor->print());

        // Running the same edit again produces no change the second time.
        $first = $editor->print();
        $this->assertFalse($editor->appendToArray($this->actionsArrayLocator(), $action));
        $this->assertSame($first, $editor->print());
    }

    public function test_adding_an_attribute_touches_only_the_attributes_array()
    {
        $original = $this->reference('app/Domains/People/Panel/Actions/AddPersonModal.php');

        $editor = PhpEditor::fromString($original, 'AddPersonModal.php');

        $attribute = new ArrayItem(new ConstFetch(new Name('true')), new String_('x-cloak'));

        $this->assertTrue($editor->appendToArray($this->htmlAttributesArrayLocator(), $attribute));

        $diff = $editor->diff($original);

        // The only changed line is the mergeHtmlAttributes array gaining the
        // new attribute; no other node in the action is re-printed.
        $changed = $this->changedLines($diff);
        $this->assertCount(2, $changed);
        $this->assertStringContainsString('wire:click', $changed[0]);
        $this->assertStringContainsString("'x-cloak' => true", $changed[1]);

        // Running the same edit again produces no change the second time.
        $first = $editor->print();
        $this->assertFalse($editor->appendToArray($this->htmlAttributesArrayLocator(), $attribute));
        $this->assertSame($first, $editor->print());
    }

    public function test_appending_to_a_long_array_touches_only_that_array()
    {
        $original = $this->reference('app/Domains/People/Panel/Resources/PeoplePanelResource.php');

        $editor = PhpEditor::fromString($original, 'PeoplePanelResource.php');

        $route = new ArrayItem(
            new StaticCall(new Name('People\Panel\Pages\PersonExport'), new Identifier('route'), [new Arg(new String_('/export'))]),
            new String_('export'),
        );

        $this->assertTrue($editor->appendToArray($this->getPagesArrayLocator(), $route));

        $diff = $editor->diff($original);

        // The only +/- line is the new page entry at the end of getPages().
        $changed = $this->changedLines($diff);
        $this->assertCount(1, $changed);
        $this->assertStringContainsString("'export' => People\Panel\Pages\PersonExport::route('/export')", $changed[0]);

        // Every existing page entry is untouched.
        foreach (['index', 'groups', 'attendance', 'comments', 'forms', 'profile', 'timeline'] as $key) {
            $this->assertStringContainsString("'{$key}' => People\Panel\Pages", $editor->print());
        }

        // Running the same edit again produces no change the second time.
        $first = $editor->print();
        $this->assertFalse($editor->appendToArray($this->getPagesArrayLocator(), $route));
        $this->assertSame($first, $editor->print());
    }

    /**
     * Locator for the page's Heading actions array.
     */
    protected function actionsArrayLocator(): callable
    {
        return function ($array, array $stack): bool {
            $n = count($stack);

            return $n >= 2
                && $stack[$n - 1] instanceof Arg
                && $stack[$n - 2] instanceof MethodCall
                && $stack[$n - 2]->name instanceof Identifier
                && $stack[$n - 2]->name->toString() === 'actions';
        };
    }

    /**
     * Locator for the action's mergeHtmlAttributes array.
     */
    protected function htmlAttributesArrayLocator(): callable
    {
        return function ($array, array $stack): bool {
            $n = count($stack);

            return $n >= 2
                && $stack[$n - 1] instanceof Arg
                && $stack[$n - 2] instanceof MethodCall
                && $stack[$n - 2]->name instanceof Identifier
                && $stack[$n - 2]->name->toString() === 'mergeHtmlAttributes';
        };
    }

    /**
     * Locator for the resource's getPages() returned array.
     */
    protected function getPagesArrayLocator(): callable
    {
        return function ($array, array $stack): bool {
            $n = count($stack);

            return $n >= 2
                && $stack[$n - 1] instanceof Return_
                && $stack[$n - 2] instanceof ClassMethod
                && $stack[$n - 2]->name instanceof Identifier
                && $stack[$n - 2]->name->toString() === 'getPages';
        };
    }

    /**
     * The verbatim contents of one reference snapshot.
     */
    protected function reference(string $relative): string
    {
        return (string) File::get(__DIR__.'/../../Fixtures/References/v1/'.$relative);
    }

    /**
     * The added/removed lines in a unified diff, in order.
     */
    protected function changedLines(string $diff): array
    {
        $lines = [];

        foreach (explode("\n", $diff) as $line) {
            if (str_starts_with($line, '+') && ! str_starts_with($line, '+++')) {
                $lines[] = $line;
            }

            if (str_starts_with($line, '-') && ! str_starts_with($line, '---')) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}
