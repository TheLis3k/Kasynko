<?php

namespace App\Controllers;

class ErrorController extends BaseController
{
    public function notFound(): void
    {
        http_response_code(404);
        $this->view('errors/404');
    }

    public function forbidden(): void
    {
        http_response_code(403);
        $this->view('errors/403');
    }

    public function serverError(): void
    {
        http_response_code(500);
        $this->view('errors/500');
    }
}
