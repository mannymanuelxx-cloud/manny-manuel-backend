<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Migration
{
    public static $command = 'migration';
    public static $description = 'Run database migrations';

    protected static $route_map = [
        'run' => 'migrate',
        'create-migration' => 'create-migration',
        'rollback' => 'rollback',
        'rollback-all' => 'rollback-all',
        'refresh' => 'refresh',
        'status' => 'status',
    ];

    public function handle($action = null, array $flags = [], $name = null)
    {
        $action = $action ?? 'run';
        if (!isset(static::$route_map[$action])) {
            fwrite(STDERR, 'Unknown migration action: ' . $action . PHP_EOL);
            fwrite(STDERR, 'Available actions: ' . implode(', ', array_keys(static::$route_map)) . PHP_EOL);
            exit(1);
        }

        if ($action === 'create-migration') {
            if (!$name || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
                fwrite(STDERR, 'A valid migration class name is required.' . PHP_EOL);
                exit(1);
            }
            $route = 'create-migration/' . $name;
        } else {
            $route = static::$route_map[$action];
        }

        $index = PUBLIC_DIR . 'index.php';
        if (!file_exists($index)) {
            fwrite(STDERR, 'index.php not found at: ' . $index . PHP_EOL);
            exit(1);
        }

        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($index) . ' ' . escapeshellarg($route);
        passthru($command, $exit_code);
        if ($exit_code !== 0) {
            exit($exit_code);
        }
    }
}