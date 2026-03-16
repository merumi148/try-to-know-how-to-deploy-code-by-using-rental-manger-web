<?php
declare(strict_types=1);

class Database
{
    private string $host = '127.0.0.1';
    private string $dbName = 'quanlyphongtro_db';
    private string $username = 'root';
    private string $password = '';
    private string $charset = 'utf8mb4';

    public function getConnection(): PDO
    {
        $host = getenv('DB_HOST') ?: $this->host;
        $dbName = getenv('DB_NAME') ?: $this->dbName;
        $username = getenv('DB_USER') ?: $this->username;
        $password = getenv('DB_PASS') ?: $this->password;

        $dsn = "mysql:host={$host};dbname={$dbName};charset={$this->charset}";

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function getMysqliConnection(): mysqli
    {
        $host = getenv('DB_HOST') ?: $this->host;
        $dbName = getenv('DB_NAME') ?: $this->dbName;
        $username = getenv('DB_USER') ?: $this->username;
        $password = getenv('DB_PASS') ?: $this->password;

        $db = new mysqli($host, $username, $password, $dbName);
        if ($db->connect_errno) {
            throw new RuntimeException('Database connection failed: ' . $db->connect_error);
        }

        if (!$db->set_charset($this->charset)) {
            throw new RuntimeException('Failed to set charset: ' . $db->error);
        }

        return $db;
    }
}
