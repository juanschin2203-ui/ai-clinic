<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API key (from .env)
    |--------------------------------------------------------------------------
    | Never commit. Production uses the value from AWS Secrets Manager via
    | a 12-factor env var.
    */
    'api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Models per stage
    |--------------------------------------------------------------------------
    | Extraction is cheap + fast → Haiku. Reasoning stages (CPT suggestion,
    | modifier inference, compliance check, report drafting, DX mapping) need
    | stronger model → Sonnet. Configurable via .env so we can swap as Claude
    | releases new versions without touching code.
    */
    'models' => [
        'extraction' => env('ANTHROPIC_MODEL_EXTRACTION', 'claude-haiku-4-5'),
        'reasoning' => env('ANTHROPIC_MODEL_REASONING', 'claude-sonnet-4-6'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-request + per-case token guardrails
    |--------------------------------------------------------------------------
    | Hard caps to prevent runaway spend when a pathological case or prompt
    | tries to consume unbounded tokens.
    |
    |   max_tokens_per_request — passed as `max_tokens` to each API call
    |   max_tokens_per_case    — summed across all stages for one case; if
    |                            exceeded, the pipeline aborts and the case
    |                            goes to `needsReview` with a budget-exceeded
    |                            error flag
    */
    'max_tokens_per_request' => (int) env('ANTHROPIC_MAX_TOKENS_PER_REQUEST', 4096),
    'max_tokens_per_case' => (int) env('ANTHROPIC_MAX_TOKENS_PER_CASE', 50000),

    /*
    |--------------------------------------------------------------------------
    | Request timeout + retries
    |--------------------------------------------------------------------------
    */
    'request_timeout_seconds' => (int) env('ANTHROPIC_REQUEST_TIMEOUT', 60),
    'retry_attempts' => 3,
    'retry_backoff_seconds' => [2, 4, 8],

    /*
    |--------------------------------------------------------------------------
    | Pipeline stage ordering
    |--------------------------------------------------------------------------
    | Runs strictly in this order. Each stage sees the case state as mutated
    | by earlier stages. If a stage fails after 3 retries, the case moves to
    | `needsReview` and the pipeline halts — remaining stages are NOT run.
    */
    'pipeline_stages' => [
        \App\Services\Ai\Stages\ExtractPatientDataStage::class,
        \App\Services\Ai\Stages\DraftReportStage::class,
        \App\Services\Ai\Stages\SuggestCptStage::class,
        \App\Services\Ai\Stages\ApplyModifiersStage::class,
        \App\Services\Ai\Stages\MapDiagnosesStage::class,
        \App\Services\Ai\Stages\CheckComplianceStage::class,
    ],
];
