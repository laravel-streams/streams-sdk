<?php

namespace App\Domains\People\Panel\Pages;

use App\Domains\People\Panel\Actions\PeopleActionsAdd as AddPersonModal;
use App\Domains\People\Panel\Tables\PeopleTable;
use App\Livewire\PanelPage;
use Streams\Ui\Builders\Containers\Container;
use Streams\Ui\Builders\Headings\Heading;
use Streams\Ui\Livewire\Actions\InteractsWithActions;
use Streams\Ui\Livewire\Tables\InteractsWithTables;

class PeoplePagesManage extends PanelPage
{
    use InteractsWithActions;
    use InteractsWithTables;

    // Keep the author's page state and unrelated behavior.
    public array $peopleStats = [];

    protected string $pendingAction = AddPersonModal::class;

    public function boot(): void
    {
        PeopleTable::register($this);
    }

    public function getComponents(): array
    {
        return [
            Container::make('group')
                ->components([
                    Heading::make('Manage people')
                        ->actions([
                            // The add action belongs here.
                            ImportPeopleModal::resolve(),
                            PeopleActionsMenu::make(),
                        ]),
                    PeopleTable::resolve(),
                ]),
        ];
    }

    protected function loadPeopleStats(): void
    {
        $this->peopleStats = ['total' => entries('people')->count()];
    }
}
