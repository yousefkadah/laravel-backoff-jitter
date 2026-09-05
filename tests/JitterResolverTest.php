<?php

namespace YousefKadah\BackoffJitter\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use YousefKadah\BackoffJitter\BackoffJitter;
use YousefKadah\BackoffJitter\JitterResolver;

class JitterResolverTest extends TestCase
{
    protected function payload(string $class, ?string $backoff, ?int $maxTries = 3): array
    {
        return [
            'backoff' => $backoff,
            'maxTries' => $maxTries,
            'data' => ['commandName' => $class],
        ];
    }

    protected function delays(array $result): array
    {
        return array_map(intval(...), explode(',', $result['backoff']));
    }

    #[Test]
    public function it_leaves_jobs_that_declare_nothing_alone(): void
    {
        $resolver = new JitterResolver;

        $this->assertSame([], $resolver->apply($this->payload(PlainJob::class, '60')));
    }

    #[Test]
    public function it_applies_the_default_ratio_from_a_bare_attribute(): void
    {
        $resolver = new JitterResolver;

        foreach (range(1, 30) as $ignored) {
            $delays = $this->delays($resolver->apply($this->payload(AttributeJob::class, '100')));

            foreach ($delays as $delay) {
                $this->assertGreaterThanOrEqual(75, $delay);
                $this->assertLessThanOrEqual(125, $delay);
            }
        }
    }

    #[Test]
    public function it_honours_an_explicit_ratio(): void
    {
        $resolver = new JitterResolver;

        foreach (range(1, 30) as $ignored) {
            $delays = $this->delays($resolver->apply($this->payload(ExplicitRatioJob::class, '100')));

            foreach ($delays as $delay) {
                $this->assertGreaterThanOrEqual(50, $delay);
                $this->assertLessThanOrEqual(150, $delay);
            }
        }
    }

    #[Test]
    public function it_reads_a_property_when_there_is_no_attribute(): void
    {
        $resolver = new JitterResolver;

        $delays = $this->delays($resolver->apply($this->payload(PropertyJob::class, '100')));

        foreach ($delays as $delay) {
            $this->assertGreaterThanOrEqual(50, $delay);
            $this->assertLessThanOrEqual(150, $delay);
        }
    }

    #[Test]
    public function a_false_property_opts_out_of_the_global_default(): void
    {
        $resolver = new JitterResolver(defaultRatio: 0.5);

        $this->assertSame([], $resolver->apply($this->payload(OptedOutJob::class, '60')));
    }

    #[Test]
    public function it_inherits_the_attribute_from_a_parent(): void
    {
        $resolver = new JitterResolver;

        $this->assertNotSame([], $resolver->apply($this->payload(ChildJob::class, '60')));
    }

    #[Test]
    public function it_generates_one_delay_per_attempt(): void
    {
        $resolver = new JitterResolver;

        $delays = $this->delays($resolver->apply($this->payload(AttributeJob::class, '100', 5)));

        $this->assertCount(5, $delays);
        $this->assertGreaterThan(1, count(array_unique($delays)));
    }

    #[Test]
    public function it_preserves_a_per_attempt_backoff_list(): void
    {
        $resolver = new JitterResolver;

        $delays = $this->delays($resolver->apply($this->payload(AttributeJob::class, '100,200,400', 3)));

        $this->assertCount(3, $delays);
        $this->assertGreaterThanOrEqual(75, $delays[0]);
        $this->assertLessThanOrEqual(125, $delays[0]);
        $this->assertGreaterThanOrEqual(150, $delays[1]);
        $this->assertLessThanOrEqual(250, $delays[1]);
        $this->assertGreaterThanOrEqual(300, $delays[2]);
        $this->assertLessThanOrEqual(500, $delays[2]);
    }

    #[Test]
    public function it_caps_delays_for_jobs_with_unlimited_attempts(): void
    {
        $resolver = new JitterResolver(unboundedAttempts: 4);

        $delays = $this->delays($resolver->apply($this->payload(AttributeJob::class, '100', 0)));

        $this->assertCount(4, $delays);
    }

    #[Test]
    public function it_leaves_a_missing_backoff_alone(): void
    {
        $resolver = new JitterResolver;

        $this->assertSame([], $resolver->apply($this->payload(AttributeJob::class, null)));
    }

    #[Test]
    public function it_leaves_a_zero_backoff_alone(): void
    {
        $resolver = new JitterResolver;

        $delays = $this->delays($resolver->apply($this->payload(AttributeJob::class, '0')));

        $this->assertSame([0, 0, 0], $delays);
    }

    #[Test]
    public function it_never_produces_a_negative_delay(): void
    {
        $resolver = new JitterResolver;

        foreach (range(1, 50) as $ignored) {
            $delays = $this->delays($resolver->apply($this->payload(WildRatioJob::class, '5')));

            foreach ($delays as $delay) {
                $this->assertGreaterThanOrEqual(0, $delay);
            }
        }
    }

    #[Test]
    public function it_falls_back_to_the_default_for_an_unknown_class(): void
    {
        $this->assertSame([], (new JitterResolver)->apply($this->payload('Missing\\Job', '60')));

        $this->assertNotSame(
            [],
            (new JitterResolver(defaultRatio: 0.25))->apply($this->payload('Missing\\Job', '60'))
        );
    }
}

class PlainJob
{
}

#[BackoffJitter]
class AttributeJob
{
}

#[BackoffJitter(0.5)]
class ExplicitRatioJob
{
}

#[BackoffJitter(5.0)]
class WildRatioJob
{
}

class PropertyJob
{
    public $backoffJitter = 0.5;
}

class OptedOutJob
{
    public $backoffJitter = false;
}

class ChildJob extends AttributeJob
{
}
