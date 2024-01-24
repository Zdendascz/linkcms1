<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $fillable = [
        'name',
        'domain',
        'created_at',
        'updated_at',
        'user_id',
        'active',
        'tarif_id',
        'template_dir',
        'language',
        'configurations',
        'analytics',
        'notes',
        'head_code',
        'post_body_code',
        'pre_end_body_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }

    /**
 * Vrátí pole všech unikátních handlerů z tabulky urls, seřazených abecedně.
 * @return array Pole unikátních handlerů.
 */
public function getAllUniqueHandlers() {
    $handlers = Url::distinct()->orderBy('handler', 'asc')->pluck('handler')->toArray();
    return $handlers;
}

/**
 * Vrátí pole všech unikátních modelů z tabulky urls, seřazených abecedně.
 * @return array Pole unikátních modelů.
 */
public function getAllUniqueModels() {
    $models = Url::distinct()->orderBy('model', 'asc')->pluck('model')->toArray();
    return $models;
}
}
?>