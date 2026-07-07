<?php

namespace App\Models;

use IPKF\Database\Model;

class UserRole extends Model
{
    protected string $table = 'user_roles';

    protected array $fillable = [
        'user_id',
        'role_id'
    ];
}