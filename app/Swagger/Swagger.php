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
 *     url="https://dev-api.beachrex.com",
 *     description="Development Server"
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
 *         enum={"en", "si", "de", "hr", "fr", "es", "it", "ua", "ru", "pl", "cs", "hu", "bg", "sk"},
 *         default="en",
 *         example="en"
 *     )
 * )
 *
 * @OA\Response(
 *     response="CacheHeaders",
 *     description="Cache-related response headers",
 *     @OA\Header(
 *         header="X-Cache-Status",
 *         description="Cache status: HIT (from cache), MISS (from database), or BYPASS (not cached)",
 *         @OA\Schema(type="string", enum={"HIT", "MISS", "BYPASS"}, example="HIT")
 *     ),
 *     @OA\Header(
 *         header="X-Cache-Key",
 *         description="The cache key used for this request (locale-aware)",
 *         @OA\Schema(type="string", example="waterbodies:index:en:all")
 *     ),
 *     @OA\Header(
 *         header="X-Response-Time",
 *         description="Response time in milliseconds",
 *         @OA\Schema(type="string", example="11.35ms")
 *     ),
 *     @OA\Header(
 *         header="X-Locale",
 *         description="The locale used for this response",
 *         @OA\Schema(type="string", example="en")
 *     )
 * )
 */
class Swagger {}
