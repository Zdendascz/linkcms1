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


class UploadedFile extends Model {
    protected $table = 'uploaded_files'; // Explicitně specifikuje název tabulky, pokud není standardní
    protected $fillable = ['user_id', 'site_id', 'name', 'file_path', 'mime_type', 'size', 'role', 'status']; // Pole, do kterých lze hromadně přiřazovat

    // Zde můžete přidat další metody modelu, jako jsou relace nebo vlastní dotazy
    public static function getAllFilesBySiteId($siteId) {
        return self::where('site_id', $siteId)->get();
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
    
            // Inicializace Google Cloud Storage klienta a Filesystem
            $projectId = $_SERVER['domain']['gc_project_id'];
            $keyFilePath = $_SERVER['domain']['gc_key_json'];
            $bucketName = $_SERVER['domain']['gc_bucket_name'];
            $storageClient = new StorageClient([
                'projectId' => $projectId,
                'keyFilePath' => $keyFilePath,
            ]);
    
            $bucket = $storageClient->bucket($bucketName);
            $adapter = new GoogleCloudStorageAdapter($bucket);
            $filesystem = new Filesystem($adapter);
    
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
    
                // Otevření streamu pro nahrávání
                $stream = fopen($tempPath, 'r+');
                if ($stream === false) {
                    throw new Exception("Unable to open file stream for '{$name}'.");
                }
    
                // Nahrání souboru na Google Cloud Storage
                $result = $filesystem->writeStream($filePath, $stream, ['visibility' => 'public']);
                if ($result === false) {
                    throw new Exception("Failed to upload file '{$name}' to Google Cloud Storage.");
                }
    
                // Zavření streamu
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $publicUrl = "https://storage.googleapis.com/{$bucketName}/{$filePath}";
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
                $uploadedFile->save();
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
        
        return $filename;
    }

}

?>