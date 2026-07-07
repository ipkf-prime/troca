<?php

namespace App\Http\Controllers;

use IPKF\Http\Request;
use IPKF\Http\Response;

class Controller
{
    protected Request $request;
    protected Response $response;

    public function __construct(Request $request, Response $response)
    {
        $this->request  = $request;
        $this->response = $response;
    }
}