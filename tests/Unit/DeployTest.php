<?php

use App\Actions\Database\StartMariadb;
use App\Actions\Database\StartMongodb;
use App\Actions\Database\StartMysql;
use App\Actions\Database\StartPostgresql;
use App\Actions\Service\StartService;
use App\Models\Application;
use App\Models\Service;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('should start database resource', function ($action, $resource) {
    Mockery::mock($action)
        ->shouldReceive('start')
        ->once();

    $this->get("/api/v1/deploy?uuid={$resource->uuid}")
        ->assertOk()
        ->assertJson(['message' => 'Database started.']);
})->with([
    [StartMysql::class, StandaloneMysql::factory()->create()],
    [StartMariadb::class, StandaloneMariadb::factory()->create()],
    [StartMongodb::class, StandaloneMongodb::factory()->create()],
    [StartPostgresql::class, StandalonePostgresql::factory()->create()],
]);

it('should start service resource', function () {
    Mockery::mock(StartService::class)
        ->shouldReceive('run')
        ->once();

    $service = Service::factory()->create();

    $this->get("/api/v1/deploy?uuid={$service->uuid}")
        ->assertOk()
        ->assertJson(['message' => 'Service started. It could take a while, be patient.']);
});

it('should start application resource')
    ->skip('How to mock queue_application_deployment?');
