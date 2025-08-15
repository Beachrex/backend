<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachSchema",
 *     title="Beach Schema",
 *     description="Beach API Resource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=5),
 *     @OA\Property(property="status", type="string", example="Active"),
 *     @OA\Property(property="name", type="string", example="Plaža Dječji raj"),
 *     @OA\Property(property="description", type="string", example="Family-friendly beach"),
 *     @OA\Property(property="latitude", type="number", format="float", example=45.1234567),
 *     @OA\Property(property="longitude", type="number", format="float", example=14.9876543),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class BeachSchema {}
