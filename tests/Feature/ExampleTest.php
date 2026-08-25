<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * This app has no public marketing page -- "/" redirects straight to
     * the Filament panel, and deliberately never renders welcome.blade.php
     * (which @vite()s assets Laravel doesn't build in this project, since
     * Filament ships its own pre-built assets -- rendering it would 500 in
     * a production deploy with no npm build step).
     */
    public function test_root_redirects_to_the_admin_panel(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/admin');
    }
}
