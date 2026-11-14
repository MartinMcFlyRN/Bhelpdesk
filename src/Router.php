<?php
declare(strict_types=1);

namespace Bhelpdesk;

final class Router
{
    /** @var array<int, array{string, string, callable}> */
    private array $routes = [];

    public function __construct(private string $prefix, private string $area)
    {
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['GET', $pattern, $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['POST', $pattern, $handler];
    }

    public function dispatch(): void
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_starts_with($path, $this->prefix)) {
            $path = substr($path, strlen($this->prefix));
        }
        $path = '/' . trim($path, '/');
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // body larger than post_max_size: PHP drops $_POST and $_FILES entirely
        if ($method === 'POST' && !$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_response_code(413);
            View::render($this->area, 'error', ['title' => __('error.too_large.title'), 'message' => __('error.too_large.message')]);
            return;
        }

        if ($method === 'POST' && !Csrf::valid()) {
            http_response_code(400);
            View::render($this->area, 'error', ['title' => __('error.session_expired.title'), 'message' => __('error.session_expired.message')]);
            return;
        }

        foreach ($this->routes as [$m, $pattern, $handler]) {
            if ($m !== $method) {
                continue;
            }
            $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($re, $path, $match)) {
                $params = array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler($params);
                return;
            }
        }

        http_response_code(404);
        View::render($this->area, 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
    }
}
