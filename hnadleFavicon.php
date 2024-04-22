<?php
// Specifikujte URL vaší preferované favicon
$customFaviconUrl = "http://example.com/path/to/your/favicon.ico";
$defaultFavicon = "https://mini-web.cz/templates/mini-web/images/favicon.png";  // Cesta k vaší defaultní favicon

// Zkuste získat hlavičky pro specifickou favicon
$headers = @get_headers($customFaviconUrl);
if($headers && strpos( $headers[0], '200')) {
    header('Location: ' . $customFaviconUrl);
    exit;
} else {
    header('Content-Type: image/x-icon');
    readfile($_SERVER['DOCUMENT_ROOT'] . $defaultFavicon);
    exit;
}
