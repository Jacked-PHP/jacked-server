<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Concerns\WithWorkbench;

class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;
    use WithWorkbench;

    protected $loadEnvironmentVariables = false;

    public string $laravelPath;
    public int $port = 8989;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpLaravel();
    }
}
