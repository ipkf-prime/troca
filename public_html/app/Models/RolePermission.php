<?php

namespace App\Models;

use IPKF\Database\Model;

class RolePermission extends Model
{
    protected string $table = 'role_permissions';

    protected array $fillable = [
        'role_id',
        'permission_id'
    ];
}