<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
    }

    private function require_database_configuration()
    {
        $required = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
        $missing = array_filter($required, function ($key) {
            return getenv($key) === false || getenv($key) === '';
        });

        if ($missing) {
            $this->api->respond_error(
                'Database is not configured. Set these backend environment variables: ' . implode(', ', $missing) . '.',
                503
            );
        }
    }

    public function register()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit();
        $input = $this->api->body();
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
            $this->api->respond_error('Username must be 3 to 100 characters and use only letters, numbers, dots, dashes, or underscores.', 422);
        }
        if (strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api->respond_error('A valid email address is required.', 422);
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            $this->api->respond_error('Password must be between 8 and 72 characters.', 422);
        }

        $this->require_database_configuration();
        $this->call->database();
        $existing = $this->db->raw(
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$username, $email]
        )->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $this->api->respond_error('Username or email is already registered.', 409);
        }

        $this->db->raw(
            'INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), 'user']
        );
        $user = $this->db->raw(
            'SELECT id, username, email, role FROM users WHERE username = ? LIMIT 1',
            [$username]
        )->fetch(PDO::FETCH_ASSOC);

        $tokens = $this->api->issue_tokens(['id' => $user['id'], 'role' => $user['role']]);
        $this->api->respond(['user' => $user, 'tokens' => $tokens], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit();
        $input = $this->api->body();
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        if ($username === '' || $password === '') {
            $this->api->respond_error('Username/email and password are required.', 400);
        }

        $this->require_database_configuration();
        $this->call->database();
        $user = $this->db->raw(
            'SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ? LIMIT 1',
            [$username, $username]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid username/email or password.', 401);
        }

        unset($user['password']);
        $tokens = $this->api->issue_tokens(['id' => $user['id'], 'role' => $user['role']]);
        $this->api->respond(['user' => $user, 'tokens' => $tokens]);
    }

    public function refresh()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit();
        $input = $this->api->body();
        if (empty($input['refresh_token'])) {
            $this->api->respond_error('Refresh token is required.', 400);
        }
        $this->require_database_configuration();
        $this->call->database();
        $this->api->refresh_access_token($input['refresh_token']);
    }

    public function logout()
    {
        $this->api->require_method('POST');
        $this->api->require_jwt();
        $input = $this->api->body();
        if (!empty($input['refresh_token'])) {
            $this->require_database_configuration();
            $this->call->database();
            $this->api->revoke_refresh_token($input['refresh_token']);
        }
        $this->api->respond(['message' => 'Logged out']);
    }
}