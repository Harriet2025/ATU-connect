<?php

require_once __DIR__ . '/../core/Database.php';

abstract class Model
{
    protected static function db(): PDO
    {
        return Database::getInstance();
    }
}
