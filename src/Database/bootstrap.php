<?php

/**
 * Loads the optional database declaration graph explicitly for compiled PHP and native
 * applications.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

// Optional declaration graph. Database-free entries do not load or link PDO.
require_once __DIR__.'/../Contracts/Kernel/ScopeResource.php';
require_once __DIR__.'/../Kernel/Scope/CleanupFailed.php';
require_once __DIR__.'/../Kernel/Scope/CleanupErrors.php';
require_once __DIR__.'/Parameters.php';
require_once __DIR__.'/MappingError.php';
require_once __DIR__.'/DatabaseError.php';
require_once __DIR__.'/ConnectionConfig.php';
require_once __DIR__.'/Row.php';
require_once __DIR__.'/QueryResult.php';
require_once __DIR__.'/../Contracts/Database/RowMapper.php';
require_once __DIR__.'/../Contracts/Database/Connection.php';
require_once __DIR__.'/PDOConnection.php';
require_once __DIR__.'/Session.php';
