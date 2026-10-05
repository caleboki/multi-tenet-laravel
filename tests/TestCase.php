<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Render views without requiring a built Vite manifest.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
