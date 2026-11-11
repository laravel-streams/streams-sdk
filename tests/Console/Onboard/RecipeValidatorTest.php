<?php

namespace Streams\Sdk\Tests\Console\Onboard;

use Streams\Sdk\Console\Onboard\OnboardRecipe;
use Streams\Sdk\Console\Onboard\RecipeValidator;
use Streams\Sdk\Tests\SdkTestCase;

class RecipeValidatorTest extends SdkTestCase
{
    public function test_preset_todos_is_a_valid_document(): void
    {
        $path = RecipeValidator::presetPath('todos');
        $this->assertNotNull($path);

        $document = RecipeValidator::readDocument($path);

        $this->assertSame(1, $document['version']);
        $this->assertSame('Todos', $document['sample_domain']);
    }

    public function test_unknown_keys_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown onboard recipe keys: public_client');

        OnboardRecipe::fromArray([
            'surface' => 'laravel',
            'frontend' => true,
            'public_client' => 'blade-alpine',
        ]);
    }

    public function test_written_recipe_includes_schema_and_validates(): void
    {
        $path = sys_get_temp_dir().'/onboard-recipe-'.uniqid().'.json';
        $recipe = OnboardRecipe::fromArray([
            'surface' => 'laravel',
            'frontend' => true,
            'panels' => 1,
            'api' => true,
            'sample_domain' => 'Todos',
        ]);
        $recipe->write($path);

        $document = RecipeValidator::readDocument($path);
        $this->assertSame('https://streams.dev/schema/onboard.schema.json', $document['$schema']);
        $this->assertTrue($document['frontend']);

        @unlink($path);
    }
}
