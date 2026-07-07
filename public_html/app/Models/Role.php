<?php

namespace App\Models;

use IPKF\Database\Model;

class Role extends Model
{
    protected string $table = 'roles';

    protected array $fillable = [
        'name',
        'slug'
    ];
}