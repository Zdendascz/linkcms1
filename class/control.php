<?php
namespace linkcms1;
use Illuminate\Database\Capsule\Manager as Capsule;
use linkcms1\Models\User as EloquentUser;
use linkcms1\Models\Site;
use linkcms1\Models\Url;



class domainControl {

    protected $capsule;

    public function __construct($capsule) {
        $this->capsule = $capsule;

    }

    public function loadDomain (){
        $domain = Site::where('domain', '=', $_SERVER['HTTP_HOST'])->first();
        if ($domain) {
            foreach ($domain->getAttributes() as $key => $value) {
                // Kontrola, zda hodnota je JSON a dekódování
                if (is_string($value) && is_array(json_decode($value, true)) && json_last_error() === JSON_ERROR_NONE) {
                    $value = json_decode($value, true);
                }
        
                // Přidání hodnoty do $_SERVER s prefixem 'SITE_'
                $_SERVER['SITE_' . strtoupper($key)] = $value;
            }
        }
        else{
            $logger->error('Nepodařilo se načíst data domény '.$_SERVER['HTTP_HOST']);
        }

    }

    public function loadSite(){

       return Url::where('url', '=', str_replace($_SERVER['SITE_DOMAIN'], '', $_SERVER['REQUEST_URI']))->first();    
       // echo $_SERVER['HTTP_HOST'].'<pre>' . print_r($url, true) . '</pre>'.'<pre>' . print_r($_SERVER, true) . '</pre>';
    }

    public static function get_home(){

    }

}

?>