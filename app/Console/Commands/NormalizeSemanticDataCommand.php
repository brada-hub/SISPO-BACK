<?php

namespace App\Console\Commands;

use App\Jobs\NormalizeAcademicRecordsJob;
use App\Jobs\NormalizePostgraduateRecordsJob;
use App\Jobs\NormalizeProfessionalExperienceJob;
use App\Models\FormacionAcademica;
use App\Models\ExperienciaDocencia;
use App\Models\FormacionPostgrado;
use App\Models\ExperienciaProfesional;
use App\Models\CatalogAcademicLevel;
use App\Models\CatalogCareer;
use App\Models\CatalogJobPosition;
use App\Models\CatalogPostgraduateType;
use App\Services\Normalization\AcademicLevelNormalizer;
use App\Services\Normalization\CareerNormalizer;
use App\Services\Normalization\JobPositionNormalizer;
use App\Services\Normalization\PostgraduateTypeNormalizer;
use Illuminate\Console\Command;

class NormalizeSemanticDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sispo:normalize-semantic
                            {--dry-run : Simulate the semantic normalization process without saving}
                            {--write : Write the normalized foreign keys and confidence scores to DB}
                            {--only= : Filter execution. Options: academic-records, professional-experience, postgraduate-records}
                            {--limit= : Limit the number of records to process}
                            {--force-ai-fallback : Force LLM AI fallback on low confidence (for future expansion)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform semantic normalization on raw fields inside SQL normalized tables using catalogs and aliases';

    /**
     * Execute the console command.
     */
    public function handle(
        AcademicLevelNormalizer $levelNormalizer,
        CareerNormalizer $careerNormalizer,
        JobPositionNormalizer $positionNormalizer,
        PostgraduateTypeNormalizer $postgradNormalizer
    ): int {
        $write = $this->option('write');
        $dryRun = $this->option('dry-run');
        $only = $this->option('only');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $forceAi = $this->option('force-ai-fallback');

        if (!$write && !$dryRun) {
            $this->error('Debes especificar --write o --dry-run para ejecutar la normalización semántica.');
            $this->info('Ejemplo: php artisan sispo:normalize-semantic --dry-run');
            return 1;
        }

        $this->info('============================================================');
        $this->info('      INICIANDO NORMALIZACIÓN SEMÁNTICA EN SISPO IA         ');
        $this->info('============================================================');
        $this->info('Modo de ejecución: ' . ($write ? '<fg=green;options=bold>ESCRITURA REAL</>' : '<fg=yellow;options=bold>SIMULACIÓN (DRY-RUN)</>'));
        if ($only) {
            $this->info('Filtro activado: ' . $only);
        }
        if ($limit) {
            $this->info('Límite de registros: ' . $limit);
        }
        if ($forceAi) {
            $this->info('Fallback de IA: Activado');
        }
        $this->info('------------------------------------------------------------');

        // Stats tracking
        $results = [];

        // 1. Process Academic Records (Formaciones Académicas and Docencia)
        if (!$only || $only === 'academic-records') {
            $this->comment('Procesando Registros Académicos...');
            
            $academicQuery = FormacionAcademica::query();
            if ($limit) $academicQuery->limit($limit);
            $academicIds = $academicQuery->pluck('id')->toArray();

            $docenciaQuery = ExperienciaDocencia::query();
            if ($limit) $docenciaQuery->limit($limit);
            $docenciaIds = $docenciaQuery->pluck('id')->toArray();

            $job = new NormalizeAcademicRecordsJob($academicIds, $docenciaIds, $write);
            $stats = $job->handle($levelNormalizer, $careerNormalizer);

            $results[] = [
                'Sección / Tabla', 
                'Total Registros', 
                'Normalizados Determinísticamente', 
                'Ratio de Match'
            ];
            
            $results[] = [
                'Formación Académica', 
                $stats['academic_total'], 
                $stats['academic_normalized'], 
                $stats['academic_total'] > 0 ? round(($stats['academic_normalized'] / $stats['academic_total']) * 100, 2) . '%' : '0%'
            ];
            
            $results[] = [
                'Docencia Universitaria', 
                $stats['docencia_total'], 
                $stats['docencia_normalized'], 
                $stats['docencia_total'] > 0 ? round(($stats['docencia_normalized'] / $stats['docencia_total']) * 100, 2) . '%' : '0%'
            ];
        }

        // 2. Process Professional Experience
        if (!$only || $only === 'professional-experience') {
            $this->comment('Procesando Experiencias Profesionales...');

            $profQuery = ExperienciaProfesional::query();
            if ($limit) $profQuery->limit($limit);
            $profIds = $profQuery->pluck('id')->toArray();

            $job = new NormalizeProfessionalExperienceJob($profIds, $write);
            $stats = $job->handle($positionNormalizer);

            if (empty($results)) {
                $results[] = [
                    'Sección / Tabla', 
                    'Total Registros', 
                    'Normalizados Determinísticamente', 
                    'Ratio de Match'
                ];
            }

            $results[] = [
                'Experiencia Profesional', 
                $stats['profesional_total'], 
                $stats['profesional_normalized'], 
                $stats['profesional_total'] > 0 ? round(($stats['profesional_normalized'] / $stats['profesional_total']) * 100, 2) . '%' : '0%'
            ];
        }

        // 3. Process Postgraduate Records
        if (!$only || $only === 'postgraduate-records') {
            $this->comment('Procesando Formación de Posgrado...');

            $postgQuery = FormacionPostgrado::query();
            if ($limit) $postgQuery->limit($limit);
            $postgIds = $postgQuery->pluck('id')->toArray();

            $job = new NormalizePostgraduateRecordsJob($postgIds, $write);
            $stats = $job->handle($postgradNormalizer);

            if (empty($results)) {
                $results[] = [
                    'Sección / Tabla', 
                    'Total Registros', 
                    'Normalizados Determinísticamente', 
                    'Ratio de Match'
                ];
            }

            $results[] = [
                'Formación Posgrado', 
                $stats['postgrado_total'], 
                $stats['postgrado_normalized'], 
                $stats['postgrado_total'] > 0 ? round(($stats['postgrado_normalized'] / $stats['postgrado_total']) * 100, 2) . '%' : '0%'
            ];
        }

        $this->info('============================================================');
        $this->info('                 RESUMEN DE NORMALIZACIÓN                   ');
        $this->info('============================================================');
        
        $this->table($results[0], array_slice($results, 1));

        if ($write) {
            $this->info('🎉 ¡La normalización semántica se ha grabado exitosamente en base de datos!');
        } else {
            $this->info('💡 Simulación completada. No se grabaron cambios en MySQL. Usa --write para persistir.');
        }
        $this->info('============================================================');

        return 0;
    }
}
