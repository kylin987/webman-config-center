<?php

namespace app\common\model;

use support\Model;

class AdminUser extends Model
{
    protected $table = 'cc_admin_user';

    protected $guarded = ['id'];
}

