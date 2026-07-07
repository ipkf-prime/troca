<?php

namespace IPKF\Controllers;

use IPKF\Core\Request;
use IPKF\Core\Response;

class TestController
{
    public function index(Request $request, Response $response): void
    {
        echo "<h1>IPKF CORE IS RUNNING</h1>";
        echo "<p>Domain: " . $request->host() . "</p>";
        echo "<p>URI: " . $request->uri() . "</p>";
    }
}