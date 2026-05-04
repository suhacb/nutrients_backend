<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Nutrients API',
    version: '1.0.0',
    description: 'REST API for managing ingredients and their nutritional data.'
)]
#[OA\Server(
    url: 'http://localhost',
    description: 'API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'frontend',
    type: 'http',
    scheme: 'bearer',
    description: 'Bearer token obtained from POST /api/auth/login. Also requires X-Refresh-Token, X-Application-Name, and X-Client-Url headers on every request.'
)]
abstract class Controller
{
    //
}
