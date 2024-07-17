<?php

namespace JackedPhp\JackedServer\Services\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;

class EchoHandler extends AbstractProcessingHandler
{
    protected function write(array|LogRecord $record): void
    {
        echo $record['formatted'];
    }
}