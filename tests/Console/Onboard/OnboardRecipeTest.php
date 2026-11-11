<?php

namespace Streams\Sdk\Tests\Console\Onboard;

use PHPUnit\Framework\TestCase;
use Streams\Sdk\Console\Onboard\OnboardRecipe;

class OnboardRecipeTest extends TestCase
{
    public function test_kitchen_sink_plans_ui_api_domain_steps(): void
    {
        $recipe = OnboardRecipe::fromArray([
            'surface' => 'laravel',
            'frontend' => true,
            'panels' => 1,
            'multi_tenancy' => true,
            'api' => true,
            'discover_streams_api' => false,
            'streams_api_base_url' => null,
            'sample_domain' => 'Todos',
            'created_at' => '2026-01-01T00:00:00+00:00',
        ]);

        $ids = array_column($recipe->plannedSteps(), 'id');

        $this->assertContains('require-streams-ui', $ids);
        $this->assertContains('require-streams-api', $ids);
        $this->assertContains('alpine-entry', $ids);
        $this->assertContains('tenancy', $ids);
        $this->assertContains('domain-Todos', $ids);
    }

    public function test_js_surface_marks_path_two(): void
    {
        $recipe = OnboardRecipe::fromArray([
            'surface' => 'js',
            'frontend' => true,
            'panels' => 0,
            'multi_tenancy' => false,
            'api' => true,
            'discover_streams_api' => true,
            'streams_api_base_url' => 'https://api.example.test',
            'sample_domain' => null,
            'created_at' => '2026-01-01T00:00:00+00:00',
        ]);

        $steps = $recipe->plannedSteps();
        $surface = null;
        $api = null;
        foreach ($steps as $step) {
            if ($step['id'] === 'surface') {
                $surface = $step;
            }
            if ($step['id'] === 'require-streams-api') {
                $api = $step;
            }
        }

        $this->assertNotNull($surface);
        $this->assertStringContainsString('Path 2', $surface['note']);
        $this->assertNotNull($api);
        $this->assertStringContainsString('api.example.test', $api['note']);
    }
}
