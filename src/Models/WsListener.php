<?php

namespace JackedPhp\JackedServer\Models;

use Illuminate\Database\Eloquent\Model;

class WsListener extends Model
{
    const TABLE_NAME = 'wslisteners';

    /** @var string */
    protected $name = self::TABLE_NAME;
    protected $table = self::TABLE_NAME;

    protected array $defaults = [];

    protected $fillable = [
        'fd',
        'action',
    ];
}
