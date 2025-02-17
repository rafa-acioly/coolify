<?php

namespace App\Actions\Server;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\Server;
use App\Models\StandaloneDocker;

class InstallDocker
{
    use AsAction;
    public function handle(Server $server)
    {
        $supported_os_type = $server->validateOS();
        if (!$supported_os_type) {
            throw new \Exception('Server OS type is not supported for automated installation. Please install Docker manually before continuing: <a target="_blank" class="underline" href="https://coolify.io/docs/servers#install-docker-engine-manually">documentation</a>.');
        }
        ray('Installing Docker on server: ' . $server->name . ' (' . $server->ip . ')' . ' with OS type: ' . $supported_os_type);
        $dockerVersion = '24.0';
        $config = base64_encode('{
            "log-driver": "json-file",
            "log-opts": {
              "max-size": "10m",
              "max-file": "3"
            }
          }');
        $found = StandaloneDocker::where('server_id', $server->id);
        if ($found->count() == 0 && $server->id) {
            StandaloneDocker::create([
                'name' => 'coolify',
                'network' => 'coolify',
                'server_id' => $server->id,
            ]);
        }
        $command = collect([]);
        if (isDev() && $server->id === 0) {
            $command = $command->merge([
                "echo 'Installing Prerequisites...'",
                "sleep 1",
                "echo 'Installing Docker Engine...'",
                "echo 'Configuring Docker Engine (merging existing configuration with the required)...'",
                "sleep 4",
                "echo 'Restarting Docker Engine...'",
                "ls -l /tmp"
            ]);
        } else {
            if ($supported_os_type->contains('debian')) {
                $command = $command->merge([
                    "echo 'Installing Prerequisites...'",
                    "command -v jq >/dev/null || apt-get update -y",
                    "command -v jq >/dev/null || apt install -y curl wget git jq",

                ]);
            } else if ($supported_os_type->contains('rhel')) {
                $command = $command->merge([
                    "echo 'Installing Prerequisites...'",
                    "command -v jq >/dev/null || dnf install -y curl wget git jq",
                ]);
            } else if ($supported_os_type->contains('sles')) {
                $command = $command->merge([
                    "echo 'Installing Prerequisites...'",
                    "command -v jq >/dev/null || zypper update -y",
                    "command -v jq >/dev/null || zypper install -y curl wget git jq",
                ]);
            } else {
                throw new \Exception('Unsupported OS');
            }
            $command = $command->merge([
                "echo 'Installing Docker Engine...'",
                "curl https://releases.rancher.com/install-docker/{$dockerVersion}.sh | sh",
                "echo 'Configuring Docker Engine (merging existing configuration with the required)...'",
                "test -s /etc/docker/daemon.json && cp /etc/docker/daemon.json \"/etc/docker/daemon.json.original-`date +\"%Y%m%d-%H%M%S\"`\" || echo '{$config}' | base64 -d > /etc/docker/daemon.json",
                "echo '{$config}' | base64 -d > /etc/docker/daemon.json.coolify",
                "cat <<< $(jq . /etc/docker/daemon.json.coolify) > /etc/docker/daemon.json.coolify",
                "cat <<< $(jq -s '.[0] * .[1]' /etc/docker/daemon.json /etc/docker/daemon.json.coolify) > /etc/docker/daemon.json",
                "echo 'Restarting Docker Engine...'",
                "systemctl enable docker >/dev/null 2>&1 || true",
                "systemctl restart docker",
            ]);
            if ($server->isSwarm()) {
                $command = $command->merge([
                    "docker network create --attachable --driver overlay coolify-overlay >/dev/null 2>&1 || true",
                ]);
            } else {
                $command = $command->merge([
                    "docker network create --attachable coolify >/dev/null 2>&1 || true",
                ]);
                $command = $command->merge([
                    "echo 'Done!'",
                ]);
            }

            return remote_process($command, $server);
        }
    }
}
