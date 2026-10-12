<?php

namespace Streams\Sdk\Console\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * Edits a PHP file in place through an AST.
 *
 * The file is parsed with nikic/php-parser, mutated, and pretty-printed.
 * Printing is deterministic, so re-running an edit that is already present
 * returns "not changed" and saves nothing (idempotent).
 */
class PhpFile
{
    protected array $statements = [];

    public function __construct(protected string $path)
    {
    }

    public static function read(string $path): static
    {
        $file = new static($path);

        $file->statements = $file->parser()->parse((string) file_get_contents($path));

        return $file;
    }

    /**
     * Build a PhpFile from code rather than the filesystem.
     */
    public static function fromString(string $code, string $path): static
    {
        $file = new static($path);

        $file->statements = $file->parser()->parse($code);

        return $file;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Add (or update) a keyed item in the file's top-level returned array.
     *
     * $flags are key/value pairs such as ['panel' => true, 'page' => false].
     * Existing keys are updated in place; missing ones are appended, so the
     * edit is idempotent.
     *
     * @param array<string, bool> $flags
     */
    public function put(string $key, array $flags = []): bool
    {
        $before = $this->print();

        $traverser = new NodeTraverser;

        $traverser->addVisitor(new class($key, $flags) extends NodeVisitorAbstract
        {
            public function __construct(protected string $key, protected array $flags)
            {
            }

            public function enterNode(Node $node)
            {
                if ($node instanceof Return_ && $node->expr instanceof Array_) {
                    $node->expr = $this->merge($node->expr);

                    return $node;
                }

                return null;
            }

            protected function merge(Array_ $array): Array_
            {
                $item = null;

                foreach ($array->items as $candidate) {
                    if ($candidate->key instanceof String_ && $candidate->key->value === $this->key) {
                        $item = $candidate;

                        break;
                    }
                }

                if ($item === null) {
                    $array->items[] = new ArrayItem($this->flagsNode(), new String_($this->key));

                    return $array;
                }

                if (!$item->value instanceof Array_) {
                    $item->value = new Array_([], ['kind' => Array_::KIND_SHORT]);
                }

                $item->value = $this->mergeFlags($item->value);

                return $array;
            }

            protected function mergeFlags(Array_ $array): Array_
            {
                foreach ($this->flags as $flag => $value) {
                    $valueNode = $value
                        ? new ConstFetch(new Name('true'))
                        : new ConstFetch(new Name('false'));

                    $replaced = false;

                    foreach ($array->items as $candidate) {
                        if ($candidate->key instanceof String_ && $candidate->key->value === $flag) {
                            $candidate->value = $valueNode;

                            $replaced = true;

                            break;
                        }
                    }

                    if (!$replaced) {
                        $array->items[] = new ArrayItem($valueNode, new String_($flag));
                    }
                }

                return $array;
            }

            protected function flagsNode(): Array_
            {
                $items = [];

                foreach ($this->flags as $flag => $value) {
                    $items[] = new ArrayItem(
                        $value ? new ConstFetch(new Name('true')) : new ConstFetch(new Name('false')),
                        new String_($flag),
                    );
                }

                return new Array_($items, ['kind' => Array_::KIND_SHORT]);
            }
        });

        $this->statements = $traverser->traverse($this->statements);

        return $before !== $this->print();
    }

    /**
     * The pretty-printed contents of the file.
     */
    public function contents(): string
    {
        return $this->print();
    }

    /**
     * The unified diff between the original contents and the edited
     * contents — exactly what saving the file would apply.
     */
    public function diff(string $original): string
    {
        $differ = new Differ(new UnifiedDiffOutputBuilder("--- {$this->path}\n+++ {$this->path}\n"));

        return $differ->diff($original, $this->contents());
    }

    protected function parser(): Parser
    {
        return (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    }

    protected function print(): string
    {
        return (new Standard)->prettyPrintFile($this->statements);
    }
}