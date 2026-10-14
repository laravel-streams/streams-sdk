<?php

namespace Streams\Sdk\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Minimal JSON Schema (draft-07) validator for the keyword subset the
 * app-definition schemas use: type, enum, pattern, required, properties,
 * additionalProperties, items, oneOf, allOf and $ref.
 *
 * $ref ids are the schemas' $id URLs; a directory of schema files is indexed
 * by $id on construction.
 */
class SchemaValidator
{
    /**
     * @var array<string, \stdClass>
     */
    protected array $schemas = [];

    public function __construct(string $schemaDir)
    {
        foreach ($this->schemaFiles($schemaDir) as $file) {
            $schema = json_decode((string) file_get_contents($file));

            if (isset($schema->{'$id'}) && is_string($schema->{'$id'})) {
                $this->schemas[$schema->{'$id'}] = $schema;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function schemaFiles(string $dir): array
    {
        $files = glob(rtrim($dir, '/') . '/*.json') ?: [];

        foreach (glob(rtrim($dir, '/') . '/nodes/*.json') ?: [] as $file) {
            $files[] = $file;
        }

        return $files;
    }

    public function assertValid(mixed $data, string $schemaId, string $path = '$'): void
    {
        if (!isset($this->schemas[$schemaId])) {
            Assert::fail("Unknown schema [{$schemaId}].");
        }

        $this->validate($data, $this->schemas[$schemaId], $path);
    }

    protected function resolve(\stdClass $schema): \stdClass
    {
        if (isset($schema->{'$ref'}) && is_string($schema->{'$ref'})) {
            $id = $schema->{'$ref'};

            if (!isset($this->schemas[$id])) {
                Assert::fail("Unresolved \$ref [{$id}].");
            }

            return $this->schemas[$id];
        }

        return $schema;
    }

    protected function validate(mixed $data, \stdClass $schema, string $path): void
    {
        $schema = $this->resolve($schema);

        if (isset($schema->allOf)) {
            foreach ($schema->allOf as $index => $sub) {
                $this->validate($data, $sub, "{$path}.allOf[{$index}]");
            }
        }

        if (isset($schema->enum)) {
            Assert::assertContains($data, $schema->enum, "{$path}: not one of the enum values.");
        }

        if (isset($schema->type)) {
            $types = is_array($schema->type) ? $schema->type : [$schema->type];

            $actual = match (true) {
                is_bool($data) => 'boolean',
                is_int($data) => 'integer',
                is_float($data) => 'number',
                is_string($data) => 'string',
                is_array($data) => 'array',
                $data instanceof \stdClass => 'object',
                $data === null => 'null',
            };

            Assert::assertContains($actual, $types, "{$path}: expected type(s) " . implode(', ', $types) . ", got {$actual}.");
        }

        if (isset($schema->pattern)) {
            Assert::assertMatchesRegularExpression(
                '/' . str_replace('/', '\/', $schema->pattern) . '/',
                (string) $data,
                "{$path}: pattern mismatch."
            );
        }

        if ($data instanceof \stdClass) {
            if (isset($schema->required)) {
                foreach ($schema->required as $key) {
                    Assert::assertTrue(property_exists($data, $key), "{$path}: missing required property [{$key}].");
                }
            }

            foreach ($schema->properties ?? [] as $key => $propertySchema) {
                if (property_exists($data, $key)) {
                    $this->validate($data->{$key}, $propertySchema, "{$path}.{$key}");
                }
            }

            if (isset($schema->additionalProperties) && $schema->additionalProperties === false) {
                foreach (get_object_vars($data) as $key => $value) {
                    Assert::assertTrue(
                        isset($schema->properties->{$key}),
                        "{$path}: unexpected property [{$key}]."
                    );
                }
            }

            if (isset($schema->oneOf)) {
                $matched = false;

                foreach ($schema->oneOf as $index => $option) {
                    try {
                        $this->validate($data, $option, "{$path}.oneOf[{$index}]");

                        $matched = true;

                        break;
                    } catch (\PHPUnit\Framework\ExpectationFailedException $e) {
                        // Try the next oneOf branch.
                    }
                }

                if (!$matched) {
                    Assert::fail("{$path}: matched no oneOf branch.");
                }
            }
        } elseif (is_array($data) && isset($schema->items)) {
            foreach (array_values($data) as $index => $item) {
                $this->validate($item, $schema->items, "{$path}[{$index}]");
            }
        }
    }
}
