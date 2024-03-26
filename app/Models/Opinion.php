<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Opinion extends Model
{
    use HasFactory;

    protected $fillable = [
        'release_date',
        'court',
        'case_number',
        'case_name',
        'note',
        'pdf_file_identifier',
        'pdf_file_url',
        'pdf_file'
    ];
}
