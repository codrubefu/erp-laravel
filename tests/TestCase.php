<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Prevent password validation from hitting the real "have I been pwned" API
        // during tests, which would make weak test fixture passwords (e.g. "password")
        // fail the `uncompromised` rule depending on external network conditions.
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);
    }
}
