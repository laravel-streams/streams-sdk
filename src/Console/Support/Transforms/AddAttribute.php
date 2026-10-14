<?php

namespace Streams\Sdk\Console\Support\Transforms;

use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\DNumber;
use PhpParser\Node\Expr\LNumber;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use Streams\Sdk\Console\Support\PhpEditor;

/**
 * Add a PHP 8 attribute to the first named class-like declaration.
 *
 * $args are the attribute arguments and may be plain scalars (string, int,
 * float, bool, null) or already-built php-parser expression nodes. Adding an
 * attribute that is already present is a no-op, so the transform is
 * idempotent across repeated generations.
 */
class AddAttribute implements Transform
{
    use EditsStatements;

    /**
     * @param  list<mixed>  $args
     */
    public function __construct(
        protected string $name,
        protected array $args = [],
    ) {
    }

    public function apply(PhpEditor $editor): PhpEditor
    {
        return $this->edit($editor, function (Node $node): void {
            if ($node instanceof ClassLike && $node->name !== null && $this->missing($node)) {
                $node->attrGroups[] = new AttributeGroup([
                    new Attribute(new Name($this->name), $this->arguments()),
                ]);
            }
        });
    }

    protected function missing(ClassLike $class): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() === $this->name) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @return list<Arg>
     */
    protected function arguments(): array
    {
        $arguments = [];

        foreach ($this->args as $key => $value) {
            $name = is_string($key) ? new Identifier($key) : null;

            $arguments[] = new Arg($this->value($value), false, false, [], $name);
        }

        return $arguments;
    }

    protected function value(mixed $value): Expr
    {
        if ($value instanceof Expr) {
            return $value;
        }

        if (is_string($value)) {
            return new String_($value);
        }

        if (is_int($value)) {
            return new LNumber($value);
        }

        if (is_float($value)) {
            return new DNumber($value);
        }

        if (is_bool($value)) {
            return new ConstFetch(new Name($value ? 'true' : 'false'));
        }

        if ($value === null) {
            return new ConstFetch(new Name('null'));
        }

        throw new InvalidArgumentException('Unsupported attribute argument: '.get_debug_type($value));
    }
}
