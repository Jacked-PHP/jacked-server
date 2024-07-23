<?php

namespace JackedPhp\JackedServer\Services\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class EchoHandler extends AbstractProcessingHandler
{
    /**
     * @param array<mixed>|LogRecord $record
     * @return void
     */
    protected function write(array|LogRecord $record): void
    {
        echo $record['formatted'];
    }
}
