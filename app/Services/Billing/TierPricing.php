<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Tier prices from the JSX prototype (rocket-coding.jsx line 831-833 —
 * the invoice preview table). Treat as the source of truth.
 *
 * Pricing is ADDITIVE, not exclusive. A single case may incur all three
 * tiers: every case pays Claim Submission, cases with AI output additionally
 * pay AI Auto-Coding, cases Rocket coded end-to-end additionally pay Full-
 * Service Billing.
 */
class TierPricing
{
    public const CLAIM_SUBMISSION_USD = 1.75;

    public const AI_AUTO_CODING_USD = 1.00;

    public const FULL_SERVICE_BILLING_USD = 5.00;

    // Tier keys stored verbatim in invoice_lines.tier — stable values the
    // Deployer's billing report groups by.
    public const TIER_CLAIM_SUBMISSION = 'claimSubmission';

    public const TIER_AI_AUTO_CODING = 'aiAutoCoding';

    public const TIER_FULL_SERVICE = 'fullServiceBilling';
}
