<?php
declare(strict_types=1);

/*
 * ISLANDS GEB Portal - standalone configuration.
 * No Drupal dependency.
 */
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'islands_geb_portal';
const DB_USER = 'root';
const DB_PASS = '';

const APP_NAME = 'ISLANDS GEB Portal';
const APP_TAGLINE = 'Monitoring & Reporting for Global Environmental Benefits';
const APP_URL = '/islands_geb_portal';
const APP_VERSION = '2.1.0-export-ready';

const UPLOAD_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
const MAX_UPLOAD_BYTES = 20 * 1024 * 1024;

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
