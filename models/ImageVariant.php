<?php

namespace linkcms1\Models; // Změňte na váš skutečný namespace

use Illuminate\Database\Eloquent\Model;

class ImageVariant extends Model
{
    protected $table = 'image_variants'; // Explicitně specifikujte název tabulky

    protected $fillable = [
        'original_image_id', 'variant_name', 'image_name', 'width', 'height', 'public_url'
    ]; // Pole, která lze hromadně přiřadit

    // Timestamps jsou automaticky spravovány Eloquentem
    public $timestamps = true;

    /**
     * Vztah k původnímu obrázku.
     */
    public function originalImage()
    {
        return $this->belongsTo('YourNamespace\Models\Image', 'original_image_id');
    }
}
?>