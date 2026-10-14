<?php

namespace Streams\Sdk\Console\Make\Kinds;

use Streams\Sdk\Support\AppTree;

/**
 * Component kinds the SDK ships for the people stream's pages.
 *
 * stream, page, table, and action match the app-tree node types addressed
 * at people, people.pages.manage, people.table, and people.actions.add.
 * manage is the chained page: a manage page, its table, and a modal add action.
 */
class BuiltInKinds
{
    /**
     * @return list<Kind>
     */
    public static function all(): array
    {
        return [
            self::stream(),
            self::page(),
            self::table(),
            self::action(),
            self::manage(),
        ];
    }

    public static function stream(): Kind
    {
        return Kind::define(
            'stream',
            'A stream definition, addressed at the stream handle (people).',
            [
                self::handle('Stream handle'),
                InputField::string('name')->required()->prompt('Stream name')->flag('name'),
                InputField::make('fields', 'array|object')->required()->prompt('Stream fields')->flag('fields'),
                InputField::string('description')->prompt('Stream description')->flag('description'),
            ],
        );
    }

    public static function page(): Kind
    {
        return Kind::define(
            'page',
            'A control panel page, addressed at people.pages.manage.',
            [
                self::handle('Page handle'),
                InputField::string('title')->required()->prompt('Page title')->flag('title'),
                InputField::string('section')->prompt('Panel section')->flag('section'),
                InputField::string('table')->prompt('Table path')->flag('table')
                    ->description('Dot path of the table this page shows.'),
            ],
        );
    }

    public static function table(): Kind
    {
        return Kind::define(
            'table',
            'A table, addressed at people.table.',
            [
                self::handle('Table handle'),
                InputField::string('name')->prompt('Table name')->flag('name'),
                InputField::make('columns', 'object')->prompt('Columns')->flag('columns'),
                InputField::make('actions', 'object')->prompt('Row actions')->flag('actions'),
            ],
        );
    }

    public static function action(): Kind
    {
        return Kind::define(
            'action',
            'An action, addressed at people.actions.add. type modal requires a form.',
            [
                self::handle('Action handle'),
                InputField::string('label')->required()->prompt('Action label')->flag('label'),
                InputField::string('type')->required()->prompt('Action type')->flag('type')->enum([
                    'button',
                    'link',
                    'modal',
                    'dropdown',
                ]),
                InputField::string('target')->prompt('Link target')->flag('target'),
                InputField::make('form', 'string|object')->prompt('Modal form')->flag('form'),
            ],
            requiredWhen: [
                'form' => ['field' => 'type', 'equals' => 'modal'],
            ],
        );
    }

    /**
     * The chained manage page: page + table + modal add action.
     */
    public static function manage(): Kind
    {
        return Kind::define(
            'manage',
            'A manage page chained with its table and a modal add action.',
            [
                InputField::string('title')->required()->prompt('Page title')->flag('title'),
                InputField::string('section')->prompt('Panel section')->flag('section'),
                InputField::string('add_label')->required()->prompt('Add action label')->flag('add-label'),
                InputField::make('form', 'string|object')->required()->prompt('Modal form')->flag('form'),
                InputField::string('table_name')->prompt('Table name')->flag('table-name'),
            ],
            [
                new ChainLink('page', 'pages.manage', [
                    'handle' => 'manage',
                    'title' => '$title',
                    'section' => '$section',
                    'table' => 'table',
                ]),
                new ChainLink('table', 'table', [
                    'handle' => 'table',
                    'name' => '$table_name',
                ]),
                new ChainLink('action', 'actions.add', [
                    'handle' => 'add',
                    'label' => '$add_label',
                    'type' => 'modal',
                    'form' => '$form',
                ]),
            ],
        );
    }

    protected static function handle(string $prompt): InputField
    {
        return InputField::string('handle')
            ->required()
            ->prompt($prompt)
            ->flag('handle')
            ->pattern(AppTree::SEGMENT_PATTERN);
    }
}
