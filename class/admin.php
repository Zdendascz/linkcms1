<?php
namespace linkcms1;
use Illuminate\Database\Capsule\Manager as Capsule;
use linkcms1\Models\User as EloquentUser;
use linkcms1\Models\Site;
use linkcms1\Models\Url;
use linkcms1\Models\Category;
use Monolog\Logger;
use Tracy\Debugger;
use PHPAuth\Auth as PHPAuth;
use linkcms1\Models\UserDetails;

Debugger::enable(Debugger::DEVELOPMENT);
/**
 * Třída reprezentující web, zpracovává kontrolu domény a informace o ní.
 * také zpracovává informace o konkrétní stránce
 */
class adminControl {

    protected $capsule;
    protected $logger;
    protected $auth;

    public function __construct($capsule, Logger $logger, \PHPAuth\Auth $auth) {
        $this->capsule = $capsule;
        $this->logger = $logger;
        $this->auth = $auth;
    }
    
    /**
     * loginUser funkce zajišťuje přihlášení uživatele
     *
     * @param  mixed $email
     * @param  mixed $password
     * @param  mixed $remember
     * @return void
     */
    public function loginUser($email, $password, $remember = false) {
        // Ověření vstupních dat
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "Neplatný formát e-mailu";
            return false;
        }
    
        // Zde můžete přidat logiku pro kontrolu počtu neúspěšných pokusů
    
        $result = $this->auth->login($email, $password, $remember);
    
        // Záznam pokusu o přihlášení
        $this->logger->debug("Pokus o přihlášení pro uživatele: $email");
    
        if ($result['error']) {
            $this->logger->warning("Neúspěšný pokus o přihlášení pro uživatele: $email");
            return ['success' => false, 'message' => "Přihlášení selhalo: " . $result['message']];
        } else {
            $this->logger->debug("Uživatel: $email přihlášen");
            return ['success' => true, 'message' => "Uživatel byl úspěšně přihlášen"];
        }
    }
       
        
    /**
     * isUserLoggedIn funkce ověřuje, zda je uživtel přihlášen
     *
     * @return void
     */
    public function isUserLoggedIn() {
        return $this->auth->isLogged();
    }

    public function getUserData() {
        if ($this->auth->isLogged()) {
            $userId = $this->auth->getCurrentUID(); // Získá aktuální UID uživatele
            $user = $this->auth->getUser($userId);  // Získá informace o uživateli
    
            // Příklad: Vrátí jen některé informace o uživateli
            return [
                'uid' => $user['uid'],
                'email' => $user['email'],
                // Přidat další relevantní data, která chcete zahrnout
            ];
        } else {
            return null; // Nebo můžete vrátit prázdné pole nebo chybovou zprávu
        }
    }
        
    /**
     * logoutUser Bezpečné odhlášení uživatele
     *
     * @return void
     */
    public function logoutUser() {
        $currentSessionHash = $this->auth->getCurrentSessionHash();
        if (!$currentSessionHash) {
            $this->logger->info("Odhlášení uživatele se nezdařilo: žádná aktivní session");
            return false;
        }
    
        $result = $this->auth->logout($currentSessionHash);
    
        if ($result) {
            $this->logger->info("Uživatel byl úspěšně odhlášen.");
            // Zde můžete přidat další akce po úspěšném odhlášení, jako je přesměrování
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        } else {
            $this->logger->warning("Nastal problém při odhlášení uživatele.");
            // Zde můžete zpracovat chybu odhlášení
            return false;
        }
    }
        
    /**
     * registerUser Registrace uživatele
     *
     * @param  mixed $email
     * @param  mixed $password
     * @param  mixed $repeatpassword
     * @return void
     */
    public function registerUser() {
        $postData = $_POST;
        $loginResult = [];
        // Extrahování údajů z $postData
        $email = $postData['email'] ?? '';
        $password = $postData['password'] ?? '';
        $repeatpassword = $postData['repeat_password'] ?? '';
        $fullname = $postData['fullname'] ?? '';
        $phone = $postData['phone'] ?? '';
        // ... (extrakce ostatních polí) ...

        // Ověření platnosti e-mailu
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $loginResult['message'] = "Neplatný formát e-mailu: $email";
            return false;
        }

        // Kontrola shody hesel
        if ($password !== $repeatpassword) {
            $loginResult['message'] = "Hesla se neshodují pro e-mail: $email";
            return false;
        }

        // Kontrola silného hesla
        if (!preg_match('/^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/', $password)) {
            $loginResult['message'] = "Hesla se neshodují pro e-mail: $email";
            return false;
        }

        // Pokus o registraci uživatele
        $result = $this->auth->register($email, $password, $repeatpassword);

        if ($result['error']) {
            // Registrace selhala
            $this->logger->warning("Registrace uživatele selhala: " . $result['message']);
            header('Location: '.$_SERVER['SITE_WEB'].'/admin/login/sign-up.html?error=' . urlencode($loginResult['message']));
        } else {
            // Registrace byla úspěšná
            $userId = $result['uid'];
            $this->logger->info("Uživatel byl úspěšně zaregistrován: $email id: $userId");
            // Uložení dodatečných informací do tabulky user_details
            $userDetails = new UserDetails();
            $userDetails->user_id = $userId;
            $userDetails->fullname = $fullname;
            $userDetails->phone = $phone;
            // ... (nastavení ostatních polí) ...
            $userDetails->save();

            header('Location: '.$_SERVER['SITE_WEB'].'/admin/login/regFinal');
        }
    }
    
    
    /**
     * loginHandler Přihlašovací handler
     *
     * @return void
     */
    public function loginHandler() {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
    
        $loginResult = $this->loginUser($email, $password);
    
        if ($loginResult['success']) {
            // Přesměrujte na stránku po úspěšném přihlášení
            $this->logger->debug("Pokus o přihlášení uživatele: $email úspěšný, předán hedaer: ".$_SERVER['SITE_WEB']."/admin/");
            header('Location: ' . $_SERVER['SITE_WEB'].'/admin/');
        } else {
            // Informujte uživatele o chybě a přesměrujte na přihlašovací stránku
            // Můžete použít session nebo GET parametry pro předání zprávy
            $this->logger->debug("Pokus o přihlášení uživatele: $email nebyl úspěšný, loginHandler() přijal false, zpráva: ".$loginResult['message']);
            header('Location: '.$_SERVER["SITE_WEB"].'/admin/error/?error=' . urlencode($loginResult['message']));
        }
        exit;
    }

    /**
     * Získá všechny role daného uživatele.
     * 
     * @param int $userId ID uživatele.
     * @return Collection Kolekce všech rolí uživatele.
     */
    public function getUserRoles($userId) {
        if(!$userId){ return false;}
        return $this->capsule::table('user_site_roles')
                            ->join('roles', 'user_site_roles.role_id', '=', 'roles.id')
                            ->where('user_site_roles.user_id', $userId)
                            ->pluck('roles.name');
    }

    /**
     * Ověří, zda je uživatel vlastníkem konkrétní role nebo je superadmin.
     * 
     * @param int $userId ID uživatele.
     * @param string $roleName Název role.
     * @param int $siteId ID webu (site).
     * @return bool Vrací true, pokud uživatel má roli nebo je superadmin, jinak false.
     */
    public function isUserInRole($userId, $roleName, $siteId) {
        if(!$userId){ return false;}
        $roles = $this->getUserRoles($userId);

        // Vždy vrací true, pokud je uživatel superadmin
        if ($roles->contains('superadmin')) {
            return true;
        }

        // Kontrola, zda má uživatel specifickou roli pro daný web
        return $this->capsule::table('user_site_roles')
                            ->join('roles', 'user_site_roles.role_id', '=', 'roles.id')
                            ->where('user_site_roles.user_id', $userId)
                            ->where('user_site_roles.site_id', $siteId)
                            ->where('roles.name', $roleName)
                            ->exists();
    }

    /**
     * Ověří, zda má uživatel specifické oprávnění na dané site.
     * 
     * @param int $userId ID uživatele.
     * @param string $permissionName Název oprávnění.
     * @param int $siteId ID webu (site).
     * @return bool Vrací true, pokud má uživatel oprávnění, nebo je superadmin, jinak false.
     */
    public function hasPermission($userId, $permissionName, $siteId) {
        if(!$userId){ return false;}
        $roles = $this->getUserRoles($userId);

        // Vrací true, pokud je uživatel superadmin
        if ($roles->contains('superadmin')) {
            return true;
        }

        // Získá ID rolí pro danou site
        $roleIds = $this->capsule::table('user_site_roles')
                                ->where('user_id', $userId)
                                ->where('site_id', $siteId)
                                ->pluck('role_id');

        // Ověřuje, zda některá z rolí uživatele má požadované oprávnění
        return $this->capsule::table('role_permissions')
                            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                            ->whereIn('role_permissions.role_id', $roleIds)
                            ->where('permissions.name', $permissionName)
                            ->exists();
    }

    public function getAdministration(){

    }
    
    /**
     * getSitesWithUserRoles
     * vrací všechny stránky, kde má uživatel nějakou roli
     *
     * @param  mixed $userId
     * @return void
     */
    public function getSitesWithUserRoles($userId) {
        if (!$userId) { return []; }
    
        return $this->capsule::table('user_site_roles')
                             ->join('sites', 'user_site_roles.site_id', '=', 'sites.id')
                             ->where('user_site_roles.user_id', $userId)
                             ->distinct()
                             ->get(['sites.*']);
    }
    
    /**
     * getAllPermissions
     * funkce vrací všechna oprávnění v systému, řazené abecedně
     * @return void
     */
    public function getAllPermissions() {
        // Získání všech oprávnění z databáze a seřazení podle názvu abecedně
        return $this->capsule::table('permissions')->orderBy('name', 'asc')->get()->toArray();
    }
    

           
    /**
     * getAllRoles
     * funkce vrací všechny role v systému abecedně
     *
     * @return void
     */
    public function getAllRoles() {
        // Získání všech rolí z databáze a seřazení podle názvu abecedně
        return $this->capsule::table('roles')->orderBy('name', 'asc')->get()->toArray();
    } 
        
    /**
     * saveRole
     * funkce ukládá roli, ale bez oprávnění
     * @param  mixed $data
     * @return void
     */
    public function saveRole($data) {
        // Kontrola, zda je nastaveno ID a není prázdné
        if (isset($data['id']) && !empty($data['id'])) {
            // Aktualizace existující role
            $role = $this->capsule::table('roles')->where('id', $data['id'])->first();
    
            if ($role) {
                // Role existuje, provedeme aktualizaci
                $this->capsule::table('roles')->where('id', $data['id'])->update([
                    'name' => $data['name'],
                    'description' => $data['description']
                ]);
            } else {
                // Role s daným ID neexistuje, vrátíme chybu nebo null
                return null;
            }
        } else {
            // Vytvoření nové role
            $this->capsule::table('roles')->insert([
                'name' => $data['name'],
                'description' => $data['description']
            ]);
        }
    
        return true;
    }
    
    /**
     * savePermission
     * funkce ukládá oprávnění
     *
     * @param  mixed $data
     * @return void
     */
    public function savePermission($data) {
        // Kontrola, zda je nastaveno ID a není prázdné
        if (isset($data['id']) && !empty($data['id'])) {
            // Aktualizace existujícího oprávnění
            $permission = $this->capsule::table('permissions')->where('id', $data['id'])->first();
    
            if ($permission) {
                // Oprávnění existuje, provedeme aktualizaci
                $this->capsule::table('permissions')->where('id', $data['id'])->update([
                    'name' => $data['name'],
                    'description' => $data['description']
                ]);
            } else {
                // Oprávnění s daným ID neexistuje, vrátíme chybu nebo null
                return null;
            }
        } else {
            // Vytvoření nového oprávnění
            $this->capsule::table('permissions')->insert([
                'name' => $data['name'],
                'description' => $data['description']
            ]);
        }
    
        return true;
    }
    
    /**
     * getAllRolesWithPermissionsSorted
     * funkce vrací všechny role v systému abecedně a u každé jsou oprávnění
     * také abecedně
     *
     * @return void
     */
    public function getAllRolesWithPermissionsSorted() {
        // Získání všech rolí seřazených abecedně
        $roles = $this->capsule::table('roles')
                               ->orderBy('name', 'asc')
                               ->get();
        
        $rolesArray = [];
    
        foreach ($roles as $role) {
            // Pro každou roli získáme její oprávnění seřazená abecedně
            $permissions = $this->capsule::table('role_permissions')
                                         ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                                         ->where('role_permissions.role_id', $role->id)
                                         ->orderBy('permissions.name', 'asc')
                                         ->get(['permissions.name', 'permissions.description']);
    
            // Převod oprávnění na pole
            $permissionsArray = [];
            foreach ($permissions as $permission) {
                $permissionsArray[] = (array) $permission;
            }
    
            $roleArray = (array) $role;
            $roleArray['permissions'] = $permissionsArray;
            $rolesArray[] = $roleArray;
        }
    
        return $rolesArray;
    }
    
    
    /**
     * updateRolePermissions 
     * funkce aktualizuje všechna oprávnění dané role
     *
     * @param  mixed $roleId
     * @param  mixed $permissionIds
     * @return void
     */
    public function updateRolePermissions($roleId, $permissionIds) {
        // Nejprve odstraníme všechny záznamy, které nejsou v novém seznamu oprávnění
        $this->capsule::table('role_permissions')
                      ->where('role_id', $roleId)
                      ->whereNotIn('permission_id', $permissionIds)
                      ->delete();
    
        // Nyní přidáme nová oprávnění, pokud již neexistují
        foreach ($permissionIds as $permissionId) {
            $exists = $this->capsule::table('role_permissions')
                                    ->where('role_id', $roleId)
                                    ->where('permission_id', $permissionId)
                                    ->exists();
    
            if (!$exists) {
                // Zápis do tabulky role_permissions, pokud kombinace neexistuje
                $this->capsule::table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId
                ]);
            }
        }
    
        return true;
    }
        
}

?>