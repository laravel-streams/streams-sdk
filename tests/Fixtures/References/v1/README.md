# GroupVitals people references v1

These five files are verbatim, committed source snapshots, not runnable SDK components. Their app, Streams UI, translation, and domain dependencies are intentionally not copied. Source checkout: `~/Sites/_gv/app`. Each file was checked byte-for-byte against the pinned commit and working copy.

- `streams/people.json` — People stream definition. Source: `~/Sites/_gv/app/streams/people.json`; commit: `fa7fe5cbc160b2d0a9886574840c1efb154a4106`; SHA-256: `61375318bfb7b0ca40d56cdcee57dd22305f26c881e3e54149d78d7335520b5d`.

- `app/Domains/People/Panel/Pages/ListPeople.php` — Table page and mountable action registration. Source: `~/Sites/_gv/app/app/Domains/People/Panel/Pages/ListPeople.php`; commit: `fa7fe5cbc160b2d0a9886574840c1efb154a4106`; SHA-256: `2d65b85204df12bee5c691d96791929cb647d3616631ee70d837bd6f826f9e92`.

- `app/Domains/People/Panel/Tables/PeopleTable.php` — People table. Source: `~/Sites/_gv/app/app/Domains/People/Panel/Tables/PeopleTable.php`; commit: `fa7fe5cbc160b2d0a9886574840c1efb154a4106`; SHA-256: `d0ac3926c444db5de58de434e273d7f84037f961cfe244d7135a11d255112742`.

- `app/Domains/People/Panel/Actions/AddPersonModal.php` — Mountable add-person action. Source: `~/Sites/_gv/app/app/Domains/People/Panel/Actions/AddPersonModal.php`; commit: `fa7fe5cbc160b2d0a9886574840c1efb154a4106`; SHA-256: `b8662e321ffa2dbbf834070b280196e5b9c7ce8210d02a3558563ca9441957a5`.

- `app/Domains/People/Panel/Resources/PeoplePanelResource.php` — Panel resource and page route registration. Source: `~/Sites/_gv/app/app/Domains/People/Panel/Resources/PeoplePanelResource.php`; commit: `fa7fe5cbc160b2d0a9886574840c1efb154a4106`; SHA-256: `e02a67faafd090792e7b1be3815810bbcda1006b2e080b235121a33eb3342515`.

ListPeople registers PeopleTable and AddPersonModal; PeoplePanelResource registers the people page routes. These references cover the people thin slice only. No streams/ui code is modified.

Treat v1 as immutable. Pin updated references in a new version directory with new provenance; do not silently refresh these files. Golden outputs live separately under tests/Fixtures/Golden and are reviewed expectations, not copies of all reference dependencies.
