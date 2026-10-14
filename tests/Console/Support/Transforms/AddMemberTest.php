<?php

namespace Streams\Sdk\Tests\Console\Support\Transforms;

use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use Streams\Sdk\Console\Support\PhpEditor;
use Streams\Sdk\Console\Support\Transforms\AddMember;

class AddMemberTest extends TransformsTestCase
{
    public function test_it_adds_a_method_and_touches_only_the_added_member()
    {
        $original = $this->fixture();

        $editor = PhpEditor::fromString($original, 'widgets.php');

        (new AddMember(new ClassMethod(new Identifier('heading'), [
            'flags' => Class_::MODIFIER_PUBLIC,
            'returnType' => new Identifier('string'),
            'stmts' => [new Return_(new String_('Widgets'))],
        ])))->apply($editor);

        // The only +/- lines are the new method; setUp() and the class
        // declaration are printed from their original tokens.
        $this->assertSame([
            '+    public function heading() : string',
            '+    {',
            "+        return 'Widgets';",
            '+    }',
        ], $this->changedLines($editor->diff($original)));

        // Adding another member with the same name is a no-op, so repeated
        // generations never duplicate a member.
        $first = $editor->print();
        (new AddMember(new ClassMethod(new Identifier('heading'), [])))->apply($editor);
        $this->assertSame($first, $editor->print());
    }
}
