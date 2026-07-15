<?php

namespace app\common\model;

use support\Model;

class ClientToken extends Model
{
    protected $table = 'cc_client_token';

    protected $guarded = ['id'];
}

