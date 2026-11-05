<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_root_route_redirects_to_the_organization_login(): void
    {
        $this->get('/')->assertRedirectToRoute('org.login');
    }
}
