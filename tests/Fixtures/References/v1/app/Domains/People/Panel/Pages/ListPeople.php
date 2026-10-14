<?php

namespace App\Domains\People\Panel\Pages;

use App\Livewire\PanelPage;
use Streams\Ui\Support\Facades\UI;
use Streams\Ui\Builders\Containers\Grid;
use Streams\Ui\Builders\Headings\Heading;
use Streams\Ui\Livewire\Widgets\Stats\Stat;
use Streams\Ui\Builders\Containers\Container;
use Streams\Ui\Livewire\Tables\InteractsWithTables;
use App\Domains\People\Panel\Actions\AddPersonModal;
use Streams\Ui\Livewire\Actions\InteractsWithActions;
use App\Domains\People\Panel\Actions\PeopleActionsMenu;
use App\Domains\People\Panel\Resources\PeoplePanelResource;
use App\Domains\People\Panel\Tables\PeopleTable as PeopleIndexTable;

class ListPeople extends PanelPage
{
    use InteractsWithActions;
    use InteractsWithTables;

    protected static string $resource = PeoplePanelResource::class;

    protected static bool $registerNavigation = true;

    protected static ?string $navigationGroup = 'People';

    protected static ?string $navigationLabel = 'domains/people.navigation.manage';

    protected static ?int $navigationSort = 1;

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public ?string $import = null;

    public array $peopleStats = [];

    public function boot()
    {
        $this->loadPeopleStats();

        AddPersonModal::register($this);
        PeopleIndexTable::register($this);
    }

    /**
     * Seed Status=Active once per page load. Do not set this in boot()/table setUp —
     * those run on every Livewire request and would fight cleared or changed filters.
     */
    public function mount(): void
    {
        if (! isset($this->data['tables']['people-table']['filters']['status'])) {
            data_set($this->data, 'tables.people-table.filters.status', ['value' => 'active']);
        }
    }

    public function getComponents(): array
    {
        return [
            Container::make('group')
                ->components([
                    Heading::make(__('domains/people.title.plural'))
                        ->actions([
                            AddPersonModal::resolve(),
                            PeopleActionsMenu::make(),
                            // UploadPeopleModal::resolve(),

                        ]),
                    Grid::make('stats')
                        ->columns(4)
                        ->components(function () {
                            return array_map(function ($stat) {
                                return Stat::make($stat['title'], $stat['value'])
                                    ->description($stat['description']);
                            }, $this->peopleStats);
                        }),
                    Container::make()
                        ->components([
                            PeopleIndexTable::resolve(),
                        ]),
                ]),
        ];
    }

    protected function loadPeopleStats(): void
    {
        $organization = UI::getTenant();

        $this->peopleStats = [
            [
                'title' => 'Total People',
                'value' => entries('people')->where('organization_id', $organization->id)->count(),
                'description' => '+15 this month',
            ],
            [
                'title' => 'Active People',
                'value' => entries('people')->where('organization_id', $organization->id)
                    ->where('status', 'active')->count(),
                'description' => '+10 this month',
            ],
            [
                'title' => 'Inactive People',
                'value' => entries('people')->where('organization_id', $organization->id)
                    ->where('status', 'inactive')->count(),
                'description' => '+8%',
            ],
            [
                'title' => __('domains/people.stats.new_people'),
                'value' => entries('people')
                    ->where('organization_id', $organization->id)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->count(),
                'description' => null,
            ],
        ];
    }
}
