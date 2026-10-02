<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class MigrationController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('migration');
    }

    private function guard_http_migrations()
    {
        if (PHP_SAPI !== 'cli' && config_item('ENVIRONMENT') === 'production') {
            http_response_code(404);
            exit('Not found');
        }
    }

    public function create_migration($migration_class)
    {
        $this->guard_http_migrations();
        if (!preg_match('/^[A-Za-z0-9_]+$/', $migration_class)) {
            http_response_code(400);
            exit('Invalid migration name');
        }
        $this->migration->create_migration($migration_class);
    }

    public function migrate()
    {
        $this->guard_http_migrations();
        $this->migration->migrate();
    }

    public function rollback()
    {
        $this->guard_http_migrations();
        $this->migration->rollback();
    }

    public function rollback_all()
    {
        $this->guard_http_migrations();
        $this->migration->rollback_all();
    }

    public function refresh()
    {
        $this->guard_http_migrations();
        $this->migration->refresh();
    }

    public function status()
    {
        $this->guard_http_migrations();
        $this->migration->status();
    }
}