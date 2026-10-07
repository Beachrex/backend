<?php

namespace App\Swagger\Schemas;

/**
 * @OA\Schema(
 *     schema="BeachWeatherResponse",
 *     title="Beach Weather Response",
 *     description="Response schema for beach weather endpoint",
 *     type="object",
 *     required={"success", "data"},
 *     @OA\Property(
 *         property="success",
 *         type="boolean",
 *         description="Whether the request was successful",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         description="Weather data for the beach",
 *         required={"beach_id", "beach_slug", "coordinates", "weather", "sea_temperature", "cached_at"},
 *         @OA\Property(
 *             property="beach_id",
 *             type="integer",
 *             description="Beach ID",
 *             example=1
 *         ),
 *         @OA\Property(
 *             property="beach_slug",
 *             type="string",
 *             description="Beach slug in current locale",
 *             example="plaza-livade-i-obatnice"
 *         ),
 *         @OA\Property(
 *             property="coordinates",
 *             type="object",
 *             description="Beach coordinates",
 *             required={"latitude", "longitude"},
 *             @OA\Property(
 *                 property="latitude",
 *                 type="string",
 *                 description="Beach latitude",
 *                 example="44.33880000"
 *             ),
 *             @OA\Property(
 *                 property="longitude",
 *                 type="string",
 *                 description="Beach longitude",
 *                 example="15.10510000"
 *             )
 *         ),
 *         @OA\Property(
 *             property="weather",
 *             type="object",
 *             nullable=true,
 *             description="Current weather data from OpenWeatherMap (cached for 1 hour)",
 *             required={"temperature", "feels_like", "temp_min", "temp_max", "pressure", "humidity", "condition", "description", "icon", "icon_url", "wind_speed", "wind_deg", "clouds", "visibility", "sunrise", "sunset", "location_name"},
 *             @OA\Property(
 *                 property="temperature",
 *                 type="number",
 *                 format="float",
 *                 description="Current temperature in Celsius",
 *                 example=9.9
 *             ),
 *             @OA\Property(
 *                 property="feels_like",
 *                 type="number",
 *                 format="float",
 *                 description="Feels like temperature in Celsius",
 *                 example=8.7
 *             ),
 *             @OA\Property(
 *                 property="temp_min",
 *                 type="number",
 *                 format="float",
 *                 description="Minimum temperature in Celsius",
 *                 example=9.9
 *             ),
 *             @OA\Property(
 *                 property="temp_max",
 *                 type="number",
 *                 format="float",
 *                 description="Maximum temperature in Celsius",
 *                 example=9.9
 *             ),
 *             @OA\Property(
 *                 property="pressure",
 *                 type="integer",
 *                 description="Atmospheric pressure in hPa",
 *                 example=1014
 *             ),
 *             @OA\Property(
 *                 property="humidity",
 *                 type="integer",
 *                 description="Humidity percentage",
 *                 example=58
 *             ),
 *             @OA\Property(
 *                 property="condition",
 *                 type="string",
 *                 description="Weather condition (Clear, Clouds, Rain, etc.)",
 *                 example="Clouds"
 *             ),
 *             @OA\Property(
 *                 property="description",
 *                 type="string",
 *                 description="Detailed weather description",
 *                 example="broken clouds"
 *             ),
 *             @OA\Property(
 *                 property="icon",
 *                 type="string",
 *                 description="Weather icon code",
 *                 example="04d"
 *             ),
 *             @OA\Property(
 *                 property="icon_url",
 *                 type="string",
 *                 description="Full URL to weather icon",
 *                 example="https://openweathermap.org/img/wn/04d@2x.png"
 *             ),
 *             @OA\Property(
 *                 property="wind_speed",
 *                 type="number",
 *                 format="float",
 *                 description="Wind speed in m/s",
 *                 example=2.52
 *             ),
 *             @OA\Property(
 *                 property="wind_deg",
 *                 type="integer",
 *                 description="Wind direction in degrees",
 *                 example=27
 *             ),
 *             @OA\Property(
 *                 property="clouds",
 *                 type="integer",
 *                 description="Cloudiness percentage",
 *                 example=60
 *             ),
 *             @OA\Property(
 *                 property="visibility",
 *                 type="integer",
 *                 description="Visibility in meters",
 *                 example=10000
 *             ),
 *             @OA\Property(
 *                 property="sunrise",
 *                 type="string",
 *                 description="Sunrise time (HH:mm)",
 *                 example="06:13"
 *             ),
 *             @OA\Property(
 *                 property="sunset",
 *                 type="string",
 *                 description="Sunset time (HH:mm)",
 *                 example="15:22"
 *             ),
 *             @OA\Property(
 *                 property="location_name",
 *                 type="string",
 *                 description="Location name from weather API",
 *                 example="Vir"
 *             )
 *         ),
 *         @OA\Property(
 *             property="sea_temperature",
 *             type="number",
 *             format="float",
 *             nullable=true,
 *             description="Sea surface temperature in Celsius from the Open-Meteo Marine API (cached 3 h). Null where there is no marine data (e.g. lakes) or the API is unavailable.",
 *             example=21.4
 *         ),
 *         @OA\Property(
 *             property="cached_at",
 *             type="string",
 *             format="date-time",
 *             description="Timestamp when the weather data was cached (ISO 8601 format)",
 *             example="2025-11-29T10:20:47+00:00"
 *         )
 *     )
 * )
 */
class BeachWeatherResponseSchema
{
    // This class is used only for Swagger documentation
}

