<?php
namespace linkcms1\Models;

use PDO;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Model;
use linkcms1\Models\ArticleCategory;
use linkcms1\Models\Url;
use PHPAuth\Config as PHPAuthConfig;
use PHPAuth\Auth as PHPAuth;
use Monolog\Logger;
use Tracy\Debugger;
use Google\Cloud\Storage\StorageClient;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter; // Opravený import
use Exception;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use InvalidArgumentException;


class UploadedFile extends Model {
    protected $table = 'uploaded_files'; // Explicitně specifikuje název tabulky, pokud není standardní
    protected $fillable = ['user_id', 'site_id', 'name', 'file_path', 'mime_type', 'size', 'role', 'status']; // Pole, do kterých lze hromadně přiřazovat

    // Zde můžete přidat další metody modelu, jako jsou relace nebo vlastní dotazy
    public static function getAllFilesBySiteId() {
        return self::where('site_id', $_SERVER["SITE_ID"])
                   ->orderBy('id', 'DESC')
                   ->get();
    }
    

    public static function uploadFiles(array $files) {
        
        try {
            // Připojení k databázi a autentizace (uvedené mimo tento kód)
            $dbh = new PDO(
                'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
                $_SERVER['DB_USER'],
                $_SERVER['DB_PASSWORD'],
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
            );
            $config = new PHPAuthConfig($dbh);
            $auth = new PHPAuth($dbh, $config);
    
            $userId = $auth->getCurrentUID(); // Získání ID aktuálně přihlášeného uživatele
            if (!$userId) {
                throw new Exception("User is not authenticated.");
            }
     
            // Přeformátování pole $_FILES pro podporu jednoho i více souborů
            if (!is_array($files['name'])) {
                $files = [
                    'name' => [$files['name']],
                    'type' => [$files['type']],
                    'tmp_name' => [$files['tmp_name']],
                    'error' => [$files['error']],
                    'size' => [$files['size']],
                ];
            }
    
            // Nahrávání souborů
            foreach ($files['name'] as $key => $name) {
                $tempPath = $files['tmp_name'][$key];
                $fileSize = $files['size'][$key];
                $mimeType = $files['type'][$key];
                $normalizedFileName = self::normalizeFileName($name);
                $fileName = uniqid() . '-' . $normalizedFileName;
                $filePath = $_SERVER['domain']['gc_slozka'] . $fileName;
                $folderPath = $_SERVER['domain']['gc_slozka'];
                
                self::saveFileToGoogleCloud($tempPath, $fileName, $filePath,$folderPath);

                $publicUrl = "https://storage.googleapis.com/".$_SERVER['domain']['gc_bucket_name']."/{$filePath}";
                $alt = $title = $normalizedFileName;
    
                // Uložení informací o souboru do databáze
                $uploadedFile = new self();
                $uploadedFile->user_id = $userId; // Získání ID uživatele z autentizace
                $uploadedFile->site_id = $_SERVER["SITE_ID"]; // ID stránky nebo kontextu
                $uploadedFile->name = $name;
                $uploadedFile->file_path = $filePath;
                $uploadedFile->mime_type = $mimeType;
                $uploadedFile->size = $fileSize;
                $uploadedFile->alt = $alt;
                $uploadedFile->title = $title;
                $uploadedFile->public_url = $publicUrl;
                $uploadedFile->status = 'development';
                if($uploadedFile->save()){

                $originalImageId = $uploadedFile->id;
                }
                else{
                    die("problém s nahráváním");
                }
            
                // Generování a nahrávání miniatur, pokud je soubor obrázek
                if (in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['jpeg', 'jpg', 'png', 'gif', 'webp', 'svg'])) {
                    self::generateAndUploadThumbnails($tempPath, $name, $originalImageId);
                }
            }
    
            // Vrácení úspěchu
            return ['success' => true, 'message' => 'All files uploaded and saved successfully.'];
    
        } catch (Exception $e) {
            // Zalogování chyby
            error_log("Error in file upload: " . $e->getMessage());
            // Vrácení chyby
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleUploadRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file'])) {
            // Předání pole souborů z $_FILES do metody uploadFiles a získání výsledku
            $result = UploadedFile::uploadFiles($_FILES['file']);
    
            // Nastavení hlavičky pro odpověď ve formátu JSON
            header('Content-Type: application/json');
    
            // Vrácení JSON odpovědi
            echo json_encode($result);
            exit;
        } else {
            // Pokud data nebyla odeslána metodou POST, vrátit chybovou zprávu ve formátu JSON
            echo json_encode(['success' => false, 'message' => 'Invalid request or file upload error.']);
            exit;
        }
    }

    protected static function normalizeFileName($filename) {
        // Převod diakritiky na ASCII
        $filename = iconv('UTF-8', 'ASCII//TRANSLIT', $filename);
        
        // Odstranění všech znaků kromě alfanumerických a některých dalších povolených znaků
        $filename = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $filename);
        
        // Zkrácení názvu souboru na maximálně 50 znaků, pokud je potřeba
        if (strlen($filename) > 50) {
            $filename = substr($filename, 0, 50);
        }
        
        return $filename;
    }
    

    protected static function generateAndUploadThumbnails($tempPath, $name, $originalImageId) {
        $dimensions = self::getDimensionsFromServerVariables();
    
        // Vytvoření instance ImageManager s GD driverem
        // Můžete alternativně použít 'imagick', pokud je to preferované
        $manager = new ImageManager(new GdDriver());
    
        foreach ($dimensions as $prefix => $dim) {
            $normalizedFileName = self::normalizeFileName($name);
            $thumbnailFileName = uniqid() .  $normalizedFileName ."_".$prefix. '.webp';
            $thumbnailPath = $_SERVER['domain']['gc_slozka'] ."_thumb/". $thumbnailFileName;
            $folderPath = $_SERVER['domain']['gc_slozka'] ."_thumb/";
            $thumbnailPublicUrl = "https://storage.googleapis.com/".$_SERVER['domain']['gc_bucket_name']."/{$thumbnailPath}";
            
            $image = $manager->read($tempPath);

            // $image = $image->crop($dim['w'], $dim['h'], $x, $y);
            $image->scaleDown($dim['w'], $dim['h']); 
        
            // Provedení enkódování do formátu WEBP
            $encodedImage = $image->toWebp(75);
        
            // Uložení upraveného obrázku do dočasného souboru
            if(strlen($_SERVER["TMP_PATH"])>0){
                $tempFile = tempnam($_SERVER["TMP_PATH"], 'webp');
            }
            else{
                $tempFile = tempnam(sys_get_temp_dir(), 'webp');    
            }
            file_put_contents($tempFile, $encodedImage);
        
            // Nahrání miniatury do Google Cloud Storage
            self::saveFileToGoogleCloud($tempFile, $thumbnailFileName, $thumbnailPath, $folderPath);
        
            // Odstranění dočasného souboru
            unlink($tempFile);
        
            // Uložení informací o miniatuře do databáze
            $variant = new ImageVariant();
            $variant->original_image_id = $originalImageId;
            $variant->variant_name = $prefix;
            $variant->image_name = $thumbnailFileName;
            $variant->width = $dim['w'];
            $variant->height = $dim['h'];
            $variant->public_url = $thumbnailPublicUrl;
            $variant->save();
        }
    }

    protected static function getDimensionsFromServerVariables() {
        $dimensions = [];
        foreach ($_SERVER['domain'] as $key => $value) {
            if (preg_match('/(.+)_w$/', $key, $matches) && isset($_SERVER['domain'][$matches[1] . '_h'])) {
                $prefix = $matches[1];
                $dimensions[$prefix] = [
                    'w' => $_SERVER['domain'][$prefix . '_w'],
                    'h' => $_SERVER['domain'][$prefix . '_h'],
                ];
            }
        }
        return $dimensions;
    }
    
        
    /**
     * saveFileToGoogleCloud
     *
     * @param  mixed $file - dočasný soubor (tmp file)
     * @param  mixed $fileName - název souboru
     * @param  mixed $filePath - plná cesta souboru na google cloud
     * @param  mixed $folderPath - pouze cesta k souboru
     * @return void
     */
    protected static function saveFileToGoogleCloud($file, $fileName, $filePath, $folderPath){
        // Inicializace Google Cloud Storage klienta a Filesystem
        $storageClient = new StorageClient([
            'projectId' => $_SERVER['domain']['gc_project_id'],
            'keyFilePath' => $_SERVER['domain']['gc_key_json'],
        ]);

        $bucket = $storageClient->bucket($_SERVER['domain']['gc_bucket_name']);
        $adapter = new GoogleCloudStorageAdapter($bucket);
        $filesystem = new Filesystem($adapter);

        // Otevření streamu pro nahrávání
        $stream = fopen($file, 'r+');
        if ($stream === false) {
            throw new Exception("Unable to open file stream for '{$fileName}'.");
        }

        // Nahrání souboru na Google Cloud Storage
        $result = $filesystem->writeStream($filePath, $stream, ['visibility' => 'public']);
        if ($result === false) {
            throw new Exception("Failed to upload file '{$fileName}' to Google Cloud Storage.");
        }

        // Zavření streamu
        if (is_resource($stream)) {
            fclose($stream);
        }
        return true;
    }

    public function articles()
    {
        return $this->morphedByMany(Article::class, 'imageable', 'imageables', 'image_id', 'imageable_id')
                    ->withPivot('imageable_type');
    }

    public function categories()
    {
        return $this->morphedByMany(Category::class, 'imageable', 'imageables', 'image_id', 'imageable_id')
                    ->withPivot('imageable_type');
    }

    public static function getAllFilesWithVariants($type = false)
    {
        $query = self::with('variants')->orderBy('created_at', 'desc');
        if ($type == 'image') {
            $query->where('mime_type', 'like', 'image/%');
        } elseif ($type === 'file') {
            $query->where('mime_type', 'not like', 'image/%');
        }
        else{
            \Tracy\Debugger::barDump('Neznámý typ "'.$type.'" pro výběr obsahu');
        }

        $filesWithVariants = $query->get()->map(function ($file) {
            $variantsTransformed = [];

            foreach ($file->variants as $variant) {
                $variantsTransformed[$variant->variant_name] = $variant->toArray();
            }

            $file = $file->toArray();
            $file['variants'] = $variantsTransformed;

            return $file;
        });

        return $filesWithVariants->toArray();
    }

    public function variants()
    {
        return $this->hasMany(ImageVariant::class, 'original_image_id');
    }

    public function handleCKEditorUploadRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['upload'])) { // CKEditor očekává pole s názvem 'upload'
            // Předání pole souborů z $_FILES do metody uploadFiles a získání výsledku
            $result = UploadedFile::uploadFiles($_FILES['upload']); // Upravte 'upload' podle potřeby
    
            header('Content-Type: application/json'); // Nastavení hlavičky pro odpověď ve formátu JSON
    
            if ($result['success']) {
                // Pokud bylo nahrávání úspěšné, vrátit URL k nahrávanému obrázku ve formátu očekávaném CKEditorem
                echo json_encode(['uploaded' => 1, 'fileName' => $result['name'], 'url' => $result['public_url']]);
            } else {
                // Pokud došlo k chybě, vrátit informace o chybě
                echo json_encode(['uploaded' => 0, 'error' => ['message' => $result['message']]]);
            }
            exit;
        } else {
            // Pokud data nebyla odeslána metodou POST, vrátit chybovou zprávu ve formátu JSON
            echo json_encode(['uploaded' => 0, 'error' => ['message' => 'Invalid request or file upload error.']]);
            exit;
        }
    }

    /**
     * Vytvoří adresář a podsložku na Google Cloud Storage.
     *
     * @param string $domain Název domény
     * @return void
     */
    public static function createGoogleCloudDirectory($domain)
    {
        // Konfigurace a vytvoření instance klienta
        $storageClient = new StorageClient([
            'projectId' => $_SERVER['domain']['gc_project_id'],
            'keyFilePath' => $_SERVER['domain']['gc_key_json'],
        ]);

        $bucket = $storageClient->bucket($_SERVER['domain']['gc_bucket_name']);

        // Složky, které chceme vytvořit
        $folders = [
            $domain . '/',
            $domain . '/_thumb/'
        ];

        // Vytváření složek, pokud neexistují
        foreach ($folders as $folder) {
            $object = $bucket->object($folder);
            if (!$object->exists()) {
                // Objekt neexistuje, vytvoření prázdného objektu jako složky
                $bucket->upload('', [
                    'name' => $folder,
                    'predefinedAcl' => 'publicRead' // Přidáno pro nastavení ACL, pokud potřebujete objekt veřejně čitelný
                ]);
            }
        }
    }

    /**
     * Uloží nebo aktualizuje obrázek v databázi.
     * @param array $data Pole s daty pro uložení nebo aktualizaci.
     * @return array
     */
    public static function saveImageData($data)
    {
        $response = ['success' => false, 'message' => '', 'data' => null];

        try {
            // Ověření dat
            if (empty($data['name']) || empty($data['title']) || empty($data['alt']) || empty($data['status'])) {
                throw new InvalidArgumentException("Všechny políčka musí být vyplněna.");
            }

            // Sanitace dat
            $data['name'] = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
            $data['title'] = htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8');
            $data['alt'] = htmlspecialchars($data['alt'], ENT_QUOTES, 'UTF-8');
            $data['status'] = htmlspecialchars($data['status'], ENT_QUOTES, 'UTF-8');

            // Kontrola, zda status je v platném rozsahu
            $validStatuses = ['active', 'development', 'hidden', 'suspend', 'deleted'];
            if (!in_array($data['status'], $validStatuses)) {
                throw new InvalidArgumentException("Neplatný status obrázku.");
            }

            if (isset($data['id'])) {
                // Pokud je poskytnuto ID, najde se existující záznam
                $image = self::find($data['id']);
                if (!$image) {
                    throw new Exception("Obrázek s ID {$data['id']} nebyl nalezen.");
                }
            } else {
                // Vytvoření nového záznamu, pokud ID není poskytnuto
                $image = new self;
            }

            // Nastavení atributů
            $image->name = $data['name'];
            $image->title = $data['title'];
            $image->alt = $data['alt'];
            $image->status = $data['status'];

            // Uložení záznamu do databáze
            $image->save();

            $response['success'] = true;
            $response['message'] = 'Obrázek byl úspěšně uložen.';
            $response['data'] = $image;
        } catch (Exception $e) {
            $response['message'] = "Chyba při ukládání obrázku: " . $e->getMessage();
        }

        return $response;
    }

    public function handleSaveImageData() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Získání dat z POST requestu
            $data = [
                'id' => $_POST['id'] ?? null,  // použití null, pokud 'id' není poskytnuto
                'name' => $_POST['name'] ?? '', // předpokládáme, že tyto položky existují v POST data
                'title' => $_POST['title'] ?? '',
                'alt' => $_POST['alt'] ?? '',
                'status' => $_POST['status'] ?? ''
            ];
    
            // Volání metody pro uložení dat
            $response = self::saveImageData($data);
    
            // Odeslání JSON odpovědi
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } else {
            // Neplatný požadavek
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Neplatný požadavek.']);
            exit;
        }
    }

}

?>