<?php

namespace Streams\Sdk\Tests\Console\Support\Transforms;

use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Console\Support\Transforms\AddAttribute;

class AddAttributeTest extends TransformsTestCase
{
    public function test_it_adds_an_attribute_group_and_touches_only_that_line()
    {
        $original = $this->fixture();

        $editor = PhpEditor::fromString($original, 'widgets.php');

        (new AddAttribute('App\Attributes\Widgets', ['widgets', 'icon' => 'w']))->apply($editor);

        // The only +/- line is the new attribute; nothing else moves.
        $this->assertSame([
            "+#[App\Attributes\Widgets('widgets', icon: 'w')]",
        ], $this->changedLines($editor->diff($original)));

        $this->assertStringContainsString("#[App\Attributes\Widgets('widgets', icon: 'w')]", $editor->print());

        // Adding the same attribute again is a no-op.
        $first = $editor->print();
        (new AddAttribute('App\Attributes\Widgets', ['widgets']))->apply($editor);
        $this->assertSame($first, $editor->print());
    }
}
