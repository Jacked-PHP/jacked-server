<?php

namespace JackedPhp\JackedServer\Database\Models;

use JackedPhp\LiteConnect\Model\BaseModel;

class Token extends BaseModel
{
    protected string $table = 'tokens';

    /**
     * @var string[] $fillable
     */
    protected array $fillable = [
        'fd',
        'token',
        'allowed_channels',
    ];
}
