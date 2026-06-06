<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

class Connection {
    private ?PDO $pdo = null;

    public function __construct(private readonly array $config) {}

    /**
     * Get active PDO instance, establishing connection if not already active.
     */
    public function getPdo(): PDO {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            try {
                $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                throw new RuntimeException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
            }
        }
        return $this->pdo;
    }

    /**
     * Disconnect from the database. Releases the connection resource.
     */
    public function disconnect(): void {
        $this->pdo = null;
    }
}
