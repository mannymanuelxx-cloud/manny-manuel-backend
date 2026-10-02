<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductsController extends Controller
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

    public function index()
    {
        $user = $this->api->require_jwt();
        $this->api->require_method('GET');
        $this->api->rate_limit('user_' . $user['sub']);
        $this->require_database_configuration();
        $this->call->database();
        $products = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products ORDER BY id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->api->respond($products);
    }

    public function create()
    {
        $user = $this->api->require_jwt();
        $this->api->require_method('POST');
        $this->api->rate_limit('user_' . $user['sub']);
        $product = $this->validated_product($this->api->body());

        $this->require_database_configuration();
        $this->call->database();
        $this->db->raw(
            'INSERT INTO products (product_name, description, price, quantity) VALUES (?, ?, ?, ?)',
            [$product['product_name'], $product['description'], $product['price'], $product['quantity']]
        );
        $created = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = LAST_INSERT_ID()'
        )->fetch(PDO::FETCH_ASSOC);
        $this->api->respond($created, 201);
    }

    public function update($id)
    {
        $user = $this->api->require_jwt();
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
            $this->api->respond_error('Method Not Allowed', 405);
        }
        $this->api->rate_limit('user_' . $user['sub']);
        $this->require_database_configuration();
        $this->call->database();

        $existing = $this->find_product($id);
        if (!$existing) {
            $this->api->respond_error('Product not found.', 404);
        }

        $input = $this->api->body();
        $allowed = ['product_name', 'description', 'price', 'quantity'];
        $data = array_intersect_key($input, array_flip($allowed));
        if (!$data) {
            $this->api->respond_error('At least one product field is required.', 422);
        }
        $product = $this->validated_product(array_merge($existing, $data), true, array_keys($data));
        $sets = [];
        $values = [];
        foreach (array_keys($data) as $field) {
            $sets[] = $field . ' = ?';
            $values[] = $product[$field];
        }
        $values[] = (int) $id;
        $this->db->raw('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?', $values);
        $this->api->respond($this->find_product($id));
    }

    public function delete($id)
    {
        $user = $this->api->require_jwt();
        $this->api->require_method('DELETE');
        $this->api->rate_limit('user_' . $user['sub']);
        $this->require_database_configuration();
        $this->call->database();
        if (!$this->find_product($id)) {
            $this->api->respond_error('Product not found.', 404);
        }
        $this->db->raw('DELETE FROM products WHERE id = ?', [(int) $id]);
        http_response_code(204);
        exit;
    }

    private function find_product($id)
    {
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->api->respond_error('Invalid product ID.', 422);
        }
        return $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1',
            [(int) $id]
        )->fetch(PDO::FETCH_ASSOC);
    }

    private function validated_product(array $input, $partial = false, array $fields = [])
    {
        $fields = $fields ?: ['product_name', 'description', 'price', 'quantity'];
        $product = [];

        if (in_array('product_name', $fields, true)) {
            $name = trim((string) ($input['product_name'] ?? ''));
            if ($name === '' || strlen($name) > 100) {
                $this->api->respond_error('Product name is required and must be at most 100 characters.', 422);
            }
            $product['product_name'] = $name;
        } elseif ($partial) {
            $product['product_name'] = $input['product_name'];
        }

        if (in_array('description', $fields, true)) {
            $description = (string) ($input['description'] ?? '');
            if (strlen($description) > 10000) {
                $this->api->respond_error('Description must be at most 10,000 characters.', 422);
            }
            $product['description'] = $description;
        } elseif ($partial) {
            $product['description'] = $input['description'];
        }

        if (in_array('price', $fields, true)) {
            $price = (string) ($input['price'] ?? '');
            if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $price)) {
                $this->api->respond_error('Price must be a non-negative amount with up to two decimal places.', 422);
            }
            $product['price'] = $price;
        } elseif ($partial) {
            $product['price'] = $input['price'];
        }

        if (in_array('quantity', $fields, true)) {
            $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($quantity === false) {
                $this->api->respond_error('Quantity must be a non-negative whole number.', 422);
            }
            $product['quantity'] = $quantity;
        } elseif ($partial) {
            $product['quantity'] = $input['quantity'];
        }

        return $product;
    }
}