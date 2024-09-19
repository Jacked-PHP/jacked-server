<?php

namespace Tests;

use Hook\Filter;
use JackedPhp\JackedServer\Constants as JackedServerConstants;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Dotenv\Dotenv;
use Tests\Feature\Traits\ServerTrait;

class TestCase extends BaseTestCase
{
    use ServerTrait;

    public function setUp(): void
    {
        define('ROOT_DIR', __DIR__ . '/Sample');
        define('CONFIG_FILE', ROOT_DIR . '/config/jacked-server.php');
        define('MONITOR_CHANNEL', 'jacked-monitor');
        define('IS_PHAR', false);

        $dotenv = Dotenv::createImmutable(ROOT_DIR);
        $dotenv->load();

        parent::setUp();
    }

    public function tearDown(): void
    {
        Filter::removeAllFilters(JackedServerConstants::INTERCEPT_REQUEST);

        parent::tearDown();
    }
}
