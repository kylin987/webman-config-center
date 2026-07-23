<?php

namespace app\common\model;

use support\Model;

class AdminMfaChallenge extends Model
{
    protected $table = 'cc_admin_mfa_challenge';

    protected $guarded = ['id'];
}
