<?php

namespace Streams\Sdk\Tests\Support;

use Illuminate\Support\Facades\File;
use Streams\Sdk\Tests\SdkTestCase;
use Streams\Sdk\Support\AppTree;
use Streams\Sdk\Support\AppTreeException;

/**
 * App tree resolver: loads root streams.json plus $ref node files into one
 * tree and resolves any dot path to a node.
 */
class AppTreeTest extends SdkTestCase
{
    protected string $fixture = __DIR__ . '/../../resources/schemas/fixtures/people/streams.json';

    protected string $temp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temp = sys_get_temp_dir() . '/streams-sdk-apptree-' . uniqid();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temp);

        parent::tearDown();
    }

    public function test_it_resolves_every_dot_path_in_the_people_fixture(): void
    {
        $tree = AppTree::load($this->fixture);

        $this->assertSame('stream', $tree->type('people'));
        $this->assertSame('panel', $tree->type('people.panel'));
        $this->assertSame('table', $tree->type('people.table'));
        $this->assertSame('column', $tree->type('people.table.columns.name'));
        $this->assertSame('action', $tree->type('people.actions.add'));
        $this->assertSame('action', $tree->type('people.actions.add.actions.more'));
        $this->assertSame('form', $tree->type('people.actions.add.form'));
        $this->assertSame('section', $tree->type('people.actions.add.form.sections.basics'));
        $this->assertSame('page', $tree->type('people.pages.manage'));
        $this->assertSame('public_page', $tree->type('people.public_pages.directory'));

        $this->assertSame('People', $tree->node('people')['name']);
        $this->assertSame('People table', $tree->node('people.table')['name']);
        $this->assertSame('Name', $tree->node('people.table.columns.name')['label']);
        $this->assertSame('Add person', $tree->node('people.actions.add')['label']);
        $this->assertSame('modal', $tree->node('people.actions.add')['type']);
        $this->assertSame('Basics', $tree->node('people.actions.add.form.sections.basics')['name']);
        $this->assertSame('Manage people', $tree->node('people.pages.manage')['title']);
        $this->assertSame('people', $tree->node('people.public_pages.directory')['path']);
    }

    public function test_it_loads_root_and_ref_files_into_one_tree(): void
    {
        $tree = AppTree::load($this->fixture);

        $this->assertSame('streams/people.json', $tree->file('people'));
        $this->assertSame('streams/people.table.json', $tree->file('people.table'));
        $this->assertSame('streams/people.actions.add.json', $tree->file('people.actions.add'));
        $this->assertSame('streams/people.actions.add.form.json', $tree->file('people.actions.add.form'));

        $this->assertArrayHasKey('streams', $tree->root());
        $this->assertSame('People', $tree->root()['name']);
    }

    public function test_unknown_paths_throw(): void
    {
        $tree = AppTree::load($this->fixture);

        $this->assertFalse($tree->has('people.does_not_exist'));
        $this->assertFalse($tree->has('contacts'));

        $this->expectException(AppTreeException::class);
        $this->expectExceptionMessage('Unknown app tree node [contacts].');

        $tree->node('contacts');
    }

    public function test_duplicate_class_names_in_one_map_throw(): void
    {
        $root = $this->writeTempJson('streams.json', [
            'streams' => [
                'foo_bar' => ['name' => 'Foo Bar', 'fields' => []],
                'foo-bar' => ['name' => 'Foo Bar', 'fields' => []],
            ],
        ]);

        $this->expectException(AppTreeException::class);
        $this->expectExceptionMessage('both derive the StudlyCase class [FooBar]');

        AppTree::load($root);
    }

    public function test_the_same_file_cannot_be_loaded_twice(): void
    {
        $this->writeTempJson('people.json', ['name' => 'People', 'handle' => 'people', 'fields' => []]);

        $root = $this->writeTempJson('streams.json', [
            'streams' => [
                'people' => ['$ref' => 'people.json'],
                'contacts' => ['$ref' => 'people.json'],
            ],
        ]);

        $this->expectException(AppTreeException::class);
        $this->expectExceptionMessage('is already loaded for [people]');

        AppTree::load($root);
    }

    public function test_path_segments_must_be_snake_case_or_slug(): void
    {
        $cases = [
            'People' => ['name' => 'People', 'fields' => []],
            '2fa' => ['name' => 'Two factor', 'fields' => []],
            'user name' => ['name' => 'User', 'fields' => []],
        ];

        foreach ($cases as $handle => $stream) {
            $root = $this->writeTempJson('streams-' . uniqid() . '.json', [
                'streams' => [$handle => $stream],
            ]);

            try {
                AppTree::load($root);

                $this->fail("Segment [{$handle}] should have been rejected.");
            } catch (AppTreeException $e) {
                $this->assertStringContainsString('Invalid path segment', $e->getMessage());
            }
        }
    }

    public function test_studly_case_class_names_derive_from_dot_paths(): void
    {
        $tree = AppTree::load($this->fixture);

        $this->assertSame('People', $tree->classForPath('people'));
        $this->assertSame('PeopleTable', $tree->classForPath('people.table'));
        $this->assertSame('PeopleActionsAdd', $tree->classForPath('people.actions.add'));
        $this->assertSame('PeoplePagesManage', $tree->classForPath('people.pages.manage'));
        $this->assertSame('PeopleActionsAddFormSectionsBasics', $tree->classForPath('people.actions.add.form.sections.basics'));

        $this->assertSame('Add', $tree->classForSegment('add'));
        $this->assertSame('FirstName', $tree->classForSegment('first_name'));
        $this->assertSame('PublicPages', $tree->classForSegment('public_pages'));
    }

    protected function writeTempJson(string $name, array $payload): string
    {
        File::ensureDirectoryExists($this->temp);

        $file = $this->temp . '/' . $name;

        File::put($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $file;
    }
}
