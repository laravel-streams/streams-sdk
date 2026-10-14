<?php

namespace Streams\Sdk\Console\Capture;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;

/**
 * Reads existing builder settings into the app tree.
 *
 * Capture is not arbitrary PHP parsing. It reads the class-level builder
 * settings — static properties and the setUp()/getPages()/navigation()
 * builder chains — from the known reference layout
 * (app/Domains/{Domain}/Panel/{Tables,Pages,Actions,Resources,Forms,Public}).
 * Literal settings become JSON data in the node. Closures, permission
 * callbacks, class-string resolvers, translation calls and computed options
 * become opaque nodes pointing at their source file and line. Opaque nodes
 * are owned by people; streams:make never rewrites them.
 */
class CaptureReader
{
    /**
     * Builder methods whose chained calls are captured as settings.
     *
     * @var list<string>
     */
    public const BUILDER_METHODS = [
        'setUp',
        'getPages',
        'getComponents',
        'navigation',
        'breadcrumbs',
        'scopedQuery',
    ];

    /**
     * Panel directory => captured node kind.
     *
     * @var array<string, string>
     */
    protected const KIND_BY_DIRECTORY = [
        'Tables' => 'table',
        'Pages' => 'page',
        'Actions' => 'action',
        'Resources' => 'panel',
        'Forms' => 'form',
        'Public' => 'public_page',
    ];

    /**
     * Captured node kind => ui container name.
     *
     * @var array<string, string>
     */
    protected const CONTAINER_BY_KIND = [
        'table' => 'table',
        'panel' => 'panel',
        'page' => 'pages',
        'action' => 'actions',
        'form' => 'forms',
        'public_page' => 'public_pages',
    ];

    public function __construct(protected string $root) {}

    /**
     * Builder files that could not be captured, with the reason.
     *
     * @return list<array{file: string, reason: string}>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /**
     * Capture the reference layout into an app tree payload.
     *
     * Builder files that cannot be read are never dropped silently: they are
     * reported through skipped() and surfaced by the streams:capture command.
     *
     * @return array{name: string, streams: array<string, array<string, mixed>>}
     */
    public function capture(): array
    {
        $streams = $this->streams();

        foreach ($this->builderFiles() as $file) {
            $relative = $this->relative($file);
            $mapped = $this->mapPath($relative);

            if ($mapped === null) {
                continue;
            }

            [$stream, $container, $handle, $kind] = $mapped;

            $node = $this->captureFile($file, $relative, $kind, $handle);

            if ($node === null) {
                $this->skipped[] = [
                    'file' => $relative,
                    'reason' => $this->skipReason ?? 'unrecognized builder file',
                ];
                $this->skipReason = null;

                continue;
            }

            $streams[$stream] = $this->place($streams[$stream] ?? [], $container, $handle, $node);
        }

        return [
            'name' => 'Captured',
            'streams' => $streams,
        ];
    }

    /**
     * @var list<array{file: string, reason: string}>
     */
    protected array $skipped = [];

    protected ?string $skipReason = null;

    /**
     * Stream definitions under streams/ become stream nodes.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function streams(): array
    {
        $streams = [];

        foreach (glob($this->root.'/streams/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);

            if (! is_array($data)) {
                throw new CaptureException("Stream file [{$file}] is not a JSON object.");
            }

            $handle = $data['handle'] ?? basename($file, '.json');
            $data['handle'] = $handle;
            $data['name'] = $data['name'] ?? ucfirst($handle);

            $streams[$handle] = $data;
        }

        return $streams;
    }

    /**
     * @return array<int, string>
     */
    protected function builderFiles(): array
    {
        return glob($this->root.'/app/Domains/*/Panel/{Tables,Pages,Actions,Resources,Forms,Public}/*.php', GLOB_BRACE) ?: [];
    }

    /**
     * Map a relative builder path to its tree location.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}|null
     */
    protected function mapPath(string $relative): ?array
    {
        if (preg_match('#^app/Domains/([^/]+)/Panel/([^/]+)/([^/]+)\.php$#', $relative, $matches) !== 1) {
            return null;
        }

        $domain = $matches[1];
        $directory = $matches[2];
        $class = $matches[3];

        $kind = self::KIND_BY_DIRECTORY[$directory] ?? null;

        if ($kind === null) {
            return null;
        }

        $stream = strtolower($domain);
        $handle = $this->snake($class);

        return [$stream, self::CONTAINER_BY_KIND[$kind], $handle, $kind];
    }

    /**
     * Place a captured node under its stream's ui subtree.
     *
     * @param  array<string, mixed>  $stream
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    protected function place(array $stream, string $container, string $handle, array $node): array
    {
        if ($container === 'table' || $container === 'panel') {
            $stream['ui'][$container] = $node;

            return $stream;
        }

        $stream['ui'][$container][$handle] = $node;

        return $stream;
    }

    /**
     * Capture one builder file into a node payload.
     *
     * @return array{handle: string, kind: string, source: string, settings: array<string, mixed>, opaque: list<array{handle: string, file: string, line: int}>}|null
     */
    protected function captureFile(string $file, string $relative, string $kind, string $handle): ?array
    {
        $statements = $this->parse($file);

        if ($statements === null) {
            $this->skipReason = 'file could not be parsed';

            return null;
        }

        $class = $this->findClass($statements);

        if ($class === null) {
            $this->skipReason = 'no builder class found';

            return null;
        }

        $settings = [];
        $opaque = [];

        foreach ($class->stmts ?? [] as $stmt) {
            if ($stmt instanceof Property && $stmt->isStatic()) {
                $this->captureProperties($stmt, $relative, $settings, $opaque);
            }

            if ($stmt instanceof ClassMethod && in_array($stmt->name->toString(), self::BUILDER_METHODS, true)) {
                $this->captureMethod($stmt, $relative, $settings, $opaque);
            }
        }

        ksort($settings);

        usort($opaque, function (array $left, array $right): int {
            return [$left['line'], $left['handle']] <=> [$right['line'], $right['handle']];
        });

        return [
            'handle' => $handle,
            'kind' => $kind,
            'source' => $relative,
            'settings' => $settings,
            'opaque' => $opaque,
        ];
    }

    /**
     * @return list<Node>|null
     */
    protected function parse(string $file): ?array
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

        try {
            return $parser->parse((string) file_get_contents($file)) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<Node>  $statements
     */
    protected function findClass(array $statements): ?Class_
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Class_) {
                return $statement;
            }

            if ($statement instanceof Namespace_) {
                $class = $this->findClass($statement->stmts);

                if ($class !== null) {
                    return $class;
                }
            }
        }

        return null;
    }

    /**
     * Static properties are builder settings: literal values are data,
     * class constants and other computed values are opaque.
     *
     * @param  array<string, mixed>  $settings
     * @param  list<array{handle: string, file: string, line: int}>  $opaque
     */
    protected function captureProperties(Property $property, string $file, array &$settings, array &$opaque): void
    {
        foreach ($property->props as $prop) {
            $name = $prop->name->toString();

            if ($prop->default === null) {
                continue;
            }

            [$found, $value, $entries] = $this->literalOrOpaque($prop->default, $file, $name);

            if ($found) {
                $settings[$name] = $value;
            }

            foreach ($entries as $entry) {
                $opaque[] = $entry;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array{handle: string, file: string, line: int}>  $opaque
     */
    protected function captureMethod(ClassMethod $method, string $file, array &$settings, array &$opaque): void
    {
        foreach ($method->stmts ?? [] as $stmt) {
            $this->captureStatement($stmt, $file, $method->name->toString(), $settings, $opaque);
        }
    }

    /**
     * Capture one statement inside a builder method. Assignments are local
     * scaffolding, not builder settings, and never become opaque nodes.
     * Builder calls inside control flow are still walked so they are not
     * silently discarded.
     *
     * @param  array<string, mixed>  $settings
     * @param  list<array{handle: string, file: string, line: int}>  $opaque
     */
    protected function captureStatement(Stmt $stmt, string $file, string $prefix, array &$settings, array &$opaque): void
    {
        if ($stmt instanceof Expression) {
            if ($stmt->expr instanceof Assign) {
                return;
            }

            $this->chain($stmt->expr, $file, '', $settings, $opaque);

            return;
        }

        if ($stmt instanceof Return_ && $stmt->expr !== null) {
            $this->chain($stmt->expr, $file, $prefix, $settings, $opaque);

            return;
        }

        if ($stmt instanceof If_) {
            foreach ($stmt->stmts as $inner) {
                $this->captureStatement($inner, $file, $prefix, $settings, $opaque);
            }

            foreach ($stmt->elseifs as $elseif) {
                foreach ($elseif->stmts as $inner) {
                    $this->captureStatement($inner, $file, $prefix, $settings, $opaque);
                }
            }

            if ($stmt->else !== null) {
                foreach ($stmt->else->stmts as $inner) {
                    $this->captureStatement($inner, $file, $prefix, $settings, $opaque);
                }
            }

            return;
        }

        if ($stmt instanceof Foreach_) {
            foreach ($stmt->stmts as $inner) {
                $this->captureStatement($inner, $file, $prefix, $settings, $opaque);
            }
        }
    }

    /**
     * Walk a builder call chain. Literal arguments become settings; closures,
     * callbacks, resolvers and computed values become opaque nodes.
     *
     * When $item is true the chain starts at one entry of a builder container,
     * so a terminal X::make(...) is that item's constructor, not a setting.
     *
     * @param  array<string, mixed>  $settings
     * @param  list<array{handle: string, file: string, line: int}>  $opaque
     */
    protected function chain(Expr $expr, string $file, string $prefix, array &$settings, array &$opaque, bool $item = false): void
    {
        if ($expr instanceof MethodCall) {
            // Deeper in the chain first: ->filters([...])->columns([...]).
            $this->chain($expr->var, $file, $prefix, $settings, $opaque, $item);

            $method = $expr->name instanceof Node\Identifier ? $expr->name->toString() : null;

            if ($method !== null) {
                $this->call($method, $this->filterArgs($expr->args), $file, $prefix, $settings, $opaque);
            }

            return;
        }

        if ($expr instanceof StaticCall) {
            $method = $expr->name instanceof Node\Identifier ? $expr->name->toString() : null;

            // A container item's X::make(...) is its constructor, not a
            // setting. A literal name is the item key; a computed name
            // (translation, variable) is an opaque node under `name`.
            if ($item && $method === 'make' && isset($expr->args[0]) && $expr->args[0] instanceof Arg) {
                if (! $this->literal($expr->args[0]->value)[0]) {
                    $opaque[] = $this->opaque($this->joined($prefix, 'name'), $file, $expr->args[0]->value->getStartLine());
                }

                return;
            }

            // X::make('handle') names the builder item already captured by the
            // container walker. It is not itself a setting.
            if ($method === 'make' && isset($expr->args[0]) && $expr->args[0] instanceof Arg && $expr->args[0]->value instanceof String_) {
                return;
            }

            // A resolver/route class call is computed: opaque.
            $opaque[] = $this->opaque($this->joined($prefix, $this->classLabel($expr->class)), $file, $expr->getStartLine());

            return;
        }

        if ($expr instanceof Closure || $expr instanceof ArrowFunction) {
            $opaque[] = $this->opaque($prefix, $file, $expr->getStartLine());

            return;
        }

        // The chain root itself ($this or a variable) is not a setting.
        if ($expr instanceof Variable) {
            return;
        }

        // Assignment scaffolding is not a builder setting.
        if ($expr instanceof Assign) {
            return;
        }

        // A literal item inside a builder container is data, not opaque.
        $literal = $this->literalArrayValue($expr);

        if ($literal[0]) {
            $settings[$prefix] = $literal[1];

            return;
        }

        // Translation, route, criteria and other computed values are opaque.
        $opaque[] = $this->opaque($prefix, $file, $expr->getStartLine());
    }

    /**
     * @param  list<Arg>  $args
     * @param  array<string, mixed>  $settings
     * @param  list<array{handle: string, file: string, line: int}>  $opaque
     */
    protected function call(string $method, array $args, string $file, string $prefix, array &$settings, array &$opaque): void
    {
        $key = $this->joined($prefix, $method);

        if ($args === []) {
            // ->sortable() is a boolean setting.
            $settings[$key] = true;

            return;
        }

        if (count($args) === 1) {
            $this->argument($args[0]->value, $key, $file, $settings, $opaque);

            return;
        }

        // Multiple arguments: keep them as data only when every argument is a
        // literal. A call that depends on any computed argument is opaque as a
        // whole, never split into duplicate opaque handles.
        $values = [];

        foreach ($args as $arg) {
            $literal = $this->literalArrayValue($arg->value);

            if (! $literal[0]) {
                $opaque[] = $this->opaque($key, $file, $arg->value->getStartLine());

                return;
            }

            $values[] = $literal[1];
        }

        $settings[$key] = $values;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array{handle: string, file: string, line: int}>  $opaque
     */
    protected function argument(Expr $arg, string $key, string $file, array &$settings, array &$opaque): void
    {
        if ($arg instanceof Array_) {
            if ($this->isLiteralArray($arg)) {
                $settings[$key] = $this->literalArray($arg);

                return;
            }

            // A builder container: columns, filters, actions, components.
            foreach ($arg->items as $index => $item) {
                if (! $item instanceof ArrayItem) {
                    continue;
                }

                $itemKey = $this->itemHandle($item, (string) $index);

                // A spread (...$x) merges items at runtime; it cannot be
                // expanded here, so it is opaque rather than literal data.
                if ($item->unpack) {
                    $opaque[] = $this->opaque($this->joined($key, $itemKey), $file, $item->getStartLine());

                    continue;
                }

                $this->chain($item->value, $file, $this->joined($key, $itemKey), $settings, $opaque, true);
            }

            return;
        }

        [$found, $value, $entries] = $this->literalOrOpaque($arg, $file, $key);

        if ($found) {
            $settings[$key] = $value;

            return;
        }

        foreach ($entries as $entry) {
            $opaque[] = $entry;
        }
    }

    /**
     * The handle of one builder container item: its make() name, its
     * array key, or its position.
     */
    protected function itemHandle(ArrayItem $item, string $index): string
    {
        if ($item->key instanceof String_) {
            return $item->key->value;
        }

        $value = $item->value;

        // ->make('handle')->label(...) chains start at a make() static call.
        while ($value instanceof MethodCall) {
            $value = $value->var;
        }

        if ($value instanceof StaticCall && $value->name instanceof Node\Identifier && $value->name->toString() === 'make') {
            if (isset($value->args[0]) && $value->args[0] instanceof Arg && $value->args[0]->value instanceof String_) {
                return $value->args[0]->value->value;
            }
        }

        return $index;
    }

    /**
     * A literal value or opaque entries for a non-literal expression.
     *
     * The first element reports whether the expression is a literal at all,
     * so a literal null is data, not opaque.
     *
     * @return array{0: bool, 1: mixed, 2: list<array{handle: string, file: string, line: int}>}
     */
    protected function literalOrOpaque(Expr $expr, string $file, string $key): array
    {
        $literal = $this->literalArrayValue($expr);

        if ($literal[0]) {
            return [true, $literal[1], []];
        }

        return [false, null, [$this->opaque($key, $file, $expr->getStartLine())]];
    }

    /**
     * Whether an expression is a JSON-representable literal and its value.
     *
     * @return array{0: bool, 1: mixed}
     */
    protected function literal(Expr $expr): array
    {
        if ($expr instanceof String_) {
            return [true, $expr->value];
        }

        if ($expr instanceof LNumber) {
            return [true, $expr->value];
        }

        if ($expr instanceof DNumber) {
            return [true, $expr->value];
        }

        if ($expr instanceof ConstFetch) {
            $name = $expr->name->toString();

            return match ($name) {
                'true' => [true, true],
                'false' => [true, false],
                'null' => [true, null],
                default => [false, null],
            };
        }

        // A signed numeric literal (-5, +1.5) is still a literal.
        if ($expr instanceof UnaryMinus || $expr instanceof UnaryPlus) {
            $operand = $expr->expr;

            if ($operand instanceof LNumber) {
                return [true, $expr instanceof UnaryMinus ? -$operand->value : $operand->value];
            }

            if ($operand instanceof DNumber) {
                return [true, $expr instanceof UnaryMinus ? -$operand->value : $operand->value];
            }

            return [false, null];
        }

        return [false, null];
    }

    protected function isLiteralArray(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || $item->unpack) {
                return false;
            }

            if ($item->key !== null && ! $item->key instanceof String_ && ! $item->key instanceof LNumber) {
                return false;
            }

            if (! $this->literalArrayValue($item->value)[0]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function literalArray(Array_ $array): array
    {
        $out = [];

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || $item->unpack) {
                continue;
            }

            $value = $this->literalArrayValue($item->value);

            if ($item->key === null) {
                $out[] = $value[1];
            } elseif ($item->key instanceof String_ || $item->key instanceof LNumber) {
                $out[$item->key->value] = $value[1];
            }
        }

        return $out;
    }

    /**
     * @return array{0: bool, 1: mixed}
     */
    protected function literalArrayValue(Expr $expr): array
    {
        if ($expr instanceof Array_ && $this->isLiteralArray($expr)) {
            return [true, $this->literalArray($expr)];
        }

        return $this->literal($expr);
    }

    protected function joined(string $prefix, string $segment): string
    {
        return $prefix === '' ? $segment : $prefix.'.'.$segment;
    }

    /**
     * @return array{handle: string, file: string, line: int}
     */
    protected function opaque(string $handle, string $file, int $line): array
    {
        return [
            'handle' => $handle,
            'file' => $file,
            'line' => $line,
        ];
    }

    protected function shortClass(Name $name): string
    {
        $class = $name->toString();
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * A readable label for a static call target: the short class for a plain
     * class name, or the source line for a computed expression.
     */
    protected function classLabel(Node $class): string
    {
        if ($class instanceof Name) {
            return $this->shortClass($class);
        }

        return 'expression at line '.$class->getStartLine();
    }

    /**
     * Call arguments that carry a value. Static calls may end with a
     * variadic placeholder, which has no ->value.
     *
     * @param  array<int, Arg|Node\VariadicPlaceholder|Node\ArgPlaceholder>  $args
     * @return list<Arg>
     */
    protected function filterArgs(array $args): array
    {
        $values = [];

        foreach ($args as $arg) {
            if ($arg instanceof Arg) {
                $values[] = $arg;
            }
        }

        return $values;
    }

    protected function relative(string $file): string
    {
        $root = rtrim($this->root, '/').'/';

        if (str_starts_with($file, $root)) {
            return substr($file, strlen($root));
        }

        $real = rtrim(realpath($this->root) ?: $this->root, '/').'/';

        if (str_starts_with($file, $real)) {
            return substr($file, strlen($real));
        }

        return basename($file);
    }

    protected function snake(string $value): string
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $value));

        return str_replace('-', '_', $snake);
    }
}
