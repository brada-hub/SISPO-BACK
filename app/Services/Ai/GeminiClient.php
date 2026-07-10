<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP client for Google Gemini API.
 *
 * Uses the Gemini REST API (v1beta) with the generateContent endpoint.
 * Designed for the free tier (gemini-2.0-flash, 15 RPM, 1M tokens/day).
 */
class GeminiClient
{
    private string $apiKey;
    private string $model;
    private float  $temperature;
    private int    $maxOutputTokens;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    public function __construct()
    {
        $this->apiKey          = config('services.gemini.api_key', '');
        $this->model           = config('services.gemini.model', 'gemini-2.0-flash');
        $this->temperature     = (float) config('services.gemini.temperature', 0.3);
        $this->maxOutputTokens = (int) config('services.gemini.max_tokens', 4096);
    }

    /**
     * Send a prompt to Gemini and return the parsed JSON response.
     *
     * @param  string  $prompt  The full prompt text
     * @return array{response: array, raw: string, tokens_used: int|null}
     *
     * @throws \RuntimeException On API failure or invalid response
     */
    public function analyze(string $prompt): array
    {
        if (empty($this->apiKey)) {
            Log::error('GeminiClient: API key vacía. Verificar AI_API_KEY en .env y config/services.php');
            throw new \RuntimeException(
                'Gemini API key not configured. Set AI_API_KEY in your .env file and run php artisan config:clear.'
            );
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $maskedKey = substr($this->apiKey, 0, 8) . '...' . substr($this->apiKey, -4);

        Log::info('GeminiClient: [ETAPA 1] Iniciando request HTTP', [
            'model'         => $this->model,
            'url_base'      => "{$this->baseUrl}/models/{$this->model}:generateContent",
            'api_key_masked' => $maskedKey,
            'prompt_length' => strlen($prompt),
            'temperature'   => $this->temperature,
            'max_tokens'    => $this->maxOutputTokens,
        ]);

        $startTime = microtime(true);

        try {
            $httpResponse = Http::timeout(120)
                ->withoutVerifying()
                ->retry(2, 5000, function (\Exception $exception, $request) {
                    // Do NOT retry on 429 (quota), 401 (unauthorized), 403 (forbidden)
                    if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                        $status = $exception->response?->status();
                        if (in_array($status, [401, 403, 429])) {
                            Log::warning("GeminiClient: NO reintentando HTTP {$status} (no es transitorio)");
                            return false;
                        }
                    }
                    Log::info('GeminiClient: Reintentando request HTTP tras error transitorio');
                    return true;
                })
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature'      => $this->temperature,
                        'maxOutputTokens'  => $this->maxOutputTokens,
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('GeminiClient: Error de conexión (timeout/red)', [
                'message' => $e->getMessage(),
            ]);
            throw new \RuntimeException('Gemini API: Error de conexión — ' . $e->getMessage(), 0, $e);
        }

        $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

        Log::info('GeminiClient: [ETAPA 2] Respuesta HTTP recibida', [
            'status'     => $httpResponse->status(),
            'elapsed_ms' => $elapsedMs,
        ]);

        if ($httpResponse->failed()) {
            $errorBody = $httpResponse->body();
            $status = $httpResponse->status();

            Log::error('GeminiClient: [ERROR] Gemini API error', [
                'status' => $status,
                'body'   => substr($errorBody, 0, 1000),
            ]);

            if ($status === 429) {
                $errorJson = $httpResponse->json();
                $errorMsg = $errorJson['error']['message'] ?? 'Quota exceeded';
                throw new \RuntimeException(
                    "Gemini API: Cuota excedida (HTTP 429). {$errorMsg}"
                );
            }

            if ($status === 401 || $status === 403) {
                throw new \RuntimeException(
                    "Gemini API: API Key inválida o sin permisos (HTTP {$status}). Verifique AI_API_KEY."
                );
            }

            throw new \RuntimeException(
                "Gemini API returned HTTP {$status}: " . substr($errorBody, 0, 300)
            );
        }

        $body = $httpResponse->json();

        // Extract the text content from Gemini's response structure.
        // Gemini 2.5+ "thinking" models may return multiple parts:
        //   parts[0] = {"thought": "..."} (internal reasoning, skip this)
        //   parts[1] = {"text": "..."}    (actual response)
        // We need to find the part that has 'text', not 'thought'.
        $rawText = null;
        $parts = $body['candidates'][0]['content']['parts'] ?? [];

        foreach ($parts as $part) {
            if (isset($part['text']) && !isset($part['thought'])) {
                $rawText = $part['text'];
                break;
            }
        }

        // Fallback: if all parts have 'thought', try the last part's text
        if (!$rawText && !empty($parts)) {
            $lastPart = end($parts);
            $rawText = $lastPart['text'] ?? null;
        }

        if (! $rawText) {
            Log::error('GeminiClient: [ERROR] Respuesta vacía o malformada', [
                'body'        => json_encode($body, JSON_UNESCAPED_UNICODE),
                'candidates'  => isset($body['candidates']) ? count($body['candidates']) : 0,
                'parts_count' => count($parts),
                'parts_keys'  => array_map(fn($p) => array_keys($p), $parts),
            ]);
            throw new \RuntimeException('Gemini returned an empty or malformed response.');
        }

        // Extract token usage
        $tokensUsed = $body['usageMetadata']['totalTokenCount'] ?? null;

        Log::info('GeminiClient: [ETAPA 3] Respuesta extraída correctamente', [
            'tokens_used'   => $tokensUsed,
            'processing_ms' => $elapsedMs,
            'raw_length'    => mb_strlen($rawText),
            'raw_preview'   => mb_substr($rawText, 0, 500),
        ]);

        // Parse the JSON response from the AI
        Log::info('GeminiClient: [ETAPA 4] Decodificando JSON devuelto por modelo');

        $parsed = $this->parseJsonResponse($rawText);

        Log::info('GeminiClient: [ETAPA 5] JSON parseado correctamente', [
            'keys' => array_keys($parsed),
        ]);

        // Basic schema validation for CV extraction
        if (is_array($parsed) && isset($parsed['datos_personales'])) {
            if (!isset($parsed['nivel_academico']) && !isset($parsed['datos_personales']['nivel_academico'])) {
                Log::warning("GeminiClient: JSON devuelto no contiene nivel_academico. Posible alucinación o mala estructura.");
            }
        }

        return [
            'response'        => $parsed,
            'raw'             => $rawText,
            'tokens_used'     => $tokensUsed,
            'processing_ms'   => $elapsedMs,
        ];
    }

    /**
     * Send a prompt and expect a plain text response (not JSON).
     */
    public function generateText(string $prompt): array
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('Gemini API key not configured.');
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $httpResponse = Http::timeout(120)
            ->withoutVerifying()
            ->retry(2, 5000, function (\Exception $exception) {
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $status = $exception->response?->status();
                    if (in_array($status, [401, 403, 429])) {
                        return false;
                    }
                }
                return true;
            })
            ->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => $this->temperature,
                    'maxOutputTokens' => $this->maxOutputTokens,
                ],
            ]);

        if ($httpResponse->failed()) {
            throw new \RuntimeException("Gemini API error: HTTP {$httpResponse->status()}");
        }

        $body    = $httpResponse->json();
        $rawText = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $tokens  = $body['usageMetadata']['totalTokenCount'] ?? null;

        return [
            'text'        => trim($rawText),
            'tokens_used' => $tokens,
        ];
    }

    /**
     * Parse a JSON string from AI output, handling potential markdown wrappers.
     */
    private function parseJsonResponse(string $raw): array
    {
        // Remove markdown code block wrappers if present
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $cleaned = preg_replace('/\s*```$/i', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('GeminiClient: [ERROR JSON] Failed to parse Gemini JSON response', [
                'error' => json_last_error_msg(),
                'raw'   => substr($raw, 0, 500),
            ]);
            throw new \RuntimeException(
                'Gemini response is not valid JSON: ' . json_last_error_msg()
            );
        }

        return $decoded;
    }

    /**
     * Check if the API is configured and reachable.
     */
    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
    }

    public function getModel(): string
    {
        return $this->model;
    }
}
