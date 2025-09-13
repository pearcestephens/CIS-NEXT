<?php
declare(strict_types=1);

namespace App\Infra\Persistence\MariaDB;

/**
 * Migration Interface
 * Base class for database migrations
 */
abstract class Migration
{
    abstract public function up(Database $db): void;
    abstract public function down(Database $db): void;
}
