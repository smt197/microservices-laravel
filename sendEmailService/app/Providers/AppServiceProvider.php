<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         $this->createDatabaseIfNotExists();
    }

        private function createDatabaseIfNotExists(): void
    {
        try {
            $database = Config::get('database.connections.mysql.database');
            $username = Config::get('database.connections.mysql.username');
            $password = Config::get('database.connections.mysql.password');
            $host = Config::get('database.connections.mysql.host');
            $port = Config::get('database.connections.mysql.port', 3306);

            if (!$database) {
                Log::warning('No database name configured');
                return;
            }

            // Create a connection without specifying the database
            $pdo = new \PDO(
                "mysql:host={$host};port={$port}",
                $username,
                $password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );

            // Check if database exists
            $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$database]);

            if ($stmt->rowCount() === 0) {
                // Database doesn't exist, create it
                $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                Log::info("Database '{$database}' created successfully");
            } else {
                Log::info("Database '{$database}' already exists");
            }

        } catch (\PDOException $e) {
            Log::error("Failed to create database: " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Error in database creation: " . $e->getMessage());
        }
    }
}
