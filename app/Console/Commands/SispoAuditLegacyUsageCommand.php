<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SispoAuditLegacyUsageCommand extends Command
{
    protected $signature = 'sispo:audit-legacy-usage';
    protected $description = 'Audits the codebase for remaining usages of legacy meritos structures.';

    public function handle()
    {
        $this->info("===============================================================");
        $this->info("         AUDITORÍA DE CONSUMO LEGACY MERITOS                   ");
        $this->info("===============================================================");

        $searchTerms = [
            'PostulanteMerito',
            'MeritoArchivo',
            'TipoDocumento',
            'source_merito_id',
            'meritos',
            '\.meritos',
            'postulante\.meritos',
            'meritos\.value',
        ];

        $paths = [
            base_path('app'),
            base_path('routes'),
            base_path('config'),
            base_path('../sispo-front/src'),
        ];

        $results = [];

        foreach ($paths as $path) {
            if (!file_exists($path)) {
                continue;
            }
            foreach ($searchTerms as $term) {
                $process = new Process(['rg', '-n', $term, $path]);
                $process->run();
                if ($process->isSuccessful()) {
                    $output = array_filter(explode("\n", $process->getOutput()));
                    foreach ($output as $line) {
                        $parts = explode(':', $line, 3);
                        if (count($parts) >= 3) {
                            $file = $parts[0];
                            $lineNum = $parts[1];
                            $content = trim($parts[2]);

                            // Classification heuristic
                            $type = 'lectura_desconocida';
                            if (str_contains(strtolower($content), 'create') || str_contains(strtolower($content), 'insert') || str_contains(strtolower($content), 'save') || str_contains(strtolower($content), 'update')) {
                                $type = 'escritura';
                            } elseif (str_contains($content, 'hasMany') || str_contains($content, 'belongsTo') || str_contains($content, 'with(')) {
                                $type = 'relacion';
                            } elseif (str_starts_with($content, '//') || str_starts_with($content, '*')) {
                                $type = 'comentario';
                            } elseif (str_contains(strtolower($content), 'fallback') || str_contains(strtolower($content), 'legacy')) {
                                $type = 'fallback';
                            }

                            $results[] = [
                                'file' => str_replace(base_path() . '/', '', $file),
                                'line' => $lineNum,
                                'term' => $term,
                                'type' => $type,
                                'content' => $content,
                            ];
                        }
                    }
                }
            }
        }

        if (empty($results)) {
            $this->info("No legacy usages found! Codebase is perfectly clean.");
            return Command::SUCCESS;
        }

        $this->table(
            ['File', 'Line', 'Term', 'Type', 'Content Snapshot'],
            array_map(function ($r) {
                return [
                    substr($r['file'], -50),
                    $r['line'],
                    $r['term'],
                    $r['type'],
                    substr($r['content'], 0, 50) . (strlen($r['content']) > 50 ? '...' : ''),
                ];
            }, $results)
        );

        $this->warn("Found " . count($results) . " potential legacy references.");
        return Command::SUCCESS;
    }
}
