<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/health",
     *     operationId="getHealth",
     *     summary="Health check for uptime monitoring and deploy verification",
     *     description="Checks database and Redis. Returns the deployed git revision (written by CI) and the time of the last successful database backup. 503 when a dependency is down.",
     *     tags={"System"},
     *     @OA\Response(
     *         response=200,
     *         description="All dependencies healthy",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="revision", type="string", example="fd20e84c3d112454958e0a37d16bfa75decb5ec6"),
     *             @OA\Property(property="checks", type="object",
     *                 @OA\Property(property="database", type="string", example="ok"),
     *                 @OA\Property(property="redis", type="string", example="ok")
     *             ),
     *             @OA\Property(property="backup", type="object",
     *                 @OA\Property(property="enabled", type="boolean", example=true),
     *                 @OA\Property(property="last_success_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=503, description="A dependency is down (status=fail)")
     * )
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'redis' => $this->check(fn () => config('cache.default') === 'redis'
                ? Redis::connection(config('cache.stores.redis.connection', 'cache'))->ping()
                : Cache::get('health:ping')),
        ];

        $healthy = !in_array('fail', $checks, true);
        $revisionFile = base_path('REVISION');

        return response()->json([
            'status' => $healthy ? 'ok' : 'fail',
            'revision' => is_file($revisionFile) ? trim(file_get_contents($revisionFile)) : 'unknown',
            'checks' => $checks,
            'backup' => [
                'enabled' => (bool) config('backup.enabled'),
                'last_success_at' => $this->lastBackup(),
            ],
        ], $healthy ? 200 : 503);
    }

    private function lastBackup(): ?string
    {
        $file = config('backup.status_file');

        return is_file($file) ? trim(file_get_contents($file)) : null;
    }

    private function check(callable $probe): string
    {
        try {
            $probe();
            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }
}
