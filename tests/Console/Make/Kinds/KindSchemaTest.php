<?php

namespace Streams\Sdk\Tests\Console\Make\Kinds;

use Streams\Sdk\Console\Make\Kinds\BuiltInKinds;
use Streams\Sdk\Console\Make\Kinds\Kind;
use Streams\Sdk\Console\Make\Kinds\KindRegistry;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * Each kind's input schema validates input and names the field on errors.
 */
class KindSchemaTest extends SdkTestCase
{
    public function test_stream_errors_name_the_missing_fields(): void
    {
        $errors = BuiltInKinds::stream()->validate([
            'handle' => 'People',
        ]);

        $this->assertSame(['handle', 'name', 'fields'], $this->fields($errors));
        $this->assertStringContainsString('name', $this->message($errors, 'name'));
        $this->assertStringContainsString('fields', $this->message($errors, 'fields'));
        $this->assertStringContainsString('handle', $this->message($errors, 'handle'));
    }

    public function test_page_errors_name_title(): void
    {
        $errors = BuiltInKinds::page()->validate([
            'handle' => 'manage',
        ]);

        $this->assertSame(['title'], $this->fields($errors));
        $this->assertSame('The title field is required.', $this->message($errors, 'title'));
    }

    public function test_table_errors_name_handle(): void
    {
        $errors = BuiltInKinds::table()->validate([
            'name' => 'People table',
            'columns' => ['name' => ['handle' => 'name']],
        ]);

        $this->assertSame(['handle'], $this->fields($errors));
        $this->assertStringContainsString('handle', $this->message($errors, 'handle'));
    }

    public function test_table_rejects_a_handle_that_is_not_a_path_segment(): void
    {
        $errors = BuiltInKinds::table()->validate([
            'handle' => 'People Table',
        ]);

        $this->assertSame(['handle'], $this->fields($errors));
        $this->assertStringContainsString('handle', $this->message($errors, 'handle'));
    }

    public function test_action_errors_name_label_and_type(): void
    {
        $errors = BuiltInKinds::action()->validate([
            'handle' => 'add',
            'type' => 'nope',
        ]);

        $this->assertSame(['label', 'type'], $this->fields($errors));
        $this->assertSame('The label field is required.', $this->message($errors, 'label'));
        $this->assertStringContainsString('type', $this->message($errors, 'type'));
        $this->assertStringContainsString('modal', $this->message($errors, 'type'));
    }

    public function test_a_modal_action_requires_form_and_names_that_field(): void
    {
        $kind = BuiltInKinds::action();

        $missing = $kind->validate([
            'handle' => 'add',
            'label' => 'Add person',
            'type' => 'modal',
        ]);

        $this->assertSame(['form'], $this->fields($missing));
        $this->assertSame(
            'The form field is required when type is modal.',
            $this->message($missing, 'form'),
        );

        $this->assertSame([], $kind->validate([
            'handle' => 'add',
            'label' => 'Add person',
            'type' => 'modal',
            'form' => ['$ref' => 'streams/people.actions.add.form.json'],
        ]));

        $this->assertSame([], $kind->validate([
            'handle' => 'edit',
            'label' => 'Edit',
            'type' => 'link',
        ]));
    }

    public function test_action_type_must_be_a_string_and_the_error_names_type(): void
    {
        $errors = BuiltInKinds::action()->validate([
            'handle' => 'add',
            'label' => 'Add person',
            'type' => 1,
        ]);

        $this->assertSame(['type'], $this->fields($errors));
        $this->assertStringContainsString('type', $this->message($errors, 'type'));
    }

    public function test_manage_errors_name_title_add_label_and_form(): void
    {
        $errors = BuiltInKinds::manage()->validate([
            'section' => 'people',
        ]);

        $this->assertSame(['title', 'add_label', 'form'], $this->fields($errors));
        $this->assertStringContainsString('title', $this->message($errors, 'title'));
        $this->assertStringContainsString('add_label', $this->message($errors, 'add_label'));
        $this->assertStringContainsString('form', $this->message($errors, 'form'));
    }

    public function test_a_valid_manage_chain_validates_the_page_table_and_modal_action(): void
    {
        $registry = KindRegistry::withBuiltIns();
        $input = [
            'title' => 'Manage people',
            'section' => 'people',
            'add_label' => 'Add person',
            'form' => 'people.actions.add.form',
            'table_name' => 'People table',
        ];

        $this->assertSame([], $registry->get('manage')->validate($input));

        $steps = $registry->get('manage')->expandUnder('people', $input);

        $this->assertSame([
            ['kind' => 'page', 'path' => 'people.pages.manage'],
            ['kind' => 'table', 'path' => 'people.table'],
            ['kind' => 'action', 'path' => 'people.actions.add'],
        ], array_map(fn (array $step): array => [
            'kind' => $step['kind'],
            'path' => $step['path'],
        ], $steps));

        $this->assertSame('table', $steps[0]['input']['table']);
        $this->assertSame('modal', $steps[2]['input']['type']);

        foreach ($steps as $step) {
            $errors = $registry->get($step['kind'])->validate($step['input']);

            $this->assertSame([], $errors, $step['path'].' '.json_encode($errors));
        }
    }

    public function test_unknown_keys_are_ignored(): void
    {
        $this->assertSame([], BuiltInKinds::page()->validate([
            'handle' => 'manage',
            'title' => 'Manage people',
            'extra' => true,
        ]));
    }

    /**
     * @param  list<array{field: string, message: string}>  $errors
     * @return list<string>
     */
    protected function fields(array $errors): array
    {
        return array_column($errors, 'field');
    }

    /**
     * @param  list<array{field: string, message: string}>  $errors
     */
    protected function message(array $errors, string $field): string
    {
        foreach ($errors as $error) {
            if ($error['field'] === $field) {
                return $error['message'];
            }
        }

        $this->fail("No error named the {$field} field.");
    }
}
