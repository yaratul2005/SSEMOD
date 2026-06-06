<?php
declare(strict_types=1);

namespace App\Database;

use Exception;

class Migration {
    public function __construct(private readonly Connection $connection) {}

    /**
     * Run sql queries from standard schema.sql to initialize database.
     */
    public function run(string $sqlFilePath): void {
        if (!file_exists($sqlFilePath)) {
            throw new Exception("SQL schema file not found at: {$sqlFilePath}");
        }

        $pdo = $this->connection->getPdo();
        $sql = file_get_contents($sqlFilePath);
        
        // Simple SQL query parser splitting on semicolon
        $queries = explode(';', $sql);
        
        foreach ($queries as $query) {
            $query = trim($query);
            if ($query === '') {
                continue;
            }
            try {
                $pdo->exec($query);
            } catch (Exception $e) {
                throw new Exception("Failed to execute query: {$query}. Error: " . $e->getMessage(), 0, $e);
            }
        }
    }
}
