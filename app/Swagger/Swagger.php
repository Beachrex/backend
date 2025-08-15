<?php

namespace App\Swagger;

/**
 * @OA\Info(
 *     title="Beachrex API",
 *     version="1.0.0",
 *     description="
This is the API documentation for the Beachrex backend. Some of the endpoints use token-based authentication via Laravel Sanctum.

 **Authentication**

All protected endpoints require an `Authorization` header:

Authorization: Bearer YOUR_API_TOKEN

You can get your token via the `/api/v1/login` endpoint.
"
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Beachrex API Server"
 * )
 */
class Swagger {}
