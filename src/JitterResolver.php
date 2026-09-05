<?php

namespace YousefKadah\BackoffJitter;

use ReflectionClass;
use Throwable;

class JitterResolver
{
    /**
     * @param  float|null  $defaultRatio  Ratio applied to jobs that declare nothing, or null to leave them alone.
     * @param  int  $unboundedAttempts  How many delays to generate when a job has no maximum attempts.
     */
    public function __construct(
        protected ?float $defaultRatio = null,
        protected int $unboundedAttempts = 10,
    ) {
        //
    }

    /**
     * Build the payload overrides for the given job payload.
     *
     * Returns an empty array when the job opts out, which leaves the payload untouched.
     *
     * @return array<string, string>
     */
    public function apply(array $payload): array
    {
        $ratio = $this->ratioFor($payload['data']['commandName'] ?? null);

        if (is_null($ratio) || $ratio <= 0) {
            return [];
        }

        $delays = $this->delaysFrom($payload);

        if ($delays === []) {
            return [];
        }

        $ratio = min(1.0, $ratio);

        return ['backoff' => implode(',', array_map(
            fn (int $delay): int => $this->jitter($delay, $ratio),
            $this->expand($delays, $this->attemptsFor($payload)),
        ))];
    }

    /**
     * Apply jitter to a single delay.
     */
    public function jitter(int $delay, float $ratio): int
    {
        if ($delay <= 0) {
            return $delay;
        }

        $delta = (int) round($delay * $ratio);

        return $delta === 0
            ? $delay
            : random_int(max(0, $delay - $delta), $delay + $delta);
    }

    /**
     * Resolve the jitter ratio declared by the given job.
     *
     * Payload hooks run before the job is serialized, so this is usually the job
     * instance itself. It falls back to reflection when only a class name is known.
     */
    public function ratioFor(object|string|null $job): ?float
    {
        if (is_null($job)) {
            return $this->defaultRatio;
        }

        if (is_object($job)) {
            if (method_exists($job, 'backoffJitter')) {
                return $this->normalize($job->backoffJitter());
            }

            if (isset($job->backoffJitter)) {
                return $this->normalize($job->backoffJitter);
            }
        }

        $class = is_object($job) ? $job::class : $job;

        if (! class_exists($class)) {
            return $this->defaultRatio;
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (Throwable) {
            return $this->defaultRatio;
        }

        if (! is_null($ratio = $this->ratioFromAttribute($reflection))) {
            return $ratio;
        }

        $properties = $reflection->getDefaultProperties();

        if (array_key_exists('backoffJitter', $properties)) {
            return $this->normalize($properties['backoffJitter']);
        }

        return $this->defaultRatio;
    }

    /**
     * Turn a declared value into a usable ratio, or null when it opts out.
     */
    protected function normalize(mixed $value): ?float
    {
        if (is_null($value) || $value === false) {
            return null;
        }

        return $value === true
            ? BackoffJitter::DEFAULT_RATIO
            : (float) $value;
    }

    /**
     * Find the attribute on the class, its traits, or any of its parents.
     */
    protected function ratioFromAttribute(ReflectionClass $reflection): ?float
    {
        do {
            $attributes = $reflection->getAttributes(BackoffJitter::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance()->ratio;
            }

            foreach ($reflection->getTraits() as $trait) {
                $attributes = $trait->getAttributes(BackoffJitter::class);

                if ($attributes !== []) {
                    return $attributes[0]->newInstance()->ratio;
                }
            }
        } while ($reflection = $reflection->getParentClass());

        return null;
    }

    /**
     * Read the configured backoff delays out of the payload.
     *
     * @return array<int, int>
     */
    protected function delaysFrom(array $payload): array
    {
        $backoff = $payload['backoff'] ?? null;

        if (is_null($backoff) || $backoff === '') {
            return [];
        }

        return array_map(intval(...), explode(',', (string) $backoff));
    }

    /**
     * Determine how many delays the job could use.
     */
    protected function attemptsFor(array $payload): int
    {
        $tries = (int) ($payload['maxTries'] ?? 0);

        return $tries > 0 ? $tries : $this->unboundedAttempts;
    }

    /**
     * Pad the delays out to one per attempt, repeating the last as the worker does.
     *
     * @param  array<int, int>  $delays
     * @return array<int, int>
     */
    protected function expand(array $delays, int $attempts): array
    {
        $last = $delays[count($delays) - 1];

        for ($i = count($delays); $i < $attempts; $i++) {
            $delays[] = $last;
        }

        return $delays;
    }
}
