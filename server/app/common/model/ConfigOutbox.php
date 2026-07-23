<?php

namespace app\common\model;

use support\Model;

class ConfigOutbox extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'cc_config_outbox';

    protected $guarded = [];
}
