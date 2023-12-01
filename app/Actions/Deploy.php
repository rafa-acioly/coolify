<?php

namespace App\Actions;

use App\Actions\Service\StartService;
use App\Http\Requests\DeployRequest;
use App\Models\Application;
use App\Models\BaseModel;
use App\Models\Service;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Visus\Cuid2\Cuid2;

class Deploy
{
    use AsAction;

    public function asController(DeployRequest $request): JsonResponse
    {
        $token = auth()->user()->currentAccessToken();
        $force = $request->query->get('force') ?? false;

        $teamId = data_get($token, 'team_id');
        abort_unless(is_null($teamId), 400, ['error' => 'Invalid token.']);

        $uuid = $request->query->get('uuid');
        $resource = getResourceByUuid($uuid, $teamId);
        if (!$resource) {
            return response()->json(['error' => "No resource found with $uuid."], 400);
        }

        return match ($resource->getMorphClass()) {
            Service::class => $this->deployService($resource),
            Application::class => $this->deployApplication($resource, $force),

            StandaloneMysql::class,
            StandaloneMariadb::class,
            StandaloneMongodb::class,
            StandalonePostgresql::class,=> $this->deployDatabase($resource),

            default => response()->json(['error' => "Unknown resource type $uuid."], 400),
        };
    }

    private function deployDatabase(BaseModel $resource): JsonResponse
    {
        $resource->start();
        $resource->update(['started_at' => now()]);
        return response()->json(['message' => 'Database started.']);
    }

    private function deployApplication(BaseModel $resource, bool $force = false): JsonResponse
    {
        queue_application_deployment(
            application_id: $resource->id,
            deployment_uuid: new Cuid2(7),
            force_rebuild: $force,
        );

        return response()->json(['message' => 'Deployment queued.']);
    }

    private function deployService(BaseModel $resource): JsonResponse
    {
        StartService::run($resource);
        return response()->json(['message' => 'Service started. It could take a while, be patient.']);
    }
}
