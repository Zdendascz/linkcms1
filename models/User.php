<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $table = 'users';
    // Ostatní definice modelu...

    public function articles()
    {
        return $this->hasMany(Article::class);
    }
}

?>