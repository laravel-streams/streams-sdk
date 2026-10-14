<?php

namespace Streams\Sdk\Console\Support;

use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

/**
 * Edits a PHP file through nikic/php-parser and writes it back with the
 * format-preserving printer.
 *
 * The file is parsed once and the AST is deep-cloned. Callers mutate the
 * clone; printing matches every untouched node back to its original tokens
 * so only the nodes that were actually changed are re-printed. A no-op edit
 * therefore prints the file byte-for-byte (idempotent).
 */
class PhpEditor
{
    /**
     * The editable (deep-cloned) statements.
     */
    protected array $statements = [];

    /**
     * The original statements the file was parsed into.
     */
    protected array $originalStatements = [];

    /**
     * The original token stream, used by the format-preserving printer.
     *
     * @var array<int, mixed>
     */
    protected array $tokens = [];

    public function __construct(protected string $path)
    {
    }

    public static function read(string $path): static
    {
        return static::fromString((string) file_get_contents($path), $path);
    }

    /**
     * Build an editor from code rather than the filesystem.
     */
    public static function fromString(string $code, string $path): static
    {
        $editor = new static($path);

        $lexer = new Lexer([
            'usedAttributes' => [
                'comments',
                'startLine',
                'endLine',
                'startTokenPos',
                'endTokenPos',
            ],
        ]);

        $parser = static::parser($lexer);

        $editor->originalStatements = $parser->parse($code);
        $editor->tokens = $lexer->getTokens();

        // Deep-clone the AST before editing: the format-preserving printer
        // matches new nodes back to the original tokens by their positions,
        // so unchanged nodes must still carry the original position data.
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new CloningVisitor);

        $editor->statements = $traverser->traverse($editor->originalStatements);

        return $editor;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The editable (deep-cloned) statements.
     */
    public function statements(): array
    {
        return $this->statements;
    }

    /**
     * Replace the editable statements after applying node edits.
     */
    public function withStatements(array $statements): static
    {
        $this->statements = $statements;

        return $this;
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
     * The format-preserving print of the edited file.
     */
    public function print(): string
    {
        return (new Standard)->printFormatPreserving(
            $this->statements,
            $this->originalStatements,
            $this->tokens,
        );
    }

    protected static function parser(Lexer $lexer): Parser
    {
        return (new ParserFactory)->create(ParserFactory::PREFER_PHP7, $lexer);
    }
}
