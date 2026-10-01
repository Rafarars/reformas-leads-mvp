<?php

it('refuses to start when FAKE_CRM_TOKEN is not defined', function () {
    $pipes = [];
    $env = $_ENV;
    unset($env['FAKE_CRM_TOKEN']);

    $process = proc_open(
        ['node', base_path('docker/fake-crm/server.mjs')],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        base_path(),
        $env,
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Failed to start fake-crm process');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    expect($exitCode)->toBe(1);
    expect($stderr)->toContain('FAKE_CRM_TOKEN');
});

it('serves health without authentication and protects control and deliveries with bearer token', function () {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if (! is_resource($socket)) {
        throw new RuntimeException("Failed to allocate socket: $errstr");
    }

    $name = stream_socket_get_name($socket, false);
    if ($name === false) {
        fclose($socket);
        throw new RuntimeException('Failed to resolve socket name');
    }

    $colonPos = strrchr($name, ':');
    if ($colonPos === false) {
        fclose($socket);
        throw new RuntimeException("Failed to parse port from socket name: $name");
    }

    $port = (int) substr($colonPos, 1);
    fclose($socket);

    $token = 'test-fake-crm-token-1234';
    $pipes = [];

    $process = proc_open(
        ['node', base_path('docker/fake-crm/server.mjs')],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        base_path(),
        array_merge($_ENV, [
            'PORT' => (string) $port,
            'FAKE_CRM_TOKEN' => $token,
        ]),
    );

    if (! is_resource($process)) {
        throw new RuntimeException('Failed to start fake-crm process');
    }

    $request = function (string $method, string $path, ?string $authToken = null, ?array $body = null) use ($port): array {
        $headers = [
            'Accept: application/json',
        ];
        if ($authToken !== null) {
            $headers[] = 'Authorization: Bearer '.$authToken;
        }

        $content = null;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $content = json_encode($body, JSON_THROW_ON_ERROR);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'ignore_errors' => true,
                'timeout' => 2.0,
            ],
        ]);

        $raw = @file_get_contents("http://127.0.0.1:{$port}{$path}", false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('{HTTP/\S*\s+(\d{3})}', $statusLine, $match);
        $statusCode = isset($match[1]) ? (int) $match[1] : 0;
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

        return ['status' => $statusCode, 'data' => $data, 'raw' => $raw];
    };

    try {
        $ready = false;
        for ($i = 0; $i < 40; $i++) {
            $check = $request('GET', '/health');
            if ($check['status'] === 200) {
                $ready = true;
                break;
            }
            usleep(50000);
        }

        expect($ready)->toBeTrue('fake-crm server did not start in time');

        // /health es pública y no requiere token
        $health = $request('GET', '/health');
        expect($health['status'])->toBe(200);
        expect($health['data'])->toBe(['status' => 'ok']);

        // /control requiere token
        $unauthorizedControl = $request('GET', '/control');
        expect($unauthorizedControl['status'])->toBe(401);
        expect($unauthorizedControl['data'])->toBe(['error' => 'unauthorized']);

        $invalidTokenControl = $request('GET', '/control', 'wrong-token');
        expect($invalidTokenControl['status'])->toBe(401);

        $authorizedControl = $request('GET', '/control', $token);
        expect($authorizedControl['status'])->toBe(200);
        expect($authorizedControl['data'])->toHaveKeys(['outage', 'rejected', 'duplicates', 'deliveries']);
        expect($authorizedControl['data']['outage'])->toBeFalse();

        // /deliveries requiere token
        $unauthorizedDeliveries = $request('GET', '/deliveries');
        expect($unauthorizedDeliveries['status'])->toBe(401);
        expect($unauthorizedDeliveries['data'])->toBe(['error' => 'unauthorized']);

        $invalidDeliveries = $request('GET', '/deliveries', 'wrong-token');
        expect($invalidDeliveries['status'])->toBe(401);

        $authorizedDeliveries = $request('GET', '/deliveries', $token);
        expect($authorizedDeliveries['status'])->toBe(200);
        expect($authorizedDeliveries['data'])->toBe(['deliveries' => []]);

        // POST /control/outage requiere token y cambia el estado
        $outageChange = $request('POST', '/control/outage', $token, ['enabled' => true]);
        expect($outageChange['status'])->toBe(200);
        expect($outageChange['data']['outage'])->toBeTrue();

        // Con caída activa, POST /webhooks/leads responde 503 sin necesidad de token
        $rejectedLead = $request('POST', '/webhooks/leads', null, ['event' => 'lead.received']);
        expect($rejectedLead['status'])->toBe(503);
        expect($rejectedLead['data'])->toBe(['error' => 'service_unavailable']);

        // Reset deja el simulador limpio
        $reset = $request('POST', '/control/reset', $token);
        expect($reset['status'])->toBe(200);
        expect($reset['data']['outage'])->toBeFalse();

        // Con caída inactiva, POST /webhooks/leads acepta el lead
        $acceptedLead = $request('POST', '/webhooks/leads', null, [
            'event' => 'lead.received',
            'aggregate_id' => 'lead-123',
            'data' => ['name' => 'Carlos'],
        ]);
        expect($acceptedLead['status'])->toBe(202);
        expect($acceptedLead['data'])->toBe(['received' => true, 'duplicate' => false]);

        // Y /deliveries con token ahora muestra la entrega
        $deliveriesAfter = $request('GET', '/deliveries', $token);
        expect($deliveriesAfter['status'])->toBe(200);
        expect($deliveriesAfter['data']['deliveries'])->toHaveCount(1);
        expect($deliveriesAfter['data']['deliveries'][0]['aggregate_id'])->toBe('lead-123');
    } finally {
        foreach ($pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        proc_terminate($process);
        proc_close($process);
    }
});
