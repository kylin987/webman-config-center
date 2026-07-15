<?php

namespace app\common\model;

use support\Model;

class ConfigHistory extends Model
{
    protected $table = 'cc_config_history';

    protected $guarded = ['id'];
}

