<?php

namespace Streams\Sdk\Console\Support\Transforms;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Streams\Sdk\Console\Support\PhpEditor;

/**
 * Shared traversal plumbing for transforms.
 *
 * Each transform describes a single enter-node edit; this trait runs that
 * edit across the editor's editable statements and writes the traversed
 * tree back. Nodes are mutated in place (the PhpEditor tree is already
 * deep-cloned), so untouched nodes keep their original tokens and the
 * format-preserving printer only re-prints what changed.
 */
trait EditsStatements
{
    /**
     * Traverse the editor's statements with $enter and write the result back.
     *
     * @param  callable(Node): void  $enter
     */
    protected function edit(PhpEditor $editor, callable $enter): PhpEditor
    {
        $traverser = new NodeTraverser;

        $traverser->addVisitor(new class($enter) extends NodeVisitorAbstract
        {
            public function __construct(protected $enter) {}

            public function enterNode(Node $node)
            {
                ($this->enter)($node);

                return null;
            }
        });

        return $editor->withStatements($traverser->traverse($editor->statements()));
    }
}
