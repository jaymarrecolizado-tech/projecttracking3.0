<?php

namespace Tests\Feature;

use App\Listeners\CheckSystemHealth;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * `/up` is the URL an external uptime monitor pings (Plan.md → F3). Laravel's
 * endpoint answers 200 as long as PHP is alive, which is not the same as the
 * app working — so the listener has to prove the two dependencies that make it
 * unusable, and has to fail *loudly* rather than quietly pass.
 */
class HealthCheckTest extends TestCase
{
    public function test_up_answers_200_when_the_dependencies_are_fine(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_up_is_reachable_without_authentication(): void
    {
        $this->assertGuest();
        $this->get('/up')->assertOk();
    }

    public function test_the_listener_is_wired_to_the_health_event(): void
    {
        $this->assertTrue(
            Event::hasListeners(DiagnosingHealth::class),
            'A monitor pointed at /up is only as good as what the endpoint checks.'
        );

        // The happy path must not throw, or /up would 500 on a healthy box.
        (new CheckSystemHealth)(new DiagnosingHealth);
    }

    /**
     * Point the default connection at a port nothing is listening on, so
     * `DB::select()` fails the way it would on a host that lost MySQL.
     *
     * A mocked connection would be the wrong tool: it asserts that the code
     * handles the exception a mock throws, not that a real unreachable database
     * produces one. Port 1 refuses instantly, so the test stays fast.
     */
    private function breakDatabase(): void
    {
        config()->set('database.connections.unreachable', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'nope',
            'username' => 'nope',
            'password' => 'nope',
            'unix_socket' => '',
            'charset' => 'utf8mb4',
        ]);
        config()->set('database.default', 'unreachable');
        DB::purge('unreachable');
    }

    public function test_an_unreachable_database_fails_the_health_check(): void
    {
        $this->breakDatabase();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database unreachable');

        (new CheckSystemHealth)(new DiagnosingHealth);
    }

    /** The other half: permissions drift breaks sessions, logs and report writes. */
    public function test_an_unwritable_storage_directory_fails_the_health_check(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Windows ignores POSIX mode bits, so a directory cannot be made unwritable here. The check itself runs on the Linux host.');
        }

        $storage = sys_get_temp_dir().'/fpiap-unwritable-'.uniqid();
        mkdir($storage, 0500, true);
        app()->useStoragePath($storage);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('storage/ is not writable');

            (new CheckSystemHealth)(new DiagnosingHealth);
        } finally {
            @chmod($storage, 0700);
            @rmdir($storage);
            app()->useStoragePath(base_path('storage'));
        }
    }
}
