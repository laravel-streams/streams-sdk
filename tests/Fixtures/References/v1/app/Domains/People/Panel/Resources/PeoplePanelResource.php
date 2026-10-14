<?php

namespace App\Domains\People\Panel\Resources;

use App\Domains\People;
use Streams\Ui\Resources\Resource;
use Streams\Ui\Support\Facades\UI;
use Streams\Core\Criteria\Criteria;
use Streams\Ui\Builders\Actions\Action;
use Streams\Ui\Builders\Navigation\Navigation;
use Streams\Ui\Builders\Breadcrumbs\Breadcrumbs;
use Streams\Ui\Builders\Navigation\NavigationItem;

class PeoplePanelResource extends Resource
{
    protected static bool $registerNavigation = false;

    protected static ?string $slug = 'people';

    public static function getPages(): array
    {
        return [
            'index' => People\Panel\Pages\ListPeople::route('/'),

            'groups' => People\Panel\Pages\PersonGroups::route('/{entry}'),
            'attendance' => People\Panel\Pages\PersonAttendance::route('/{entry}/attendance'),
            'comments' => People\Panel\Pages\PersonComments::route('/{entry}/comments'),
            'forms' => People\Panel\Pages\PersonForms::route('/{entry}/forms'),
            'profile' => People\Panel\Pages\PersonProfile::route('/{entry}/profile'),
            'timeline' => People\Panel\Pages\PersonActivity::route('/{entry}/timeline'),
        ];
    }

    public static function navigation(int|string $entryId, ?string $state = null): Navigation
    {
        return Navigation::make('person-navigation')
            ->style('pills')
            ->activeColor('primary')
            ->borderRadius('full')
            ->outlined()
            ->items([
                NavigationItem::make(__('domains/people.navigation.profile'))
                    ->url(route('streams.ui.admin.people.profile', $entryId))
                    ->isActiveWhen(fn () => $state === 'profile'),
                NavigationItem::make(__('domains/groups.title.plural'))
                    ->url(route('streams.ui.admin.people.groups', $entryId))
                    ->isActiveWhen(fn () => $state === 'groups'),
                NavigationItem::make(__('domains/groups.navigation.attendance'))
                    ->url(route('streams.ui.admin.people.attendance', $entryId))
                    ->isActiveWhen(fn () => $state === 'attendance'),
                NavigationItem::make(__('domains/comments.title.plural'))
                    ->url(route('streams.ui.admin.people.comments', $entryId))
                    ->isActiveWhen(fn () => $state === 'comments'),
                NavigationItem::make(__('domains/forms.title.plural'))
                    ->url(route('streams.ui.admin.people.forms', $entryId))
                    ->isActiveWhen(fn () => $state === 'forms'),
                NavigationItem::make(__('domains/people.navigation.timeline'))
                    ->url(route('streams.ui.admin.people.timeline', $entryId))
                    ->isActiveWhen(fn () => $state === 'timeline'),
            ]);
    }

    public static function breadcrumbs(): Breadcrumbs
    {
        return Breadcrumbs::make([
            Action::make(__('domains/people.actions.back_to_people'))
                ->icon('heroicon-o-arrow-left')
                ->htmlAttributes(['iconPosition' => 'before'])
                ->link(route('streams.ui.admin.people.index')),
        ]);
    }

    public static function scopedQuery(): Criteria
    {
        $organization = UI::getTenant();

        return entries('people')
            ->where('organization_id', $organization->id);
    }

    public static function kraftwerk($householdId, $personId): Criteria
    {
        $query = entries('household_members');

        if ($householdId) {
            return $query->where('household_id', $householdId);
        }

        return $query->where('person_id', (int) $personId);
    }

    /**
     * Page H1 for person detail screens — first + last name.
     */
    public static function personHeadingTitle(object $person): string
    {
        $name = trim(implode(' ', array_filter([
            trim((string) ($person->first_name ?? '')),
            trim((string) ($person->last_name ?? '')),
        ])));

        return $name !== ''
            ? $name
            : __('domains/people.labels.unknown');
    }
}
