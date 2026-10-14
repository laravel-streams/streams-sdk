<?php

namespace App\Domains\People\Panel\Actions;

use Streams\Ui\Support\Facades\UI;
use App\Domains\People\Models\Person;
use Streams\Ui\Notifications\Notification;
use App\Domains\People\Actions\CreatePerson;
use App\Domains\People\Actions\CreatePersonData;
use Streams\Ui\Builders\Actions\MountableAction;
use App\Domains\People\Panel\Forms\PersonCreateForm;

class AddPersonModal extends MountableAction
{
    protected function setUp(): void
    {
        $name = $this->getName();
        PersonCreateForm::register($this->getLivewire());

        $this
            ->label(__('domains/people.actions.add'))
            ->modalWidth('xl')
            ->modalHeading(__('domains/people.actions.add'))
            ->modalComponents(function (): array {
                $form = PersonCreateForm::resolve();

                if (empty($form->getRawState())) {
                    $organization = UI::getTenant();

                    $initialLocation = entries('locations')
                        ->where('organization_id', $organization->id)
                        ->orderBy('id')
                        ->first();

                    $form->fill([
                        ...PersonCreateForm::defaultState(),
                        'location_id' => $initialLocation?->id,
                    ]);
                }

                return [$form];
            })
            ->action(function (MountableAction $action): void {

                $data = $action->getFormData();
                $organization = UI::getTenant();

                dispatch_sync(new CreatePerson(
                    CreatePersonData::fromValidated(
                        validated: $data,
                        organizationId: $organization->id,
                    ),
                ));

                Notification::make()
                    ->title('Person Created')
                    ->description('Person created successfully.')
                    ->duration(5)
                    ->success()
                    ->push($this->livewire);

                PersonCreateForm::resolve()->fill(PersonCreateForm::defaultState());
            })
            ->authorize('create', Person::class)
            ->disabled(fn ($action) => ! $action->isAuthorized())
            ->mergeHtmlAttributes([
                'wire:click' => 'mountAction(\''.$name.'\')',
            ]);
    }
}
