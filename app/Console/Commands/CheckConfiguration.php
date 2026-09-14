<?php

namespace App\Console\Commands;

use App\Support\ProductionConfiguration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-config')]
#[Description('Verifica la configuración antes de arrancar; en producción falla si algo está mal')]
class CheckConfiguration extends Command
{
    public function handle(ProductionConfiguration $configuration): int
    {
        $problems = $configuration->problems();

        if ($problems === []) {
            $this->info('Configuración válida.');

            return self::SUCCESS;
        }

        $this->error('Configuración inválida para producción:');

        foreach ($problems as $problem) {
            $this->line("  - {$problem}");
        }

        return self::FAILURE;
    }
}
