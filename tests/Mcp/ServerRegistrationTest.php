<?php

namespace Streams\Sdk\Tests\Mcp;

use Laravel\Mcp\Facades\Mcp;

class ServerRegistrationTest extends McpTestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['env'] = 'production';
    }

    public function test_it_is_not_registered_in_production()
    {
        $this->assertNull(Mcp::getLocalServer('streams'));
    }
}
