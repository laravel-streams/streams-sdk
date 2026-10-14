<?php

namespace Streams\Sdk\Tests\Console\Make\Kinds;

use Streams\Sdk\Console\Make\Kinds\BuiltInKinds;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * Built-in kinds for the people pages, including the chained manage page
 * and a modal add action.
 */
class BuiltInKindsTest extends SdkTestCase
{
    protected string $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixture = dirname(__DIR__, 4).'/resources/schemas/fixtures/people/streams.json';
    }

    public function test_built_in_kinds_cover_the_people_pages(): void
    {
        $registry = KindRegistry::withBuiltIns();

        $this->assertSame('stream', $registry->forType('stream')->name);
        $this->assertSame('page', $registry->forType('page')->name);
        $this->assertSame('table', $registry->forType('table')->name);
        $this->assertSame('action', $registry->forType('action')->name);
        $this->assertSame('manage', $registry->get('manage')->name);

        $this->assertContains('modal', $registry->get('action')->input('type')->enum);
        $this->assertCount(3, $registry->get('manage')->expand([
            'title' => 'Manage people',
            'add_label' => 'Add person',
            'form' => 'people.actions.add.form',
        ]));
    }

    public function test_the_people_fixture_matches_each_built_in_kind(): void
    {
        $tree = AppTree::load($this->fixture);
        $registry = KindRegistry::withBuiltIns();

        $this->assertSame('stream', $registry->forPath($tree, 'people')->name);
        $this->assertSame('page', $registry->forPath($tree, 'people.pages.manage')->name);
        $this->assertSame('table', $registry->forPath($tree, 'people.table')->name);
        $this->assertSame('action', $registry->forPath($tree, 'people.actions.add')->name);
        $this->assertSame('modal', $tree->node('people.actions.add')['type']);

        $checked = [];

        foreach ($tree->types() as $path => $type) {
            $kind = $registry->forType($type);

            if ($kind === null || $path === '') {
                continue;
            }

            $errors = $kind->validate($tree->node($path));

            $this->assertSame([], $errors, $path.' '.json_encode($errors));
            $checked[] = $path;
        }

        $this->assertContains('people', $checked);
        $this->assertContains('people.pages.manage', $checked);
        $this->assertContains('people.table', $checked);
        $this->assertContains('people.actions.add', $checked);
        $this->assertContains('people.table.actions.edit', $checked);
    }

    public function test_manage_chains_a_page_a_table_and_a_modal_add_action(): void
    {
        $steps = BuiltInKinds::manage()->expand([
            'title' => 'Manage people',
            'section' => 'people',
            'add_label' => 'Add person',
            'form' => ['$ref' => 'streams/people.actions.add.form.json'],
        ]);

        $this->assertSame('page', $steps[0]['kind']);
        $this->assertSame('pages.manage', $steps[0]['path']);
        $this->assertSame('Manage people', $steps[0]['input']['title']);
        $this->assertSame('table', $steps[0]['input']['table']);

        $this->assertSame('table', $steps[1]['kind']);
        $this->assertSame('table', $steps[1]['path']);
        $this->assertSame('table', $steps[1]['input']['handle']);
        $this->assertArrayNotHasKey('name', $steps[1]['input']);

        $this->assertSame('action', $steps[2]['kind']);
        $this->assertSame('actions.add', $steps[2]['path']);
        $this->assertSame('modal', $steps[2]['input']['type']);
        $this->assertSame('Add person', $steps[2]['input']['label']);
        $this->assertSame(
            ['$ref' => 'streams/people.actions.add.form.json'],
            $steps[2]['input']['form'],
        );
    }
}
