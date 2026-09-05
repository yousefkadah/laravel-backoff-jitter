# Laravel Backoff Jitter

Adds jitter to Laravel queue backoff delays, so jobs that fail together don't retry in lockstep.

Laravel's backoff is deterministic. If a hundred jobs hit the same rate-limited API and all fail at 12:00:00 with `backoff = 60`, all hundred retry at 12:01:00 — and knock it over again. Jitter spreads them across a window instead.

```php
use YousefKadah\BackoffJitter\BackoffJitter;

#[BackoffJitter]
class SyncInventory implements ShouldQueue
{
    public $tries = 4;

    public $backoff = 60;   // now 45–75s, drawn separately for each attempt
}
```

## Installation

```bash
composer require yousefkadah/laravel-backoff-jitter
```

The service provider is auto-discovered. Nothing is jittered until a job opts in.

## Usage

**Default ratio (0.25):**

```php
#[BackoffJitter]
```

**Explicit ratio** — `0.5` on a 60s backoff gives 30–90s:

```php
#[BackoffJitter(0.5)]
```

**Property instead of an attribute**, if you prefer it or are on a job that already uses properties:

```php
public $backoffJitter = 0.5;
```

**Per-attempt backoff lists** are jittered element by element:

```php
public $backoff = [60, 300, 900];   // e.g. 52, 271, 1024
```

**Opting out** of a global default:

```php
public $backoffJitter = false;
```

## Global default

To jitter everything without touching each job, publish the config and set a ratio:

```bash
php artisan vendor:publish --tag=backoff-jitter-config
```

```php
'default_ratio' => 0.25,
```

Individual jobs can still override it, or opt out with `false`. Set `enabled => false` to switch the package off entirely — useful when a test suite asserts on exact delays.

## How it works

The package registers a `Queue::createPayloadUsing()` hook. At dispatch it reads the job's declared backoff, expands it to one value per attempt, and applies jitter to each — then writes the list back into the payload's `backoff` key.

That's a public, documented Laravel API. There is no `Worker` subclass, no container rebinding, and nothing that reaches into framework internals, so upgrades shouldn't break it.

Two consequences worth knowing:

- **Delays are drawn at dispatch, not at retry.** Each attempt still gets its own value, but they're fixed when the job is queued. This is what de-correlates jobs from each other, which is the actual cause of a retry storm.
- **Jobs with no `$tries` limit** get `unbounded_attempts` (default 10) distinct delays; attempts beyond that reuse the last one, exactly as the queue worker already does with a short backoff list.

A backoff of `0` is left at `0` — opting in never introduces a delay where you had none. Ratios are clamped to `0..1`, so a delay can't go negative.

## Resolution order

1. `backoffJitter()` method on the job
2. `$backoffJitter` property
3. `#[BackoffJitter]` attribute, including one inherited from a parent class or trait
4. `default_ratio` from config

Returning `null` or `false` from any of these opts the job out.

## Testing

```bash
composer install
vendor/bin/phpunit
```

## Background

This started as [laravel/framework#61437](https://github.com/laravel/framework/pull/61437), which was declined on the grounds of keeping the framework's maintenance surface small — with a suggestion to release it as a package. This is that package. The approach differs from the PR: rather than patching `Worker::calculateBackoff()`, it works entirely through the payload hook, which is why it needs no framework changes at all.

## License

MIT. See [LICENSE.md](LICENSE.md).
