# Task 139 — Litmus: capture → rebuild → opaque share

Code-bearing nodes: **4**
Opaque nodes (not rebuildable from JSON): **4**
Opaque share: **100.0%** (4/4)

Every code-bearing node is either rebuilt from the captured JSON
snapshot or is a deliberately opaque node (a captured closure,
permission callback or computed option) that `streams:make` never
rewrites. The opaque nodes below are toolkit gap candidates:

- [ ] `DeactivateSelectedPeople` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:21) — toolkit gap candidate
- [ ] `RestoreSelectedPeople` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:22) — toolkit gap candidate
- [ ] `DeleteSelectedPeople` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:23) — toolkit gap candidate
- [ ] `filters.first_name.placeholder` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:28) — toolkit gap candidate
- [ ] `filters.last_name.placeholder` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:30) — toolkit gap candidate
- [ ] `filters.email.placeholder` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:32) — toolkit gap candidate
- [ ] `filters.status.label` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:34) — toolkit gap candidate
- [ ] `filters.status.placeholder` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:35) — toolkit gap candidate
- [ ] `filters.status.options.active` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:37) — toolkit gap candidate
- [ ] `filters.status.options.inactive` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:38) — toolkit gap candidate
- [ ] `filters.status.options.invited` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:39) — toolkit gap candidate
- [ ] `columns.status.label` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:44) — toolkit gap candidate
- [ ] `columns.status.color` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:45) — toolkit gap candidate
- [ ] `columns.status.title` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:51) — toolkit gap candidate
- [ ] `columns.first_name.label` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:59) — toolkit gap candidate
- [ ] `columns.first_name.value` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:60) — toolkit gap candidate
- [ ] `columns.first_name.url` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:61) — toolkit gap candidate
- [ ] `columns.email.label` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:67) — toolkit gap candidate
- [ ] `columns.phone_number.label` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:69) — toolkit gap candidate
- [ ] `columns.tags.label` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:73) — toolkit gap candidate
- [ ] `columns.tags.value` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:74) — toolkit gap candidate
- [ ] `bulkActions.0.DeactivateSelectedPeople` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:88) — toolkit gap candidate
- [ ] `bulkActions.1.RestoreSelectedPeople` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:89) — toolkit gap candidate
- [ ] `bulkActions.2.DeleteSelectedPeople` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:90) — toolkit gap candidate
- [ ] `query` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:92) — toolkit gap candidate
- [ ] `emptyStateDescription` — `people.table` (app/Domains/People/Panel/Tables/PeopleTable.php:93) — toolkit gap candidate
- [ ] `resource` — `people.pages.list_people` (app/Domains/People/Panel/Pages/ListPeople.php:23) — toolkit gap candidate
- [ ] `getComponents` — `people.pages.list_people` (app/Domains/People/Panel/Pages/ListPeople.php:67) — toolkit gap candidate
- [ ] `PersonCreateForm` — `people.actions.add_person_modal` (app/Domains/People/Panel/Actions/AddPersonModal.php:18) — toolkit gap candidate
- [ ] `label` — `people.actions.add_person_modal` (app/Domains/People/Panel/Actions/AddPersonModal.php:21) — toolkit gap candidate
- [ ] `modalHeading` — `people.actions.add_person_modal` (app/Domains/People/Panel/Actions/AddPersonModal.php:23) — toolkit gap candidate
- [ ] `modalComponents` — `people.actions.add_person_modal` (app/Domains/People/Panel/Actions/AddPersonModal.php:24) — toolkit gap candidate
- [ ] `action` — `people.actions.add_person_modal` (app/Domains/People/Panel/Actions/AddPersonModal.php:43) — toolkit gap candidate
- [ ] `authorize` — `people.actions.add_person_modal` (app/Domains/People/Panel/Actions/AddPersonModal.php:64) — toolkit gap candidate
- [ ] `disabled` — `people.actions.add_person_modal` (app/Domains/People/Panel/Actions/AddPersonModal.php:65) — toolkit gap candidate
- [ ] `mergeHtmlAttributes.wire:click` — `people.actions.add_person_modal` (app/Domains/People/Panel/Actions/AddPersonModal.php:67) — toolkit gap candidate
- [ ] `getPages` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:22) — toolkit gap candidate
- [ ] `navigation.items.0.name` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:42) — toolkit gap candidate
- [ ] `navigation.items.0.url` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:43) — toolkit gap candidate
- [ ] `navigation.items.0.isActiveWhen` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:44) — toolkit gap candidate
- [ ] `navigation.items.1.name` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:45) — toolkit gap candidate
- [ ] `navigation.items.1.url` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:46) — toolkit gap candidate
- [ ] `navigation.items.1.isActiveWhen` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:47) — toolkit gap candidate
- [ ] `navigation.items.2.name` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:48) — toolkit gap candidate
- [ ] `navigation.items.2.url` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:49) — toolkit gap candidate
- [ ] `navigation.items.2.isActiveWhen` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:50) — toolkit gap candidate
- [ ] `navigation.items.3.name` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:51) — toolkit gap candidate
- [ ] `navigation.items.3.url` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:52) — toolkit gap candidate
- [ ] `navigation.items.3.isActiveWhen` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:53) — toolkit gap candidate
- [ ] `navigation.items.4.name` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:54) — toolkit gap candidate
- [ ] `navigation.items.4.url` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:55) — toolkit gap candidate
- [ ] `navigation.items.4.isActiveWhen` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:56) — toolkit gap candidate
- [ ] `navigation.items.5.name` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:57) — toolkit gap candidate
- [ ] `navigation.items.5.url` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:58) — toolkit gap candidate
- [ ] `navigation.items.5.isActiveWhen` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:59) — toolkit gap candidate
- [ ] `breadcrumbs.Breadcrumbs` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:65) — toolkit gap candidate
- [ ] `scopedQuery` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:77) — toolkit gap candidate
- [ ] `scopedQuery.where` — `people.panel` (app/Domains/People/Panel/Resources/PeoplePanelResource.php:78) — toolkit gap candidate
