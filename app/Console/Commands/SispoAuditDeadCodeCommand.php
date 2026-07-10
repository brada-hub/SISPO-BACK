<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SispoAuditDeadCodeCommand extends Command
{
    protected $signature = 'sispo:audit-dead-code';
    protected $description = 'Find unused classes, legacy AI modules, and obsolete components.';

    public function handle()
    {
        $this->info("========================================================================");
        $this->info("             AUDITORÍA DE DEUDA TÉCNICA Y DEAD CODE                     ");
        $this->info("========================================================================");

        $suspects = [
            'AiController.php' => 'app/Http/Controllers/AiController.php',
            'AiAnalysisService.php' => 'app/Services/Ai/AiAnalysisService.php',
            'GeminiClient.php' => 'app/Services/Ai/GeminiClient.php',
            'MatchingEngine.php' => 'app/Services/Ai/MatchingEngine.php',
            'RankingService.php' => 'app/Services/Ai/RankingService.php',
            'AnalyzeCvJob.php' => 'app/Jobs/AnalyzeCvJob.php',
            'BatchAnalyzeJob.php' => 'app/Jobs/BatchAnalyzeJob.php',
        ];

        $results = [];

        foreach ($suspects as $name => $path) {
            $baseName = str_replace('.php', '', $name);
            $process = new Process(['rg', '-l', $baseName, base_path('app'), base_path('routes')]);
            $process->run();

            $files = array_filter(explode("\n", $process->getOutput()));
            // remove self from list
            $files = array_filter($files, function($f) use ($path) {
                return !str_ends_with(str_replace('\\', '/', $f), $path);
            });

            if (count($files) === 0) {
                $results[] = [$baseName, '<fg=red>NO USADO</>', 'Candidato a Eliminación'];
            } else {
                $results[] = [$baseName, '<fg=green>USADO</>', 'Usado en ' . count($files) . ' archivos'];
            }
        }

        $this->table(['Clase / Módulo', 'Estado', 'Detalle'], $results);

        $this->info("\nPara frontend, recomendamos usar 'npx depcheck' o 'npx unimported'.");
        return Command::SUCCESS;
    }
}
