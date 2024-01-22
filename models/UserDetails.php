<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;
use Tracy\Debugger;

class UserDetails extends Model
{
    protected $table = 'user_details';

    protected $fillable = [
        'fullname', 'phone', 'address', 'city', 'country', 'postal_code', 'additional_info'
    ];

    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }
}

?>