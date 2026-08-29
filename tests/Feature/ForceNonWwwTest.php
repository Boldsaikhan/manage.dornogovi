<?php

namespace Tests\Feature;

use Tests\TestCase;

class ForceNonWwwTest extends TestCase
{
    public function test_www_host_redirects_to_apex(): void
    {
        $response = $this->call('GET', '/login', [], [], [], [
            'HTTP_HOST' => 'www.example.test',
            'HTTPS' => 'off',
        ]);

        $response->assertRedirect('http://example.test/login');
        $response->assertStatus(301);
    }

    public function test_apex_host_is_not_redirected(): void
    {
        $response = $this->get('/login');

        $this->assertNotEquals(301, $response->getStatusCode());
    }
}
