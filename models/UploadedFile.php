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

class UploadedFile extends Model {
    protected $table = 'uploaded_files'; // Explicitně specifikuje název tabulky, pokud není standardní
    protected $fillable = ['user_id', 'site_id', 'name', 'file_path', 'mime_type', 'size', 'role', 'status']; // Pole, do kterých lze hromadně přiřazovat

    // Zde můžete přidat další metody modelu, jako jsou relace nebo vlastní dotazy

    /**
     * Nahraje soubor na Google Cloud Storage.
     * 
     * @param string $filePath Cesta k souboru, který má být nahrán.
     * @param string $fileName Název souboru, pod kterým má být uložen na Google Cloud Storage.
     * @return string Vrací cestu k souboru na Google Cloud Storage.
     */
    public static function uploadToGoogleCloud($filePath, $fileName) {
        $projectId = 'your-google-cloud-project-id';
        $bucketName = 'your-bucket-name';
        $keyFilePath = 'path/to/your/google-cloud-key.json';

        $storage = new StorageClient([
            'projectId' => $projectId,
            'keyFilePath' => $keyFilePath,
        ]);

        $bucket = $storage->bucket($bucketName);

        // Generování unikátní cesty pro soubor, aby se předešlo konfliktům jmen
        $targetPath = "uploads/" . uniqid() . "_" . $fileName;

        // Nahrání souboru
        $bucket->upload(fopen($filePath, 'r'), [
            'name' => $targetPath
        ]);

        // Vrácení cesty k souboru na Google Cloud Storage
        return $targetPath;
    }
}

?>