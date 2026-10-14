<?php

namespace Streams\Sdk\Tests\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use Streams\Sdk\Tests\SdkTestCase;

class StreamsTapTest extends SdkTestCase
{
    /** @var resource|null */
    private $server;

    /** @var list<resource> */
    private array $pipes = [];

    private ?string $router = null;

    private ?string $log = null;

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);

            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            proc_close($this->server);
            $this->server = null;
        }

        foreach ([$this->router, $this->log] as $path) {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_streams_tap_is_registered_and_calls_the_url_with_query_string_input(): void
    {
        $this->assertArrayHasKey('streams:tap', Artisan::all());

        $base = $this->startServer();

        $this->artisan('streams:tap', [
            'tap' => $base.'/widgets',
            'input' => 'name=Widgets&status=draft',
        ])->expectsOutputToContain('tapped')
            ->assertSuccessful();

        $this->assertSame('/widgets?name=Widgets&status=draft', $this->recordedUri());
    }

    public function test_streams_tap_rejects_a_url_that_is_not_http(): void
    {
        $this->artisan('streams:tap', [
            'tap' => 'file:///etc/passwd',
        ])->assertFailed();
    }

    public function test_streams_tap_fails_when_the_tap_responds_with_an_error(): void
    {
        $base = $this->startServer();

        $this->artisan('streams:tap', [
            'tap' => $base.'/widgets',
            'input' => 'fail=1',
        ])->expectsOutputToContain('Tap failed with status 500.')
            ->assertFailed();
    }

    private function startServer(): string
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);

        $this->assertIsResource($socket, $error);

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $port = (int) substr((string) strrchr((string) $address, ':'), 1);
        $this->router = sys_get_temp_dir().'/streams-tap-router-'.$port.'.php';
        $this->log = sys_get_temp_dir().'/streams-tap-log-'.$port.'.txt';

        file_put_contents($this->router, <<<'PHP'
<?php
file_put_contents(getenv('TAP_LOG'), $_SERVER['REQUEST_URI']);
if (isset($_GET['fail'])) {
    http_response_code(500);
    echo 'nope';
    return;
}
echo 'tapped';
PHP);

        $env = getenv();
        $env['TAP_LOG'] = $this->log;

        $this->server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$port, $this->router],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $this->pipes,
            null,
            $env
        );

        $this->assertIsResource($this->server);

        $ready = false;

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $error, 0.1);

            if (is_resource($connection)) {
                fclose($connection);
                $ready = true;
                break;
            }

            usleep(20000);
        }

        $this->assertTrue($ready, 'The tap test server did not start.');

        return 'http://127.0.0.1:'.$port;
    }

    private function recordedUri(): string
    {
        $this->assertIsString($this->log);
        $this->assertFileExists($this->log);

        return (string) file_get_contents($this->log);
    }
}
