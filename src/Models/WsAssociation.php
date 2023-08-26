<?php

namespace JackedPhp\JackedServer\Models;

use Illuminate\Database\Eloquent\Model;

class WsAssociation extends Model
{
    const TABLE_NAME = 'wsassociations';

    /** @var string */
    protected $name = self::TABLE_NAME;
    protected $table = self::TABLE_NAME;

    protected array $defaults = [];

    protected $fillable = [
        'fd',
        'user_id',
    ];
}
