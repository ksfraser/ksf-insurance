<?php

declare(strict_types=1);

namespace Ksfraser\Insurance;

use KSFII\AssumptionManagement\AssumptionManager;
use Ksfraser\ModulesCommon\CalculationContext;

/**
 * Cost Per Thousand Calculator
 *
 * Calculates the cost per $1,000 of insurance coverage,
 * providing a standardized way to compare insurance costs.
 */
class CostPerThousandCalculator
{
    private AssumptionManager $assumptionManager;

    public function __construct(AssumptionManager $assumptionManager)
    {
        $this->assumptionManager = $assumptionManager;
    }

    /**
     * Calculate cost per thousand dollars of insurance coverage.
     */
    public function calculateCostPerThousand(CalculationContext $context): array
    {
        $annualPremium = (float) $context->getParameter('annual_premium');
        $deathBenefit = (float) $context->getParameter('death_benefit', 100000.0); // Default $100K if not specified
        $policyType = $context->getParameter('policy_type', 'whole_life');
        $currentAge = (int) $context->getParameter('current_age');

        // Calculate basic cost per thousand
        $costPerThousand = ($annualPremium / $deathBenefit) * 1000;

        // Adjust for policy type and other factors
        $adjustedCost = $this->adjustForPolicyType($costPerThousand, $policyType, $currentAge);

        // Calculate cost ratios and benchmarks
        $industryAverage = $this->getIndustryAverage($policyType, $currentAge);
        $costRatio = $industryAverage > 0 ? ($adjustedCost / $industryAverage) : 1.0;

        // Calculate net cost (after cash value consideration)
        $currentCashValue = (float) $context->getParameter('current_cash_value', 0.0);
        $netCostPerThousand = $this->calculateNetCost($annualPremium, $deathBenefit, $currentCashValue);

        return [
            'cost_per_thousand' => round($adjustedCost, 2),
            'basic_cost_per_thousand' => round($costPerThousand, 2),
            'net_cost_per_thousand' => round($netCostPerThousand, 2),
            'industry_average' => round($industryAverage, 2),
            'cost_ratio_vs_industry' => round($costRatio, 2),
            'cost_rating' => $this->calculateCostRating($costRatio),
            'death_benefit_used' => $deathBenefit,
            'annual_premium_used' => $annualPremium,
            'policy_type' => $policyType
        ];
    }

    /**
     * Adjust cost per thousand based on policy type and age.
     */
    private function adjustForPolicyType(float $basicCost, string $policyType, int $age): float
    {
        $adjustmentFactor = 1.0;

        switch ($policyType) {
            case 'term_life':
                // Term life is typically cheaper per thousand
                $adjustmentFactor = 0.8;
                break;

            case 'whole_life':
                // Whole life includes cash value component
                $adjustmentFactor = 1.2;
                break;

            case 'universal_life':
                // Universal life has flexible premiums
                $adjustmentFactor = 1.1;
                break;

            case 'variable_life':
                // Variable life has investment risk
                $adjustmentFactor = 1.3;
                break;
        }

        // Age adjustment - older ages generally have higher costs
        if ($age >= 50) {
            $adjustmentFactor *= 1.5;
        } elseif ($age >= 40) {
            $adjustmentFactor *= 1.2;
        } elseif ($age >= 30) {
            $adjustmentFactor *= 1.0;
        } else {
            $adjustmentFactor *= 0.9;
        }

        return $basicCost * $adjustmentFactor;
    }

    /**
     * Get industry average cost per thousand for comparison.
     */
    private function getIndustryAverage(string $policyType, int $age): float
    {
        // Simplified industry averages (in reality, these would come from actuarial data)
        $averages = [
            'term_life' => [
                '18-29' => 0.50,
                '30-39' => 0.75,
                '40-49' => 1.25,
                '50-59' => 2.50,
                '60+' => 5.00
            ],
            'whole_life' => [
                '18-29' => 15.00,
                '30-39' => 18.00,
                '40-49' => 22.00,
                '50-59' => 28.00,
                '60+' => 35.00
            ],
            'universal_life' => [
                '18-29' => 8.00,
                '30-39' => 10.00,
                '40-49' => 12.00,
                '50-59' => 15.00,
                '60+' => 18.00
            ],
            'variable_life' => [
                '18-29' => 12.00,
                '30-39' => 15.00,
                '40-49' => 18.00,
                '50-59' => 22.00,
                '60+' => 28.00
            ]
        ];

        $ageBracket = $this->getAgeBracket($age);
        return $averages[$policyType][$ageBracket] ?? 10.00; // Default fallback
    }

    /**
     * Calculate net cost per thousand after cash value consideration.
     */
    private function calculateNetCost(float $annualPremium, float $deathBenefit, float $cashValue): float
    {
        // Net cost considers the cash value as a benefit
        $netPremium = $annualPremium - ($cashValue * 0.05); // Simplified - assume 5% annual return on cash value
        $netPremium = max(0, $netPremium); // Cannot be negative

        return ($netPremium / $deathBenefit) * 1000;
    }

    /**
     * Get age bracket for industry averages.
     */
    private function getAgeBracket(int $age): string
    {
        if ($age >= 60) return '60+';
        if ($age >= 50) return '50-59';
        if ($age >= 40) return '40-49';
        if ($age >= 30) return '30-39';
        return '18-29';
    }

    /**
     * Calculate a qualitative cost rating.
     */
    private function calculateCostRating(float $costRatio): string
    {
        if ($costRatio < 0.8) {
            return 'Excellent';
        } elseif ($costRatio < 1.0) {
            return 'Good';
        } elseif ($costRatio < 1.2) {
            return 'Fair';
        } elseif ($costRatio < 1.5) {
            return 'High';
        } else {
            return 'Very High';
        }
    }
}