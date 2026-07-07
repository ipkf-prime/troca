<?php

namespace App\Models;

use IPKF\Database\Model;

class Permission extends Model
{
    protected string $table = 'permissions';

    protected array $fillable = [
        'name',
        'slug'
    ];
}