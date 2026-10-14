<?php

namespace Streams\Sdk\Console\Support\Transforms;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassLike;
use Streams\Sdk\Console\Support\PhpEditor;

/**
 * Rename the first named class-like declaration (class, interface, trait,
 * or enum) in the file. Anonymous classes are ignored.
 */
class RenameClass implements Transform
{
    use EditsStatements;

    public function __construct(protected string $name) {}

    public function apply(PhpEditor $editor): PhpEditor
    {
        return $this->edit($editor, function (Node $node): void {
            if ($node instanceof ClassLike && $node->name !== null) {
                $node->name = new Identifier($this->name);
            }
        });
    }
}
