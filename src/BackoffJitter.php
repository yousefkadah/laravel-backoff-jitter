<?php

namespace YousefKadah\BackoffJitter;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class BackoffJitter
{
    /**
     * The proportion a backoff is adjusted by when none is given.
     */
    public const DEFAULT_RATIO = 0.25;

    /**
     * @param  float  $ratio  Proportion of the backoff the delay may vary by, between 0 and 1.
     */
    public function __construct(public float $ratio = self::DEFAULT_RATIO)
    {
        //
    }
}
