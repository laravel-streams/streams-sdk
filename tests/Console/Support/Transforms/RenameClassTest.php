<?php

namespace Streams\Sdk\Tests\Console\Support\Transforms;

use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Console\Support\Transforms\RenameClass;

class RenameClassTest extends TransformsTestCase
{
    public function test_it_renames_the_class_and_touches_only_the_declaration()
    {
        $original = $this->fixture();

        $editor = PhpEditor::fromString($original, 'widgets.php');

        (new RenameClass('PostsTable'))->apply($editor);

        // The only +/- line is the class declaration; the namespace, use
        // statements, and setUp() body are printed from their original tokens.
        $this->assertSame([
            '-class WidgetsTable extends Widget',
            '+class PostsTable extends Widget',
        ], $this->changedLines($editor->diff($original)));

        $this->assertStringContainsString('class PostsTable extends Widget', $editor->print());
        $this->assertStringNotContainsString('WidgetsTable', $editor->print());

        // Applying the same rename again leaves the file byte-for-byte alone.
        $first = $editor->print();
        (new RenameClass('PostsTable'))->apply($editor);
        $this->assertSame($first, $editor->print());
    }
}
