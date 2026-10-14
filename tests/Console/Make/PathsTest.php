<?php

namespace Streams\Sdk\Tests\Console\Make;

use Streams\Sdk\Console\Make\Paths;
use Streams\Sdk\Tests\SdkTestCase;

/**
 * Generated paths resolve from config and otherwise match the GroupVitals
 * reference layout (app/Domains/People/Panel/...).
 */
class PathsTest extends SdkTestCase
{
    public function test_reference_defaults_match_the_people_layout(): void
    {
        $paths = Paths::fromConfig();

        $this->assertSame(
            'app/Domains/People/Panel/Tables/PeopleTable.php',
            $paths->resolve('table', 'People', 'PeopleTable'),
        );

        $this->assertSame(
            'app/Domains/People/Panel/Pages/PeoplePagesManage.php',
            $paths->resolve('page', 'People', 'PeoplePagesManage'),
        );

        $this->assertSame(
            'app/Domains/People/Panel/Actions/PeopleActionsAdd.php',
            $paths->resolve('action', 'People', 'PeopleActionsAdd'),
        );

        $this->assertSame(
            'app/Domains/People/Panel/Resources/PeoplePanelResource.php',
            $paths->resolve('panel', 'People', 'PeoplePanelResource'),
        );

        $this->assertSame(
            'app/Domains/People/Panel/Forms/PeopleActionsAddForm.php',
            $paths->resolve('form', 'People', 'PeopleActionsAddForm'),
        );

        $this->assertSame(
            'app/Domains/People/Public/Pages/PeoplePublicPagesDirectory.php',
            $paths->resolve('public_page', 'People', 'PeoplePublicPagesDirectory'),
        );

        $this->assertSame(
            'App\\Domains\\People\\Panel\\Tables',
            $paths->namespaceFor('app/Domains/People/Panel/Tables/PeopleTable.php'),
        );

        $this->assertSame('app/Domains', $paths->managedRoot());
        $this->assertNull($paths->resolve('column', 'People', 'Name'));
    }

    public function test_config_overrides_a_path_template_and_keeps_the_other_defaults(): void
    {
        config([
            'streams.sdk.make.paths' => [
                'root' => 'src/Domains',
                'namespace' => 'Work\\Domains',
                'table' => '{root}/{Domain}/Tables/{Class}.php',
            ],
        ]);

        $paths = Paths::fromConfig();

        $this->assertSame(
            'src/Domains/People/Tables/PeopleTable.php',
            $paths->resolve('table', 'People', 'PeopleTable'),
        );

        $this->assertSame(
            'src/Domains/People/Panel/Actions/PeopleActionsAdd.php',
            $paths->resolve('action', 'People', 'PeopleActionsAdd'),
        );

        $this->assertSame(
            'Work\\Domains\\People\\Tables',
            $paths->namespaceFor('src/Domains/People/Tables/PeopleTable.php'),
        );
    }
}
