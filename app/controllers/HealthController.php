<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class HealthController extends Controller
{
    public function index()
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['status' => 'ok']);
    }

    public function api()
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'status' => 'ok',
            'message' => 'Product Management API is running',
        ]);
    }
}