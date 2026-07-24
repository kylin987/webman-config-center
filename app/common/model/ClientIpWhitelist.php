<?php

namespace app\common\model;

use support\Model;

class ClientIpWhitelist extends Model
{
    protected $table = 'cc_client_ip_whitelist';

    protected $guarded = ['id'];
}
