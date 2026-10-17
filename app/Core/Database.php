<?php
namespace EpaperSaas\Core;
final class Database
{
    private static ?\PDO $pdo=null;
    public static function pdo(): \PDO
    {
        if(self::$pdo)return self::$pdo;$dsn='mysql:host='.Config::get('db.host').';port='.(int)Config::get('db.port',3306).';dbname='.Config::get('db.name').';charset='.Config::get('db.charset','utf8mb4');
        self::$pdo=new \PDO($dsn,(string)Config::get('db.user'),(string)Config::get('db.pass'),[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,\PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC,\PDO::ATTR_EMULATE_PREPARES=>false]);return self::$pdo;
    }
}
