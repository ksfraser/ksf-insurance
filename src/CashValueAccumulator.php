<?php

declare(strict_types=1);

namespace Ksfraser\Insurance;

use KSFII\AssumptionManagement\AssumptionManager;
use Ksfraser\ModulesCommon\CalculationContext;

/**
 * Cash Value Accumulator
 *
 * Projects the growth of cash value in insurance policies over time,
 * accounting for premium payments, interest crediting, and policy fees.
 */
class CashValueAccumulator
{
    private AssumptionManager $assumptionManager;

    public function __construct(AssumptionManager $assumptionManager)
    {
        $this->assumptionManager = $assumptionManager;
    }

    /**
     * Project cash value accumulation over the policy lifetime.
     */
    public function projectCashValue(CalculationContext $context): array
    {
        $currentAge = (int) $context->getParameter('current_age');
        $currentCashValue = (float) $context->getParameter('current_cash_value', 0.0);
        $annualPremium = (float) $context->getParameter('annual_premium');
        $policyDuration = (int) $context->getParameter('policy_duration_years', 30);
        $expectedLifespan = (int) $context->getParameter('expected_lifespan', 85);

        // Get assumptions for cash value growth
        $interestRate = $this->assumptionManager->getEffectiveValue('insurance.cash_value_interest_rate') ?? 0.04;
        $expenseRatio = $this->assumptionManager->getEffectiveValue('insurance.expense_ratio') ?? 0.10; // 10% of premium
        $policyType = $context->getParameter('policy_type', 'whole_life');

        $projectedValues = [];
        $cashValue = $currentCashValue;
        $totalPremiumsPaid = 0.0;

        // Calculate remaining years
        $remainingYears = max(0, $expectedLifespan - $currentAge);

        for ($year = 0; $year <= $remainingYears; $year++) {
            $age = $currentAge + $year;

            // Add annual premium (if still paying premiums)
            if ($year < $policyDuration) {
                $annualPremiumAmount = $this->calculateAnnualPremium($annualPremium, $age, $policyType);
                $netPremium = $annualPremiumAmount * (1 - $expenseRatio); // Subtract expenses
                $cashValue += $netPremium;
                $totalPremiumsPaid += $annualPremiumAmount;
            }

            // Apply interest growth to cash value
            $interestGrowth = $cashValue * $interestRate;
            $cashValue += $interestGrowth;

            // Apply policy-specific adjustments
            $cashValue = $this->applyPolicyAdjustments($cashValue, $policyType, $age);

            $projectedValues[] = [
                'year' => $year,
                'age' => $age,
                'cash_value' => round($cashValue, 2),
                'annual_premium_paid' => $year < $policyDuration ? round($annualPremiumAmount, 2) : 0,
                'interest_earned' => round($interestGrowth, 2),
                'cumulative_premiums' => round($totalPremiumsPaid, 2)
            ];
        }

        return [
            'initial_cash_value' => $currentCashValue,
            'projected_values' => $projectedValues,
            'final_cash_value' => round($cashValue, 2),
            'total_premiums_paid' => round($totalPremiumsPaid, 2),
            'total_interest_earned' => round($cashValue - $currentCashValue - $totalPremiumsPaid, 2),
            'average_annual_return' => $remainingYears > 0 ? round((pow($cashValue / max($currentCashValue, 1), 1/$remainingYears) - 1) * 100, 2) : 0
        ];
    }

    /**
     * Calculate annual premium amount (may vary by age and policy type).
     */
    private function calculateAnnualPremium(float $basePremium, int $age, string $policyType): float
    {
        // Simplified premium calculation - in reality this would be more complex
        $ageAdjustment = 1.0;

        if ($policyType === 'term_life') {
            // Term life premiums increase more significantly with age
            if ($age >= 50) {
                $ageAdjustment = 1.5;
            } elseif ($age >= 40) {
                $ageAdjustment = 1.2;
            }
        } elseif ($policyType === 'whole_life') {
            // Whole life premiums are level, but may have small increases
            $ageAdjustment = 1.0;
        }

        return $basePremium * $ageAdjustment;
    }

    /**
     * Apply policy-specific adjustments to cash value.
     */
    private function applyPolicyAdjustments(float $cashValue, string $policyType, int $age): float
    {
        // Apply policy-specific rules
        switch ($policyType) {
            case 'variable_life':
                // Variable life may have market risk adjustments
                $marketAdjustment = $this->assumptionManager->getEffectiveValue('insurance.variable_life_market_adjustment') ?? 0.0;
                $cashValue *= (1 + $marketAdjustment);
                break;

            case 'universal_life':
                // Universal life may have minimum interest guarantees
                $minimumRate = $this->assumptionManager->getEffectiveValue('insurance.universal_life_minimum_rate') ?? 0.02;
                // This is simplified - actual implementation would track credited rates
                break;

            case 'term_life':
                // Term life typically has no cash value
                $cashValue = 0.0;
                break;
        }

        return max(0, $cashValue); // Cash value cannot be negative
    }
}