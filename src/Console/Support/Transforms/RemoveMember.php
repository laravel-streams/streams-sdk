<?php

namespace Streams\Sdk\Console\Support\Transforms;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use Streams\Sdk\Console\Support\PhpEditor;

/**
 * Remove every member (method, property, or class constant) with the given
 * name from the first named class-like declaration. Members without that
 * name, and the surrounding class structure, are left untouched.
 */
class RemoveMember implements Transform
{
    use EditsStatements;

    public function __construct(protected string $name) {}

    public function apply(PhpEditor $editor): PhpEditor
    {
        return $this->edit($editor, function (Node $node): void {
            if ($node instanceof ClassLike && $node->name !== null) {
                $node->stmts = array_values(array_filter(
                    $node->stmts,
                    fn (Node $stmt): bool => ! $this->matches($stmt),
                ));
            }
        });
    }

    protected function matches(Node $stmt): bool
    {
        if ($stmt instanceof ClassMethod) {
            return $stmt->name->toString() === $this->name;
        }

        if ($stmt instanceof Property) {
            return $stmt->props[0]->name->toString() === $this->name;
        }

        if ($stmt instanceof ClassConst) {
            return $stmt->consts[0]->name->toString() === $this->name;
        }

        return false;
    }
}
