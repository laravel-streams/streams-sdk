<?php

namespace Streams\Sdk\Tests\Attributes;

use ReflectionClass;
use ReflectionMethod;
use Streams\Sdk\Attributes\Generated;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * #[Generated('dot.path')] lives in streams-sdk and records the app-tree path.
 */
class GeneratedTest extends SdkTestCase
{
    public function test_the_attribute_records_the_dot_path_on_a_class_and_a_method(): void
    {
        $class = new ReflectionClass(GeneratedFixture::class);
        $classAttribute = $class->getAttributes(Generated::class);

        $this->assertCount(1, $classAttribute);
        $this->assertSame('people.actions.add', $classAttribute[0]->newInstance()->path);

        $method = new ReflectionMethod(GeneratedFixture::class, 'label');
        $methodAttribute = $method->getAttributes(Generated::class);

        $this->assertCount(1, $methodAttribute);
        $this->assertSame('people.actions.add', $methodAttribute[0]->newInstance()->path);
    }

    public function test_path_from_source_reads_the_attribute_streams_make_writes(): void
    {
        $source = <<<'PHP'
#[Generated('people.actions.add')]
class PeopleActionsAdd
{
}
PHP;

        $this->assertSame('people.actions.add', Generated::pathFromSource($source));
        $this->assertNull(Generated::pathFromSource("class Untagged\n{\n}\n"));
    }
}

#[Generated('people.actions.add')]
class GeneratedFixture
{
    #[Generated('people.actions.add')]
    public function label(): string
    {
        return 'Add person';
    }
}
