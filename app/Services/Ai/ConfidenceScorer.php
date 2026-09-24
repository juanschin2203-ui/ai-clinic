<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Derives a confidence score for AI-generated CPT / DX suggestions from
 * STRUCTURAL SIGNALS, not the model's self-reported confidence.
 *
 * Per the security invariant (feedback_security_invariants.md): LLMs are
 * poorly calibrated at self-reporting certainty. We treat the model's
 * `confidence` field as one signal among several, and give much more
 * weight to whether citations are actually grounded in the source notes.
 *
 * Score formula for a single code suggestion:
 *
 *   1.00   starts full
 *   -0.40  if no citation field present
 *   -0.30  if citation string is empty
 *   -0.20  if citation does NOT appear verbatim in the notes
 *           (trivially defeated via lowercase+whitespace compare — so
 *            we do that before matching)
 *   +0.00  multiply by the model's self-reported confidence (0..1)
 *
 * Result is clamped to [0, 1]. Scores < 0.50 get flagged for human review
 * in the SuggestCptStage — see its `rejectLowConfidenceSuggestions()` step.
 */
class ConfidenceScorer
{
    /**
     * @param  array $suggestion  a single {code, desc, reasoning, citation, confidence} row
     * @param  string $sourceNotes  the full clinician note text the code was proposed from
     */
    public function scoreCodeSuggestion(array $suggestion, string $sourceNotes): float
    {
        // --- Citation-grounding gate (HARD) -------------------------------
        // No citation, empty citation, or citation that doesn't appear
        // verbatim in the source notes → HARD FAIL at 0.
        //
        // This is the anti-hallucination defense. Per the user's security
        // invariant, we do NOT trust the model's self-reported confidence
        // for ungrounded suggestions — grounding is a binary precondition.
        if (! isset($suggestion['citation'])) {
            return 0.0;
        }

        $citation = trim((string) $suggestion['citation']);
        if ($citation === '') {
            return 0.0;
        }

        if (! $this->citationMatches($citation, $sourceNotes)) {
            return 0.0;
        }

        // --- Model self-report (only applies to grounded citations) -------
        $selfReport = $suggestion['confidence'] ?? 0.7;
        $selfReport = is_numeric($selfReport)
            ? max(0.0, min(1.0, (float) $selfReport))
            : 0.7;

        return $selfReport;
    }

    /**
     * Returns true when `$citation` appears in `$sourceNotes` after
     * normalizing whitespace and case. This is the "grounded" check — if
     * it fails, the AI likely hallucinated the supporting quote.
     */
    public function citationMatches(string $citation, string $sourceNotes): bool
    {
        $normalizedCitation = $this->normalize($citation);
        $normalizedSource = $this->normalize($sourceNotes);

        if ($normalizedCitation === '') {
            return false;
        }

        return str_contains($normalizedSource, $normalizedCitation);
    }

    private function normalize(string $text): string
    {
        // Collapse all whitespace runs (including newlines) into single
        // spaces, lowercase, trim leading/trailing.
        $noWs = preg_replace('/\s+/', ' ', $text) ?? '';

        return trim(mb_strtolower($noWs));
    }

    /**
     * Aggregate: given a list of {code, confidence} objects, return the
     * overall confidence for the stage. Conservative: min of all
     * individual scores, not average. One bad suggestion drags the whole
     * stage down so the Deployer can spot it in the audit log.
     */
    public function aggregate(array $scores): float
    {
        if ($scores === []) {
            return 0.0;
        }

        return min($scores);
    }
}
