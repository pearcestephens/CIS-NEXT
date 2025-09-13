<?php
declare(strict_types=1);

namespace App\Infra\Persistence\MariaDB;

/**
 * Seeder Interface
 * Base class for database seeders
 */
abstract class Seeder
{
    abstract public function run(Database $db): void;
}
