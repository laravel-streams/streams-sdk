<?php

namespace Streams\Sdk\Tests\Console\Support\Transforms;

use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Console\Support\Transforms\RemoveMember;

class RemoveMemberTest extends TransformsTestCase
{
    public function test_it_removes_the_named_member_and_keeps_everything_else()
    {
        $original = $this->fixture();

        $editor = PhpEditor::fromString($original, 'widgets.php');

        (new RemoveMember('setUp'))->apply($editor);

        $diff = $editor->diff($original);

        // The whole setUp() method is removed...
        $this->assertStringContainsString('-    protected function setUp(): void', $diff);
        $this->assertStringNotContainsString('function setUp', $editor->print());

        // ...and the class, namespace, and remaining structure are intact.
        $this->assertStringContainsString('class WidgetsTable extends Widget', $editor->print());
        $this->assertStringContainsString('namespace App\Widgets;', $editor->print());

        // Removing a member that is not there is a no-op.
        $first = $editor->print();
        (new RemoveMember('missing'))->apply($editor);
        $this->assertSame($first, $editor->print());
    }
}
