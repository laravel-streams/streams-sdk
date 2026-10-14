<?php

namespace Streams\Sdk\Console\Capture;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
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

    public function __construct(protected string $root) {}

    /**
     * Capture the reference layout into an app tree payload.
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

        $container = match ($kind) {
            'table' => 'table',
            'panel' => 'panel',
            'page' => 'pages',
            'action' => 'actions',
            'form' => 'forms',
            'public_page' => 'public_pages',
            default => null,
        };

        if ($container === null) {
            return null;
        }

        return [$stream, $container, $handle, $kind];
    }

    /**
     * Place a captured node under its stream's ui subtree.
     *
     * @param  array<string, mixed>  $stream
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
            return null;
        }

        $class = $this->findClass($statements);

        if ($class === null) {
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
     * @param  list<Node>  $statements
     */
    protected function findClass(array $statements): ?Class_
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Class_) {
                return $statement;
            }

            if ($statement instanceof \PhpParser\Node\Stmt\Namespace_) {
                $class = $this->findClass($statement->stmts ?? []);

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

            [$value, $entries] = $this->literalOrOpaque($prop->default, $file, $name);

            if ($value !== null) {
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
            if ($stmt instanceof Expression && $stmt->expr instanceof Expr) {
                $this->chain($stmt->expr, $file, '', $settings, $opaque);
            }

            if ($stmt instanceof Return_ && $stmt->expr instanceof Expr) {
                $this->chain($stmt->expr, $file, $method->name->toString(), $settings, $opaque);
            }
        }
    }

    /**
     * Walk a builder call chain. Literal arguments become settings; closures,
     * callbacks, resolvers and computed values become opaque nodes.
     *
     * @param  array<string, mixed>  $settings
     * @param  list<array{handle: string, file: string, line: int}>  $opaque
     */
    protected function chain(Expr $expr, string $file, string $prefix, array &$settings, array &$opaque): void
    {
        if ($expr instanceof MethodCall) {
            // Deeper in the chain first: ->filters([...])->columns([...]).
            $this->chain($expr->var, $file, $prefix, $settings, $opaque);

            $method = $expr->name instanceof Node\Identifier ? $expr->name->toString() : null;

            if ($method !== null) {
                $this->call($method, $expr->args, $file, $prefix, $settings, $opaque);
            }

            return;
        }

        if ($expr instanceof StaticCall) {
            $method = $expr->name instanceof Node\Identifier ? $expr->name->toString() : null;

            // X::make('handle') names the builder item already captured by the
            // container walker, so the make call itself is just a marker.
            if ($method === 'make' && isset($expr->args[0]) && $expr->args[0]->value instanceof String_) {
                $settings[$this->joined($prefix, 'make')] = true;

                return;
            }

            // A resolver/route class call is computed: opaque.
            $opaque[] = $this->opaque($this->joined($prefix, $this->shortClass($expr->class)), $file, $expr->getStartLine());

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

        // Translation, route, criteria and other computed values are opaque.
        $opaque[] = $this->opaque($prefix, $file, $expr->getStartLine());
    }

    /**
     * @param  list<\PhpParser\Node\Arg>  $args
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

        // Multiple arguments: keep them when every argument is a literal.
        $values = [];

        foreach ($args as $arg) {
            [$value, $entries] = $this->literalOrOpaque($arg->value, $file, $key);

            if ($value !== null) {
                $values[] = $value;
            }

            foreach ($entries as $entry) {
                $opaque[] = $entry;
            }
        }

        if (count($values) === count($args)) {
            $settings[$key] = $values;
        }
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
                if (! $item instanceof ArrayItem || ! $item->value instanceof Expr) {
                    continue;
                }

                $itemKey = $this->itemHandle($item, (string) $index);

                $this->chain($item->value, $file, $this->joined($key, $itemKey), $settings, $opaque);
            }

            return;
        }

        [$value, $entries] = $this->literalOrOpaque($arg, $file, $key);

        if ($value !== null) {
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
            if (isset($value->args[0]) && $value->args[0]->value instanceof String_) {
                return $value->args[0]->value->value;
            }
        }

        return $index;
    }

    /**
     * A literal value or opaque entries for a non-literal expression.
     *
     * @return array{0: mixed, 1: list<array{handle: string, file: string, line: int}>}
     */
    protected function literalOrOpaque(Expr $expr, string $file, string $key): array
    {
        $value = $this->scalar($expr);

        if ($value !== null) {
            return [$value, []];
        }

        if ($expr instanceof Array_ && $this->isLiteralArray($expr)) {
            return [$this->literalArray($expr), []];
        }

        return [null, [$this->opaque($key, $file, $expr->getStartLine())]];
    }

    protected function scalar(Expr $expr): mixed
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if ($expr instanceof LNumber) {
            return $expr->value;
        }

        if ($expr instanceof DNumber) {
            return $expr->value;
        }

        if ($expr instanceof ConstFetch) {
            $name = $expr->name->toString();

            if ($name === 'true') {
                return true;
            }

            if ($name === 'false') {
                return false;
            }

            if ($name === 'null') {
                return null;
            }
        }

        return null;
    }

    protected function isLiteralArray(Array_ $array): bool
    {
        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->value instanceof Expr) {
                return false;
            }

            if ($item->key !== null && ! $item->key instanceof String_ && ! $item->key instanceof LNumber) {
                return false;
            }

            if ($this->literalArrayValue($item->value) === null) {
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
            if (! $item instanceof ArrayItem || ! $item->value instanceof Expr) {
                continue;
            }

            $value = $this->literalArrayValue($item->value);

            if ($item->key === null) {
                $out[] = $value;
            } elseif ($item->key instanceof String_ || $item->key instanceof LNumber) {
                $out[$item->key->value] = $value;
            }
        }

        return $out;
    }

    protected function literalArrayValue(Expr $expr): mixed
    {
        $value = $this->scalar($expr);

        if ($value !== null) {
            return $value;
        }

        if ($expr instanceof Array_ && $this->isLiteralArray($expr)) {
            return $this->literalArray($expr);
        }

        return null;
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
