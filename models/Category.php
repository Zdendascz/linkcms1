<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{

    protected $table = 'categories'; // Název tabulky v databázi

    protected $fillable = [
        'title',
        'display_name',
        'top_text',
        'bottom_text',
        'meta',
        'parent_id',
        'is_active',
        'site_id',
        'order_cat'
    ];

    protected $casts = [
        'meta' => 'array'
    ];

    public function parentCategory() {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function childCategories() {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function site() {
        // Předpokládá, že existuje model Site
        return $this->belongsTo(Site::class, 'site_id');
    }

    /**
     * Generuje HTML navigaci pro kategorie
     *
     * @param int $siteId ID stránky
     * @param int|null $parentId ID nadřazené kategorie, nebo null pro kořenové kategorie
     * @return string HTML navigace
     */
    public static function generateNavigation($siteId, $parentId = null) {
        $html = '<ul>';

        // Získání kategorií na základě nadřazené kategorie a site_id
        $categories = self::where('parent_id', $parentId)
                           ->where('site_id', $siteId)
                           ->where('is_active', 1)
                           ->orderBy('order_cat', 'desc')
                           ->get();

        foreach ($categories as $category) {
            $html .= '<li>' . htmlspecialchars($category->title);

            // Kontrola, zda existují podkategorie
            if (self::where('parent_id', $category->id)->where('site_id', $siteId)->where('is_active', 1)->exists()) {
                // Rekurzivní volání pro podkategorie
                $childHtml = self::generateNavigation($siteId, $category->id);
                $html .= $childHtml;
            }

            $html .= '</li>';
        }

        $html .= '</ul>';
        return $html;
    }
    // Další metody a logika pro model mohou být zde
}
?>