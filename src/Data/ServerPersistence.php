<?php

namespace JackedPhp\JackedServer\Data;

use Bag\Bag;
use Conveyor\SubProtocols\Conveyor\Persistence\Interfaces\GenericPersistenceInterface;
use OpenSwoole\Core\Coroutine\Pool\ClientPool;

readonly class ServerPersistence extends Bag
{
    /**
     * @param ClientPool $connectionPool
     * @param array<GenericPersistenceInterface> $conveyorPersistence
     */
    public function __construct(
        public ClientPool $connectionPool,
        public array $conveyorPersistence,
    ) {
    }
}
