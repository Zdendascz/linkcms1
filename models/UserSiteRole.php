<?php
namespace linkcms1\Models;

use Illuminate\Database\Eloquent\Model;

class UserSiteRole extends Model
{
    protected $table = 'user_site_roles';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }
}
?>