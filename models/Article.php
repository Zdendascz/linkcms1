<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{

    protected $table = 'articles'; // Název tabulky v databázi

    protected $fillable = [
        'title',
        'subtitle',
        'short_text',
        'snippet',
        'body',
        'author_id',
        'meta', // Poznámka: Toto pole bude automaticky konvertováno na a z JSON.
        'status'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function author() {
        return $this->belongsTo(User::class, 'author_id');
    }

    // Další vlastní metody a logika pro model mohou být zde
}
?>