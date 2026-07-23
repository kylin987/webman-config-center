<?php

namespace app\common\model;

use support\Model;

class ClientAccount extends Model
{
    protected $table = 'cc_client_account';

    protected $guarded = ['id'];
}
