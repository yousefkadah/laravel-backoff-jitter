<?php

namespace YousefKadah\BackoffJitter\Tests;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use YousefKadah\BackoffJitter\BackoffJitter;

class DispatchIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    protected function pushedPayload(): array
    {
        return json_decode(DB::table('jobs')->latest('id')->first()->payload, true);
    }

    #[Test]
    public function a_job_without_jitter_keeps_its_exact_backoff(): void
    {
        PlainDispatchJob::dispatch();

        $this->assertSame('60', $this->pushedPayload()['backoff']);
    }

    #[Test]
    public function a_jittered_job_gets_one_varied_delay_per_attempt(): void
    {
        JitteredDispatchJob::dispatch();

        $delays = array_map(intval(...), explode(',', $this->pushedPayload()['backoff']));

        $this->assertCount(4, $delays);

        foreach ($delays as $delay) {
            $this->assertGreaterThanOrEqual(75, $delay);
            $this->assertLessThanOrEqual(125, $delay);
        }
    }

    #[Test]
    public function separate_dispatches_of_the_same_job_get_different_delays(): void
    {
        foreach (range(1, 12) as $ignored) {
            JitteredDispatchJob::dispatch();
        }

        $backoffs = DB::table('jobs')->pluck('payload')
            ->map(fn ($payload) => json_decode($payload, true)['backoff'])
            ->unique();

        $this->assertGreaterThan(1, $backoffs->count());
    }

    #[Test]
    public function it_changes_nothing_but_the_backoff(): void
    {
        PlainDispatchJob::dispatch();
        $plain = $this->pushedPayload();

        DB::table('jobs')->delete();

        JitteredDispatchJob::dispatch();
        $jittered = $this->pushedPayload();

        $this->assertSame(array_keys($plain), array_keys($jittered));

        foreach (['maxTries', 'maxExceptions', 'failOnTimeout', 'timeout', 'retryUntil'] as $key) {
            $this->assertSame($plain[$key], $jittered[$key], "payload key [{$key}] changed");
        }
    }

    #[Test]
    public function a_job_may_opt_out_with_a_method(): void
    {
        MethodOptOutJob::dispatch();

        $this->assertSame('60', $this->pushedPayload()['backoff']);
    }
}

class PlainDispatchJob implements ShouldQueue
{
    use Dispatchable;

    public $tries = 4;

    public $backoff = 60;

    public function handle(): void
    {
        //
    }
}

#[BackoffJitter]
class JitteredDispatchJob implements ShouldQueue
{
    use Dispatchable;

    public $tries = 4;

    public $backoff = 100;

    public function handle(): void
    {
        //
    }
}

#[BackoffJitter]
class MethodOptOutJob implements ShouldQueue
{
    use Dispatchable;

    public $tries = 4;

    public $backoff = 60;

    public function backoffJitter(): ?float
    {
        return null;
    }

    public function handle(): void
    {
        //
    }
}
