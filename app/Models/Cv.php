<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Cv extends Model
{
    use HasUuids;

    protected $fillable = [
        'file_path',
        'extracted_text',
    ];
}
