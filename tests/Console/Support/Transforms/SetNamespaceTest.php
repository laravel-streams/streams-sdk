<?php

namespace Streams\Sdk\Tests\Console\Support\Transforms;

use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Console\Support\Transforms\SetNamespace;

class SetNamespaceTest extends TransformsTestCase
{
    public function test_it_replaces_an_existing_namespace_and_touches_only_that_line()
    {
        $original = $this->fixture();

        $editor = PhpEditor::fromString($original, 'widgets.php');

        (new SetNamespace('App\Widgets\Panel\Tables'))->apply($editor);

        $this->assertSame([
            '-namespace App\Widgets;',
            '+namespace App\Widgets\Panel\Tables;',
        ], $this->changedLines($editor->diff($original)));

        $this->assertStringContainsString('namespace App\Widgets\Panel\Tables;', $editor->print());
    }

    public function test_it_wraps_a_file_that_has_no_namespace()
    {
        $original = <<<'PHP'
<?php

class WidgetsTable
{
    public function heading(): string
    {
        return 'Widgets';
    }
}
PHP;

        $editor = PhpEditor::fromString($original, 'widgets.php');

        (new SetNamespace('App\Widgets\Panel\Tables'))->apply($editor);

        $this->assertStringContainsString('namespace App\Widgets\Panel\Tables;', $editor->print());
        $this->assertStringContainsString('class WidgetsTable', $editor->print());

        // The edited file still parses and the class is inside the namespace.
        $editor2 = PhpEditor::fromString($editor->print(), 'widgets.php');
        $this->assertSame($editor->print(), $editor2->print());
    }
}
