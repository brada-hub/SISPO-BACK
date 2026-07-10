<?php

namespace App\Services\Ai;

/**
 * Builds versioned, structured prompts for AI analysis.
 *
 * Each prompt is versioned so that results can be traced back to the exact
 * prompt that generated them — critical for academic reproducibility.
 */
class PromptBuilder
{
    public const CV_ANALYSIS_VERSION   = 'v1.0';
    public const MATCHING_VERSION      = 'v1.0';
    public const OBSERVATIONS_VERSION  = 'v1.0';

    /**
     * Build the prompt for CV/resume analysis.
     */
    public function buildCvAnalysisPrompt(string $cvText): array
    {
        $prompt = <<<PROMPT
Eres un analista de recursos humanos experto. Analiza el siguiente texto extraído de un CV/Hoja de Vida y extrae información estructurada.

TEXTO DEL CV:
---
{$cvText}
---

Responde ÚNICAMENTE con un JSON válido (sin markdown, sin backticks, sin explicaciones) con esta estructura exacta:
{
  "datos_personales": {
    "nombre_detectado": "string o null",
    "profesion_principal": "string o null",
    "nivel_academico": "Técnico Superior|Licenciatura|Maestría|Doctorado|Otro|No detectado"
  },
  "formacion_academica": [
    {
      "titulo": "string",
      "institucion": "string",
      "anio_obtencion": null,
      "area": "string"
    }
  ],
  "experiencia_laboral": [
    {
      "cargo": "string",
      "empresa_institucion": "string",
      "periodo": "string",
      "duracion_estimada_meses": 0,
      "responsabilidades_clave": ["string"]
    }
  ],
  "habilidades_tecnicas": ["string"],
  "habilidades_blandas": ["string"],
  "idiomas": [{"idioma": "string", "nivel": "string"}],
  "certificaciones": [{"nombre": "string", "institucion": "string", "anio": null}],
  "experiencia_total_estimada_anios": 0,
  "resumen_perfil": "string (máximo 100 palabras)",
  "confianza_extraccion": 0
}

REGLAS ESTRICTAS E IRROMPIBLES:
- NO alucines ni inventes NADA.
- Usa SOLO evidencia textual presente en el documento.
- Si un dato no está EXPLÍCITAMENTE en el texto, debes devolver null o un array vacío [].
- Nunca asumas, infieras o deduzcas información faltante (ej. si no dice "Universidad", no la inventes).
- Las duraciones deben estimarse en meses solo si hay fechas de inicio y fin claras. Si no hay fechas, usa 0.
- El resumen debe ser objetivo y en tercera persona, basado solo en la experiencia leída.
- confianza_extraccion: 0-100, qué tan completa y legible fue la información.
- Responde SOLO con el JSON, nada más, sin backticks ni explicaciones extras.
PROMPT;

        return [
            'prompt'  => $prompt,
            'version' => self::CV_ANALYSIS_VERSION,
        ];
    }

    /**
     * Build the prompt for matching a candidate profile against job requirements.
     */
    public function buildMatchingPrompt(
        array  $candidateProfile,
        string $cargoNombre,
        array  $requisitos,
        ?string $formacionRequerida = null,
        ?float  $experienciaMinima = null
    ): array {
        $profileJson     = json_encode($candidateProfile, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $requisitosJson  = json_encode($requisitos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $formacion       = $formacionRequerida ?? 'No especificada';
        $experiencia     = $experienciaMinima !== null ? "{$experienciaMinima} años" : 'No especificada';

        $prompt = <<<PROMPT
Eres un sistema de matching laboral. Compara el perfil del candidato con los requisitos de la convocatoria.

PERFIL DEL CANDIDATO:
{$profileJson}

REQUISITOS DE LA CONVOCATORIA:
- Cargo: {$cargoNombre}
- Requisitos documentales: {$requisitosJson}
- Formación requerida: {$formacion}
- Experiencia mínima requerida: {$experiencia}

Responde ÚNICAMENTE con JSON válido (sin markdown, sin backticks):
{
  "requisitos_evaluados": [
    {
      "requisito": "string",
      "cumple": true,
      "evidencia": "string (texto del CV que lo respalda o 'No encontrado')",
      "confianza": 0
    }
  ],
  "observaciones": "string (análisis breve de fortalezas y gaps, máximo 150 palabras)",
  "fortalezas": ["string"],
  "debilidades": ["string"],
  "recomendacion_general": "string (máximo 50 palabras)"
}

REGLAS ESTRICTAS E IRROMPIBLES:
- Sé 100% objetivo y básate ÚNICAMENTE en la evidencia textual extraída del CV.
- Valores para cumple: true, false, o "parcial".
- Si no hay evidencia CLARA Y EXPLÍCITA, marca como false con confianza baja (0-30).
- NO asumas, no deduzcas y no perdones la falta de evidencia. Si el CV no dice algo, entonces no lo cumple.
- confianza: 0-100 por cada requisito evaluado, representando certeza de la decisión.
- NO calcules puntajes totales ni decidas la contratación. Solo compara.
PROMPT;

        return [
            'prompt'  => $prompt,
            'version' => self::MATCHING_VERSION,
        ];
    }

    /**
     * Build the prompt for generating human-readable observations.
     */
    public function buildObservationsPrompt(array $matchingData, string $cargoNombre): array
    {
        $dataJson = json_encode($matchingData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Basado en los siguientes resultados de matching, genera observaciones profesionales para un comité de evaluación de RRHH.

DATOS DEL MATCHING:
{$dataJson}

CARGO: {$cargoNombre}

Genera un párrafo de observaciones (máximo 200 palabras) que:
1. Sea objetivo y profesional
2. Destaque los puntos fuertes del candidato
3. Señale los aspectos que requieren verificación
4. No tome decisiones, solo presente información
5. Use un tono institucional y formal

Responde SOLO con el texto de las observaciones, sin JSON, sin comillas.
PROMPT;

        return [
            'prompt'  => $prompt,
            'version' => self::OBSERVATIONS_VERSION,
        ];
    }

    /**
     * Build the strict prompt for the consolidated evaluation.
     */
    public function buildConsolidatedEvaluationPrompt(string $context): array
    {
        $prompt = <<<PROMPT
Eres un evaluador de Recursos Humanos universitario extremadamente estricto, objetivo y analítico.
Tu tarea es evaluar el siguiente "Expediente Institucional Consolidado" de un postulante frente a los requisitos de la convocatoria.

CONTEXTO DEL EXPEDIENTE Y CONVOCATORIA:
{$context}

REGLAS ESTRICTAS E IRROMPIBLES:
1. NO inventes experiencia, NO asumas habilidades, NO generes fortalezas falsas.
2. Si la carrera o experiencia NO está relacionada con el cargo, la afinidad es nula.
3. Penaliza fuertemente la ausencia de experiencia o datos insuficientes.
4. El "score_total" debe ser entre 0 y 100 y depender EXCLUSIVAMENTE de la evidencia explícita en el expediente. Si no hay evidencia, califica con 0-10.
5. "clasificacion" SOLO puede ser: "apto", "parcialmente_apto", o "no_apto".
6. "fortalezas" y "brechas" deben ser arrays de strings cortos. Si no hay, devuelve [].
7. "observaciones_ia" debe explicar objetivamente el perfil general.
8. "justificacion_score" debe justificar matemáticamente por qué se asignó ese score exacto.

RESPONDE ÚNICA Y EXCLUSIVAMENTE CON UN JSON VÁLIDO. NO INCLUYAS MARKDOWN (ni ```json).
ESTRUCTURA EXACTA REQUERIDA:
{
  "score_total": 0,
  "clasificacion": "no_apto",
  "fortalezas": [],
  "brechas": [],
  "observaciones_ia": "",
  "justificacion_score": ""
}
PROMPT;

        return [
            'prompt'  => $prompt,
            'version' => '2.0',
        ];
    }
}
