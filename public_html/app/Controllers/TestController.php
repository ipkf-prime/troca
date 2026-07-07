<?php

namespace App\Controllers;

use IPKF\Http\Request;
use IPKF\Http\Response;

class TestController
{
    public function index(Request $request, Response $response): Response
    {
        return $response->send(
            "<h1>IPKF CORE IS RUNNING</h1>" .
            "<p>Domain: " . htmlspecialchars($request->host(), ENT_QUOTES, 'UTF-8') . "</p>" .
            "<p>URI: " . htmlspecialchars($request->uri(), ENT_QUOTES, 'UTF-8') . "</p>"
        );
    }
}
