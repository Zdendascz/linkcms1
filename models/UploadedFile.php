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
            $dbh = new PDO(
                'mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'],
                $_SERVER['DB_USER'],
                $_SERVER['DB_PASSWORD'],
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
            );
            $config = new PHPAuthConfig($dbh);
            $auth = new PHPAuth($dbh, $config);
        
            $userId = $auth->getCurrentUID();
            $siteId = $_SERVER['SITE_ID'];

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

            // Přeformátování pro podporu jednoho souboru
            if (!is_array($files['name'])) {
                $files = [
                    'name' => [$files['name']],
                    'type' => [$files['type']],
                    'tmp_name' => [$files['tmp_name']],
                    'error' => [$files['error']],
                    'size' => [$files['size']],
                ];
            }

            foreach ($files['name'] as $key => $name) {
                try {
                    $stream = fopen($tempPath, 'r+');
                    if ($stream === false) {
                        throw new Exception("Unable to open file stream for '{$name}'.");
                    }
                
                    $result = $filesystem->writeStream($filePath, $stream, ['visibility' => 'public']);
                    if ($result === false) {
                        throw new Exception("Failed to upload file '{$name}' to Google Cloud Storage.");
                    }
                
                    fclose($stream);
                } catch (Exception $e) {
                    // Log the exception message
                    error_log("Error uploading '{$name}': " . $e->getMessage());
                    // Handle the error, e.g. by returning an error response to the user
                    return ['success' => false, 'message' => $e->getMessage()];
                }

                $tempPath = $files['tmp_name'][$key];
                $fileSize = $files['size'][$key];
                $mimeType = $files['type'][$key];
                $fileName = uniqid() . '-' . basename($name);
                $filePath = $_SERVER['domain']['gc_slozka'] . $fileName;

                $stream = fopen($tempPath, 'r+');
                if (!$filesystem->writeStream($filePath, $stream, ['visibility' => 'public'])) {
                    throw new Exception("Failed to upload file '{$name}' to Google Cloud Storage.");
                }
                if (is_resource($stream)) {
                    fclose($stream);
                }

                $uploadedFile = new self();
                $uploadedFile->user_id = $userId;
                $uploadedFile->site_id = $siteId;
                $uploadedFile->name = $name;
                $uploadedFile->file_path = $filePath;
                $uploadedFile->mime_type = $mimeType;
                $uploadedFile->size = $fileSize;
                $uploadedFile->status = 'development';
                if (!$uploadedFile->save()) {
                    throw new Exception("Failed to save file information for '{$name}' in the database.");
                }
            }

            return ['success' => true, 'message' => 'All files uploaded and saved successfully.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function handleUploadRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['file'])) {
            // Předání pole souborů z $_FILES do metody uploadFiles a získání výsledku
            $result = UploadedFile::uploadFiles($_FILES['file']);
            
            // Rozhodnutí na základě výsledku operace
            if ($result['success']) {
                // Úspěch: Přesměrování s úspěšnou zprávou
                header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=success&message=' . urlencode($result['message']));
                exit;
            } else {
                // Neúspěch: Přesměrování s chybovou zprávou
                header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode($result['message']));
                exit;
            }
        } else {
            // Pokud data nebyla odeslána metodou POST, přesměrování zpět s chybovou zprávou
            header('Location: ' . $_SERVER['HTTP_REFERER'] . '?status=error&message=' . urlencode('Invalid request or file upload error.'));
            exit;
        }
    }

}

?>