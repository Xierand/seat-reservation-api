<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader(
            'X-Internal-Api-Key',
            (string) config('services.internal.api_key')
        );
    }
}
