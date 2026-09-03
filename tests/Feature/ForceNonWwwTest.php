<?php

namespace Tests\Feature;

use Tests\TestCase;

class ForceNonWwwTest extends TestCase
{
    /**
     * Хостыг заавал БҮТЭН хаягаар өгнө.
     *
     * `$this->call('GET', '/login', ...)`-д дамжуулсан HTTP_HOST нь хүчингүй:
     * Laravel замыг APP_URL-аар бүтэн хаяг болгодог тул Symfony хост нь тэр
     * хаягийнхаар дарагдаж, www хэзээ ч тестэд хүрдэггүй.
     */
    public function test_www_host_redirects_to_apex(): void
    {
        $response = $this->get('http://www.example.test/login');

        $response->assertStatus(301);
        $response->assertRedirect('http://example.test/login');
    }

    public function test_www_host_keeps_the_path_and_query(): void
    {
        $this->get('http://www.example.test/uureg?kind=prep_plan')
            ->assertRedirect('http://example.test/uureg?kind=prep_plan');
    }

    public function test_apex_host_is_not_redirected(): void
    {
        $response = $this->get('/login');

        $this->assertNotEquals(301, $response->getStatusCode());
    }
}
