<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRateLimitConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rate_limit_defaults_to_five(): void
    {
        $this->assertSame(5, config('auth.login_rate_limit'));
    }

    public function test_login_rate_limit_is_configurable(): void
    {
        config(['auth.login_rate_limit' => 2]);

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(302);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(302);
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])->assertStatus(429);
    }
}
