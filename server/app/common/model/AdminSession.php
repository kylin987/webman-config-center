<?php

namespace app\common\model;

use support\Model;

class AdminSession extends Model
{
    protected $table = 'cc_admin_session';

    protected $guarded = ['id'];
}

