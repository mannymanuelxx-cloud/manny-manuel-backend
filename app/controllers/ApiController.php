<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
    }

    public function preflight()
    {
        http_response_code(204);
        exit;
    }
}