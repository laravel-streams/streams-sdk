<?php

namespace Streams\Sdk\Console\Support\Transforms;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use Streams\Sdk\Console\Support\PhpEditor;

/**
 * Add a member (method, property, or class constant) to the first named
 * class-like declaration.
 *
 * The member is a php-parser statement node built by the caller. Adding a
 * member whose name already exists on the class is a no-op, so applying the
 * transform twice never duplicates a member.
 */
class AddMember implements Transform
{
    use EditsStatements;

    public function __construct(protected Node\Stmt $member) {}

    public function apply(PhpEditor $editor): PhpEditor
    {
        return $this->edit($editor, function (Node $node): void {
            if ($node instanceof ClassLike && $node->name !== null && $this->missing($node)) {
                $node->stmts[] = $this->member;
            }
        });
    }

    protected function missing(ClassLike $class): bool
    {
        foreach ($class->stmts as $existing) {
            if ($this->sameName($existing, $this->member)) {
                return false;
            }
        }

        return true;
    }

    protected function sameName(Node $existing, Node $member): bool
    {
        if ($existing instanceof ClassMethod && $member instanceof ClassMethod) {
            return $existing->name->toString() === $member->name->toString();
        }

        if ($existing instanceof Property && $member instanceof Property) {
            return $existing->props[0]->name->toString() === $member->props[0]->name->toString();
        }

        if ($existing instanceof ClassConst && $member instanceof ClassConst) {
            return $existing->consts[0]->name->toString() === $member->consts[0]->name->toString();
        }

        return false;
    }
}
