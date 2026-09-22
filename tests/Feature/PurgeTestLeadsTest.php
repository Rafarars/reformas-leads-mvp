<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('purges e2e and demo leads with their dependents and files while preserving other leads', function () {
    $diskName = (string) config('services.attachments.disk');
    Storage::fake($diskName);
    $disk = Storage::disk($diskName);

    $e2eLeadId = (string) Str::uuid();
    $demoLeadId = (string) Str::uuid();
    $realLeadId = (string) Str::uuid();
    $now = now();

    // 1. Lead de prueba E2E (@e2e.test)
    DB::table('leads')->insert([
        'id' => $e2eLeadId,
        'name' => 'Lucía E2E',
        'email' => 'lucia.123@e2e.test',
        'phone' => '600000001',
        'renovation_type' => 'bathroom',
        'source' => 'web',
        'status' => 'new',
        'contact_count' => 1,
        'received_at' => $now,
        'last_contact_at' => $now,
    ]);
    DB::table('outbox_messages')->insert([
        'id' => (string) Str::uuid(),
        'event_name' => 'lead.received',
        'aggregate_id' => $e2eLeadId,
        'payload' => json_encode(['id' => $e2eLeadId]),
        'destination' => 'crm',
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => $now,
    ]);
    DB::table('lead_classifications')->insert([
        'lead_id' => $e2eLeadId,
        'status' => 'needs_review',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('lead_attachments')->insert([
        'id' => (string) Str::uuid(),
        'lead_id' => $e2eLeadId,
        'kind' => 'photo',
        'file_name' => 'bano.png',
        'content_type' => 'image/png',
        'size_bytes' => 1024,
        'path' => "leads/{$e2eLeadId}/foto.png",
        'uploaded_at' => $now,
    ]);
    $disk->put("leads/{$e2eLeadId}/foto.png", 'fake-content');

    // 2. Lead creado desde demo de resiliencia (source = demo)
    DB::table('leads')->insert([
        'id' => $demoLeadId,
        'name' => 'Demo Lead',
        'email' => 'demo.456@example.com',
        'phone' => '600000002',
        'renovation_type' => 'kitchen',
        'source' => 'demo',
        'status' => 'new',
        'contact_count' => 1,
        'received_at' => $now,
        'last_contact_at' => $now,
    ]);
    DB::table('outbox_messages')->insert([
        'id' => (string) Str::uuid(),
        'event_name' => 'lead.received',
        'aggregate_id' => $demoLeadId,
        'payload' => json_encode(['id' => $demoLeadId]),
        'destination' => 'crm',
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => $now,
    ]);

    // 3. Lead real que NO debe borrarse
    DB::table('leads')->insert([
        'id' => $realLeadId,
        'name' => 'Cliente Real',
        'email' => 'cliente.real@gmail.com',
        'phone' => '600000003',
        'renovation_type' => 'complete',
        'source' => 'web',
        'status' => 'contacted',
        'contact_count' => 1,
        'received_at' => $now,
        'last_contact_at' => $now,
    ]);
    DB::table('outbox_messages')->insert([
        'id' => (string) Str::uuid(),
        'event_name' => 'lead.received',
        'aggregate_id' => $realLeadId,
        'payload' => json_encode(['id' => $realLeadId]),
        'destination' => 'crm',
        'status' => 'delivered',
        'attempts' => 1,
        'created_at' => $now,
    ]);
    DB::table('lead_classifications')->insert([
        'lead_id' => $realLeadId,
        'status' => 'classified',
        'category' => 'kitchen',
        'urgency' => 'high',
        'confidence' => 0.95,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('lead_attachments')->insert([
        'id' => (string) Str::uuid(),
        'lead_id' => $realLeadId,
        'kind' => 'photo',
        'file_name' => 'plano.png',
        'content_type' => 'image/png',
        'size_bytes' => 2048,
        'path' => "leads/{$realLeadId}/plano.png",
        'uploaded_at' => $now,
    ]);
    $disk->put("leads/{$realLeadId}/plano.png", 'real-content');

    // Ejecutar purga
    $this->artisan('e2e:purge-leads')
        ->expectsOutputToContain('Purgados 2 leads, 2 mensajes de outbox y 1 archivos adjuntos.')
        ->assertSuccessful();

    // Comprobar que e2e y demo se borraron completamente
    expect(DB::table('leads')->whereIn('id', [$e2eLeadId, $demoLeadId])->count())->toBe(0)
        ->and(DB::table('outbox_messages')->whereIn('aggregate_id', [$e2eLeadId, $demoLeadId])->count())->toBe(0)
        ->and(DB::table('lead_classifications')->where('lead_id', $e2eLeadId)->count())->toBe(0)
        ->and(DB::table('lead_attachments')->where('lead_id', $e2eLeadId)->count())->toBe(0)
        ->and($disk->exists("leads/{$e2eLeadId}/foto.png"))->toBeFalse();

    // Comprobar que el lead real sigue intacto con todas sus filas y su archivo
    expect(DB::table('leads')->where('id', $realLeadId)->count())->toBe(1)
        ->and(DB::table('outbox_messages')->where('aggregate_id', $realLeadId)->count())->toBe(1)
        ->and(DB::table('lead_classifications')->where('lead_id', $realLeadId)->count())->toBe(1)
        ->and(DB::table('lead_attachments')->where('lead_id', $realLeadId)->count())->toBe(1)
        ->and($disk->exists("leads/{$realLeadId}/plano.png"))->toBeTrue();
});

it('refuses to run in production environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('e2e:purge-leads')
        ->expectsOutputToContain('Este comando no puede ejecutarse en el entorno de producción.')
        ->assertFailed();
});
