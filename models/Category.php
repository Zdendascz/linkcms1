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
        'order_cat',
        'url',
        'css'
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
    
        $categories = self::where('parent_id', $parentId)
                           ->where('site_id', $siteId)
                           ->where('is_active', 1)
                           ->orderBy('order_cat', 'desc')
                           ->get();
    
        foreach ($categories as $category) {
            // Dekódování JSON a příprava atributů
            $css = json_decode($category->css_cat, true) ?? [];
            $aAttributes = self::prepareAttributes($css, 'a');
            $liAttributes = self::prepareAttributes($css, 'li');
    
            // Vytvoření elementů s případnými atributy
            $html .= '<li ' . $liAttributes . '>';
            $html .= !empty($category->url) ? '<a href="' . htmlspecialchars($category->url) . '" ' . $aAttributes . '>' . htmlspecialchars($category->title) . '</a>' : htmlspecialchars($category->title);
    
            if (self::where('parent_id', $category->id)->where('site_id', $siteId)->where('is_active', 1)->exists()) {
                $childHtml = self::generateNavigation($siteId, $category->id);
                $html .= $childHtml;
            }
    
            $html .= '</li>';
        }
    
        $html .= '</ul>';
        return $html;
    }
    
    private static function prepareAttributes($css, $tag) {
        $attributes = '';
        if (isset($css[$tag . '_class']) && $css[$tag . '_class'] != '') {
            $attributes .= 'class="' . htmlspecialchars($css[$tag . '_class']) . '" ';
        }
        if (isset($css[$tag . '_id']) && $css[$tag . '_id'] != '') {
            $attributes .= 'id="' . htmlspecialchars($css[$tag . '_id']) . '" ';
        }
        if (isset($css[$tag . '_style']) && $css[$tag . '_style'] != '') {
            $attributes .= 'style="' . htmlspecialchars($css[$tag . '_style']) . '" ';
        }
        return $attributes;
    }
    // Další metody a logika pro model mohou být zde
}
?>