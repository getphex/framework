<?php

declare(strict_types=1);

use Phex\Config\Environment;
use Phex\Database\PdoConnection;
use Phex\Database\Session;
use Phex\Examples\Database\DatabaseFactory;
use Phex\Runtime\Environment\ProcessEnvironment;

require_once __DIR__.'/../../vendor/autoload.php';

$connection = PdoConnection::connect(DatabaseFactory::configuration(new Environment(new ProcessEnvironment())));
$database = new Session($connection);
try {
    $database->execute('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
} finally {
    $database->close();
    $connection->close();
}
echo "Database schema ready.\n";
