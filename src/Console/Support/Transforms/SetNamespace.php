<?php

namespace Streams\Sdk\Console\Support\Transforms;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Namespace_;
use Streams\Sdk\Console\Support\PhpEditor;

/**
 * Set the namespace of the file.
 *
 * When the file already has a namespace declaration its name is replaced in
 * place. When it has none, the top-level statements are wrapped in one so
 * the file still parses the same way.
 */
class SetNamespace implements Transform
{
    use EditsStatements;

    public function __construct(protected string $namespace) {}

    public function apply(PhpEditor $editor): PhpEditor
    {
        $found = false;

        $editor = $this->edit($editor, function (Node $node) use (&$found): void {
            if ($node instanceof Namespace_ && $node->name !== null) {
                $node->name = new Name($this->namespace);
                $found = true;
            }
        });

        if ($found) {
            return $editor;
        }

        $statements = $editor->statements();

        if ($statements === []) {
            return $editor;
        }

        return $editor->withStatements([
            new Namespace_(new Name($this->namespace), $statements),
        ]);
    }
}
