<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;

class Url extends Model
{
    // Specifikace názvu tabulky, pokud není standardní
    protected $table = 'urls';

    // Sloupce, do kterých je možné hromadně vkládat data
    protected $fillable = [
        'domain',
        'url',
        'handler',
        'model',
        'model_id'
    ];

    // Timestamps sloupce (created_at, updated_at, deleted_at) jsou již standardně v modelu
}
?>