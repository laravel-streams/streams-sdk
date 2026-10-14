<?php

namespace Streams\Sdk\Tests\Console\Make\Kinds;

use Illuminate\Support\Facades\File;
use Streams\Sdk\Console\Make\Kinds\BuiltInKinds;
use Streams\Sdk\Console\Make\Kinds\InputField;
use Streams\Sdk\Console\Make\Kinds\Kind;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Console\Make\Kinds\KindRegistryException;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * The registry accepts kinds from the SDK, an app, and a package.
 * Registration is data. It does not add a command.
 */
class KindRegistryTest extends SdkTestCase
{
    public function test_the_sdk_app_and_a_package_can_register_kinds(): void
    {
        $registry = new KindRegistry;
        $registry->registerFromSdk(BuiltInKinds::page());
        $registry->registerFromApp($this->reportKind());
        $registry->registerFromPackage($this->badgeKind(), 'example/badges');

        $this->assertSame('sdk', $registry->get('page')->source);
        $this->assertNull($registry->get('page')->package);

        $this->assertSame('app', $registry->get('report')->source);
        $this->assertSame('package', $registry->get('badge')->source);
        $this->assertSame('example/badges', $registry->get('badge')->package);

        $this->assertSame(['page'], array_map(fn (Kind $kind): string => $kind->name, $registry->from('sdk')));
        $this->assertSame(['report'], array_map(fn (Kind $kind): string => $kind->name, $registry->from('app')));
        $this->assertSame(['badge'], array_map(fn (Kind $kind): string => $kind->name, $registry->from('package')));
    }

    public function test_built_in_kinds_are_registered_from_the_sdk(): void
    {
        $registry = KindRegistry::withBuiltIns();

        $this->assertSame(
            ['stream', 'page', 'table', 'action', 'manage'],
            $registry->names(),
        );

        foreach ($registry->all() as $kind) {
            $this->assertSame('sdk', $kind->source);
            $this->assertNull($kind->package);
        }
    }

    public function test_config_registers_app_and_package_kinds_on_the_shared_registry(): void
    {
        config([
            'streams.sdk.make.kinds' => [
                'app' => [$this->reportKind()],
                'packages' => [
                    'example/badges' => [$this->badgeKind()],
                ],
            ],
        ]);

        $this->app->forgetInstance(KindRegistry::class);

        $registry = $this->app->make(KindRegistry::class);

        $this->assertSame('sdk', $registry->get('stream')->source);
        $this->assertSame('app', $registry->get('report')->source);
        $this->assertSame('package', $registry->get('badge')->source);
        $this->assertSame('example/badges', $registry->get('badge')->package);
    }

    public function test_a_kind_schema_exposes_prompts_flags_and_json_keys(): void
    {
        $action = BuiltInKinds::action();

        $this->assertSame(
            ['handle', 'label', 'type', 'target', 'form'],
            $action->keys(),
        );
        $this->assertSame('Action label', $action->prompts()['label']);
        $this->assertSame('label', $action->flags()['label']);
        $this->assertSame('form', $action->flags()['form']);
        $this->assertSame('add_label', BuiltInKinds::manage()->flags()['add-label']);
    }

    public function test_duplicate_names_and_unknown_kinds_are_errors(): void
    {
        $registry = new KindRegistry;
        $registry->registerFromApp($this->reportKind());

        $this->expectException(KindRegistryException::class);
        $this->expectExceptionMessage('Component kind [report] is already registered.');

        $registry->registerFromPackage($this->reportKind(), 'example/reports');
    }

    public function test_an_unknown_kind_names_the_missing_kind(): void
    {
        $registry = KindRegistry::withBuiltIns();

        $this->expectException(KindRegistryException::class);
        $this->expectExceptionMessage('Unknown component kind [badge].');

        $registry->get('badge');
    }

    public function test_a_package_placement_adds_a_tree_container_without_replacing_built_ins(): void
    {
        $registry = KindRegistry::withBuiltIns();
        $registry->registerFromPackage($this->badgeKind(), 'example/badges');

        $containers = $registry->treeContainers();

        $this->assertSame([
            'type' => 'badges',
            'map' => true,
            'map_of' => 'badge',
        ], $containers['ui']['badges']);
        $this->assertArrayNotHasKey('pages', $containers['ui'] ?? []);

        $root = sys_get_temp_dir().'/streams-sdk-kind-tree-'.uniqid();
        mkdir($root);

        try {
            file_put_contents($root.'/streams.json', json_encode([
                'name' => 'People',
                'streams' => [
                    'people' => [
                        'name' => 'People',
                        'handle' => 'people',
                        'fields' => [],
                        'ui' => [
                            'badges' => [
                                'star' => [
                                    'handle' => 'star',
                                    'label' => 'Star',
                                ],
                            ],
                        ],
                    ],
                ],
            ]));

            $tree = AppTree::load($root.'/streams.json', $containers);

            $this->assertSame('badge', $tree->type('people.badges.star'));
            $this->assertSame('badge', $registry->forPath($tree, 'people.badges.star')->name);
            $this->assertSame('Star', $tree->node('people.badges.star')['label']);
        } finally {
            File::deleteDirectory($root);
        }
    }

    protected function reportKind(): Kind
    {
        return Kind::define('report', 'An app report.', [
            InputField::string('handle')->required()->prompt('Report handle')->flag('handle'),
            InputField::string('title')->required()->prompt('Report title')->flag('title'),
        ]);
    }

    protected function badgeKind(): Kind
    {
        return Kind::define('badge', 'A package badge.', [
            InputField::string('handle')->required()->prompt('Badge handle')->flag('handle'),
            InputField::string('label')->required()->prompt('Badge label')->flag('label'),
        ])->placedOn('ui', 'badges', map: true, mapOf: 'badge');
    }
}
