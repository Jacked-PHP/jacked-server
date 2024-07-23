<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Dotenv\Dotenv;
use Tests\Feature\Traits\ServerTrait;

class TestCase extends BaseTestCase
{
    use ServerTrait;

    public function setUp(): void
    {
        define('ROOT_DIR', __DIR__ . '/Sample');
        define('MONITOR_CHANNEL', 'jacked-monitor');

        $dotenv = Dotenv::createImmutable(ROOT_DIR);
        $dotenv->load();

        parent::setUp();
    }

    public function tearDown(): void
    {
        self::tearServerDown();

        parent::tearDown();
    }
}
