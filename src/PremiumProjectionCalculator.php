<?php

declare(strict_types=1);

namespace Ksfraser\Insurance;

/**
 * Premium Projection Calculator
 *
 * Calculates lifetime premium payments for insurance policies,
 * including projections for future premium increases and total costs.
 */
class PremiumProjectionCalculator
{
    /**
     * Calculate lifetime premium projections.
     */
    public function calculateLifetimePremiums(CalculationContext $context): array
    {
        $currentAge = (int) $context->getParameter('current_age');
        $annualPremium = (float) $context->getParameter('annual_premium');
        $policyDuration = (int) $context->getParameter('policy_duration_years', 30);
        $expectedLifespan = (int) $context->getParameter('expected_lifespan', 85);

        // Calculate remaining years of premium payments
        $remainingYears = max(0, $expectedLifespan - $currentAge - $policyDuration);

        $yearlyPremiums = [];
        $totalLifetimePremiums = 0.0;
        $currentPremium = $annualPremium;

        // Project premiums for each year
        for ($year = 1; $year <= $remainingYears; $year++) {
            $age = $currentAge + $year;

            // Apply age-based premium increases (simplified model)
            $premiumIncrease = $this->calculatePremiumIncrease($age, $currentPremium);
            $currentPremium += $premiumIncrease;

            $yearlyPremiums[] = [
                'year' => $year,
                'age' => $age,
                'annual_premium' => round($currentPremium, 2),
                'cumulative_premiums' => round($totalLifetimePremiums + $currentPremium, 2)
            ];

            $totalLifetimePremiums += $currentPremium;
        }

        return [
            'current_annual_premium' => $annualPremium,
            'projected_yearly_premiums' => $yearlyPremiums,
            'total_lifetime_premiums' => round($totalLifetimePremiums, 2),
            'remaining_years' => $remainingYears,
            'expected_lifespan' => $expectedLifespan,
            'average_annual_premium' => $remainingYears > 0 ? round($totalLifetimePremiums / $remainingYears, 2) : 0
        ];
    }

    /**
     * Calculate premium increase based on age (simplified model).
     */
    private function calculatePremiumIncrease(int $age, float $currentPremium): float
    {
        // Simplified premium increase model based on age brackets
        if ($age >= 50) {
            return $currentPremium * 0.03; // 3% annual increase
        } elseif ($age >= 40) {
            return $currentPremium * 0.02; // 2% annual increase
        } elseif ($age >= 30) {
            return $currentPremium * 0.015; // 1.5% annual increase
        } else {
            return $currentPremium * 0.01; // 1% annual increase
        }
    }
}