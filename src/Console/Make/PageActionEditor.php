<?php

namespace Streams\Sdk\Console\Make;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter\Standard;
use Streams\Sdk\Console\Support\PhpEditor;

/** Attach one modal to a page without replacing the author's page implementation. */
class PageActionEditor
{
    public function attach(string $source, string $file, string $class, string $action): string
    {
        $editor = PhpEditor::fromString($source, $file);
        $finder = new NodeFinder;
        $classes = $finder->find($editor->statements(), fn (Node $node): bool => $node instanceof Class_ && $node->name?->toString() === $class);
        if (count($classes) !== 1) {
            throw new \RuntimeException('expected one class declaration for '.$class);
        }
        $page = $classes[0];
        $boot = $page->getMethod('boot');
        $components = $page->getMethod('getComponents');
        if ($boot === null || $boot->stmts === null || $components === null) {
            throw new \RuntimeException('boot() and getComponents() are required');
        }

        $calls = $finder->find($components->stmts ?? [], function (Node $node): bool {
            if (! $node instanceof MethodCall || ! $node->name instanceof Identifier || $node->name->toString() !== 'actions') {
                return false;
            }
            $receiver = $node->var;
            while ($receiver instanceof MethodCall) {
                $receiver = $receiver->var;
            }

            return $receiver instanceof StaticCall
                && $receiver->class instanceof Name
                && $receiver->class->getLast() === 'Heading'
                && isset($node->args[0])
                && $node->args[0]->value instanceof Array_;
        });
        if (count($calls) !== 1) {
            throw new \RuntimeException('expected one Heading actions array in getComponents()');
        }

        $traits = $finder->findInstanceOf($page->stmts, Node\Stmt\TraitUse::class);
        $hasActions = false;
        foreach ($traits as $use) {
            foreach ($use->traits as $trait) {
                $hasActions = $hasActions || $trait->getLast() === 'InteractsWithActions';
            }
        }
        if (! $hasActions) {
            throw new \RuntimeException('InteractsWithActions trait is required');
        }

        // Reuse an existing import (including aliases); otherwise use the FQCN.
        $name = new FullyQualified($action);
        foreach ($finder->findInstanceOf($editor->statements(), Use_::class) as $use) {
            if ($use->type !== Use_::TYPE_NORMAL) {
                continue;
            }
            foreach ($use->uses as $import) {
                if (strcasecmp($import->name->toString(), $action) === 0) {
                    $name = new Name($import->getAlias()->toString());
                }
            }
        }

        $array = $calls[0]->args[0]->value;
        if ($array->items === []) {
            $lines = explode("\n", $source);
            $span = implode("\n", array_slice($lines, $array->getStartLine() - 1, $array->getEndLine() - $array->getStartLine() + 1));
            if (str_contains($span, '//') || str_contains($span, '/*')) {
                throw new \RuntimeException('empty actions array contains comments that cannot be preserved');
            }
        }
        $editor->appendToArray(
            fn (Array_ $candidate): bool => $candidate === $array,
            new ArrayItem(new StaticCall(clone $name, new Identifier('resolve'))),
        );

        $register = new Expression(new StaticCall(clone $name, new Identifier('register'), [new Arg(new Variable('this'))]));
        $printer = new Standard;
        $exists = false;
        foreach ($boot->stmts as $statement) {
            if ($printer->prettyPrint([$statement]) === $printer->prettyPrint([$register])) {
                $exists = true;
            }
        }
        if (! $exists) {
            $boot->stmts[] = $register;
        }

        return $editor->print();
    }
}
