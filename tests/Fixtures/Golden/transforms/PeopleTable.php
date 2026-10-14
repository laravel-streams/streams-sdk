<?php

namespace App\Domains\People\Panel\Tables;

use App\Domains\People\Panel\Actions\DeactivateSelectedPeople;
use App\Domains\People\Panel\Actions\DeleteSelectedPeople;
use App\Domains\People\Panel\Actions\RestoreSelectedPeople;
use App\Domains\People\Panel\Resources\PeoplePanelResource;
use App\Domains\Tags\Support\TagPillsHtml;
use Streams\Ui\Builders\Tables\Columns\BadgeColumn;
use Streams\Ui\Builders\Tables\Columns\LinkColumn;
use Streams\Ui\Builders\Tables\Columns\TextColumn;
use Streams\Ui\Builders\Tables\Filters\SelectFilter;
use Streams\Ui\Builders\Tables\Filters\TextFilter;
use Streams\Ui\Builders\Tables\Table;

#[Streams\Sdk\Attributes\Table('people')]
class PeopleTable extends Table
{
    protected function setUp(): void
    {
        DeactivateSelectedPeople::register($this->getLivewire());
        RestoreSelectedPeople::register($this->getLivewire());
        DeleteSelectedPeople::register($this->getLivewire());

        $this
            ->filters([
                TextFilter::make('first_name')
                    ->placeholder(__('domains/people.fields.first_name.label')),
                TextFilter::make('last_name')
                    ->placeholder(__('domains/people.fields.last_name.label')),
                TextFilter::make('email')
                    ->placeholder(__('domains/people.fields.email.label')),
                SelectFilter::make('status')
                    ->label(__('domains/people.fields.status.label'))
                    ->placeholder(__('domains/people.fields.status.label'))
                    ->options([
                        'active' => __('domains/people.fields.status.options.active'),
                        'inactive' => __('domains/people.fields.status.options.inactive'),
                        'invited' => __('domains/people.fields.status.options.invited'),
                    ]),
            ])
            ->columns([
                BadgeColumn::make('status')
                    ->label(__('domains/people.fields.status.label'))
                    ->color(fn ($entry) => match ($entry?->status) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'invited' => 'gray',
                        default => 'gray',
                    })
                    ->title(fn ($entry) => match ($entry?->status) {
                        'active' => __('domains/people.fields.status.options.active'),
                        'inactive' => __('domains/people.fields.status.options.inactive'),
                        'invited' => __('domains/people.fields.status.options.invited'),
                        default => __('domains/people.labels.unknown'),
                    })
                    ->sortable(),
                LinkColumn::make('first_name')
                    ->label(__('domains/people.fields.first_name.label'))
                    ->value(fn ($entry) => $entry->first_name.' '.$entry->last_name)
                    ->url(fn ($entry) => route('streams.ui.admin.people.profile', $entry->id))
                    ->sortable()
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('email')
                    ->sortable()
                    ->searchable()
                    ->label(__('domains/people.fields.email.label')),
                TextColumn::make('phone_number')
                    ->label(__('domains/people.fields.phone_number.label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tags')
                    ->label(__('domains/people.fields.tags.label'))
                    ->value(function ($entry) {
                        $assignments = entries('tag_assignments')
                            ->where('taggable_id', $entry->id)
                            ->where('taggable_type', 'person')
                            ->get();

                        $names = $assignments->map(function ($assignment) {
                            return $assignment->tag()?->name;
                        })->filter();

                        return TagPillsHtml::fromNames($names);
                    }),
            ])
            ->bulkActions([
                DeactivateSelectedPeople::resolve(),
                RestoreSelectedPeople::resolve(),
                DeleteSelectedPeople::resolve(),
            ])
            ->query(PeoplePanelResource::scopedQuery())
            ->emptyStateDescription(__('domains/people.empty_state_description'))
            ->emptyStateIcon('heroicon-o-users');
    }

    public function heading(): string
    {
        return 'People';
    }
}
