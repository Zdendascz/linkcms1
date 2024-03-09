<?php
namespace linkcms1\Models; // Změňte na váš skutečný namespace

use Illuminate\Database\Eloquent\Model;

class ArticleImage extends Model
{
    // Explicitní určení názvu tabulky, pokud se název třídy neshoduje
    protected $table = 'article_images';

    // Vypnutí timestamps, pokud vaše tabulka neobsahuje sloupce created_at a updated_at
    public $timestamps = false;

    // Definice vazeb - příklad vazby k článku
    public function article() {
        return $this->belongsTo('App\Article', 'article_id');
    }

    // Definice vazby k souboru
    public function file() {
        return $this->belongsTo('App\UploadedFile', 'file_id');
    }

    // Povolené sloupce pro hromadné přiřazení
    protected $fillable = ['article_id', 'file_id', 'variant', 'order'];

    // Případně, pokud potřebujete specifikovat vlastnosti, které nejsou hromadně přiřazovatelné
    protected $guarded = [];
}
?>