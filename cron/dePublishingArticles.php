<?php
require __DIR__ . '/../cron/bootstrap.php';

use linkcms1\Models\Article;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

// Nastavení loggeru
$logger = new Logger('updateArticleStatus');
$logHandler = new RotatingFileHandler(__DIR__ . '/../logs/updateArticleStatus.log', 0, Logger::INFO);
$logger->pushHandler($logHandler);

// Získání aktuálního data a času
$currentDateTime = date('Y-m-d H:i:s');

// info o startu cronu
$logger->info('Cron Depublikace článků proběhl', ['time' => $currentDateTime]);

// Načtení článků, které mají status 'development' a jejich publish_at je starší než aktuální datum a čas
$articles = Article::where('status', 'active')
                    ->where('publish_end_at', '<', $currentDateTime)
                    ->get();

// Procházení článků a aktualizace jejich statusu na 'active'
foreach ($articles as $article) {
    $article->status = 'suspend';
    $article->save();

    // Zápis do loggeru
    $logger->info('Article status updated', ['id' => $article->id, 'time' => $currentDateTime]);
}
