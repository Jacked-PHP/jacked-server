<?php

namespace JackedPhp\JackedServer\Events;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

class JackedServerRequestIntercepted
{
    public function __construct(
        public readonly Request $request,
        public readonly Response $response,
    ) {
    }
}
