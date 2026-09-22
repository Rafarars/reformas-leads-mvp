<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PurgeTestLeads extends Command
{
    protected $signature = 'e2e:purge-leads';

    protected $description = 'Purge E2E test leads (@e2e.test and source=demo) and their associated records and files';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Este comando no puede ejecutarse en el entorno de producción.');

            return self::FAILURE;
        }

        $leadIds = DB::table('leads')
            ->where('email', 'like', '%@e2e.test')
            ->orWhere('source', 'demo')
            ->pluck('id')
            ->all();

        $deletedOutbox = 0;
        $deletedLeads = 0;

        if (! empty($leadIds)) {
            DB::transaction(function () use ($leadIds, &$deletedOutbox, &$deletedLeads) {
                // outbox_messages referencia al lead en aggregate_id sin clave foránea.
                $deletedOutbox = DB::table('outbox_messages')
                    ->whereIn('aggregate_id', $leadIds)
                    ->delete();

                // Las cascadas en la base borran lead_history, lead_classifications y lead_attachments.
                $deletedLeads = DB::table('leads')
                    ->whereIn('id', $leadIds)
                    ->delete();
            });
        }

        $disk = Storage::disk((string) config('services.attachments.disk'));
        $deletedFiles = 0;

        foreach ($leadIds as $leadId) {
            $dir = "leads/{$leadId}";
            $files = $disk->allFiles($dir);
            $deletedFiles += count($files);
            $disk->deleteDirectory($dir);
        }

        $this->info(sprintf(
            'Purgados %d leads, %d mensajes de outbox y %d archivos adjuntos.',
            $deletedLeads,
            $deletedOutbox,
            $deletedFiles
        ));

        return self::SUCCESS;
    }
}
