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
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your bearer token in the format: Bearer {token}"
 * )
 *
 * @OA\Parameter(
 *     parameter="AcceptLanguage",
 *     name="Accept-Language",
 *     in="header",
 *     required=false,
 *     description="Language preference for the response (ISO 639-1 language codes)",
 *     @OA\Schema(
 *         type="string",
 *         enum={"en", "si", "hr", "de", "it", "fr"},
 *         default="en",
 *         example="en"
 *     )
 * )
 */
class Swagger {}
