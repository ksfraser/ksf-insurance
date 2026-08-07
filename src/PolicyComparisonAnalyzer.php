<?php

declare(strict_types=1);

namespace Ksfraser\Insurance;

/**
 * Policy Comparison Analyzer
 *
 * Performs detailed side-by-side analysis of insurance policies including
 * coverage comparison, premium analysis, and gap identification.
 *
 * Single Responsibility: Analyze and compare insurance policy features and coverage.
 *
 * @author AI Assistant
 * @version 1.0
 * @since 7 November 2025
 */
class PolicyComparisonAnalyzer
{
    /**
     * Coverage types for analysis
     */
    public const COVERAGE_LIFE = 'life';
    public const COVERAGE_DISABILITY = 'disability';
    public const COVERAGE_CRITICAL_ILLNESS = 'critical_illness';
    public const COVERAGE_LONG_TERM_CARE = 'long_term_care';

    /**
     * Analyze multiple policies side-by-side
     *
     * @param array $plans Array of plan data
     * @param array $clientProfile Client financial profile
     * @return array Analysis results
     */
    public function analyzePolicies(array $plans, array $clientProfile): array
    {
        $analysis = [
            'plans' => [],
            'coverage_comparison' => [],
            'premium_comparison' => [],
            'coverage_gaps' => [],
            'strengths_weaknesses' => []
        ];

        foreach ($plans as $index => $plan) {
            $planAnalysis = $this->analyzeSinglePolicy($plan, $clientProfile);
            $analysis['plans'][] = $planAnalysis;
        }

        $analysis['coverage_comparison'] = $this->compareCoverage($plans);
        $analysis['premium_comparison'] = $this->comparePremiums($plans);
        $analysis['coverage_gaps'] = $this->identifyCoverageGaps($plans, $clientProfile);
        $analysis['strengths_weaknesses'] = $this->analyzeStrengthsWeaknesses($plans, $clientProfile);

        return $analysis;
    }

    /**
     * Analyze a single policy
     *
     * @param array $plan Plan data
     * @param array $clientProfile Client profile
     * @return array Policy analysis
     */
    private function analyzeSinglePolicy(array $plan, array $clientProfile): array
    {
        return [
            'plan_id' => $plan['id'] ?? 'unknown',
            'plan_name' => $plan['name'] ?? 'Unnamed Plan',
            'coverage_amount' => $plan['coverage_amount'] ?? 0,
            'premium_amount' => $plan['premium_amount'] ?? 0,
            'coverage_types' => $plan['coverage_types'] ?? [],
            'features' => $plan['features'] ?? [],
            'suitability_score' => $this->calculateSuitabilityScore($plan, $clientProfile)
        ];
    }

    /**
     * Compare coverage across plans
     *
     * @param array $plans Array of plans
     * @return array Coverage comparison
     */
    private function compareCoverage(array $plans): array
    {
        $comparison = [];

        foreach ([self::COVERAGE_LIFE, self::COVERAGE_DISABILITY, self::COVERAGE_CRITICAL_ILLNESS, self::COVERAGE_LONG_TERM_CARE] as $coverageType) {
            $comparison[$coverageType] = [];

            foreach ($plans as $plan) {
                $comparison[$coverageType][] = [
                    'plan_id' => $plan['id'] ?? 'unknown',
                    'coverage_amount' => $plan['coverage'][$coverageType] ?? 0,
                    'waiting_period' => $plan['waiting_periods'][$coverageType] ?? 0,
                    'benefit_period' => $plan['benefit_periods'][$coverageType] ?? 'lifetime'
                ];
            }
        }

        return $comparison;
    }

    /**
     * Compare premiums across plans
     *
     * @param array $plans Array of plans
     * @return array Premium comparison
     */
    private function comparePremiums(array $plans): array
    {
        $comparison = [];

        foreach ($plans as $plan) {
            $comparison[] = [
                'plan_id' => $plan['id'] ?? 'unknown',
                'monthly_premium' => $plan['premiums']['monthly'] ?? 0,
                'annual_premium' => $plan['premiums']['annual'] ?? 0,
                'cost_per_thousand' => $this->calculateCostPerThousand($plan)
            ];
        }

        return $comparison;
    }

    /**
     * Identify coverage gaps
     *
     * @param array $plans Array of plans
     * @param array $clientProfile Client profile
     * @return array Identified gaps
     */
    private function identifyCoverageGaps(array $plans, array $clientProfile): array
    {
        $gaps = [];
        $requiredCoverage = $clientProfile['required_coverage'] ?? [];

        foreach ($requiredCoverage as $coverageType => $requiredAmount) {
            $maxCoverage = 0;

            foreach ($plans as $plan) {
                $planCoverage = $plan['coverage'][$coverageType] ?? 0;
                $maxCoverage = max($maxCoverage, $planCoverage);
            }

            if ($maxCoverage < $requiredAmount) {
                $gaps[] = [
                    'coverage_type' => $coverageType,
                    'required_amount' => $requiredAmount,
                    'available_amount' => $maxCoverage,
                    'gap_amount' => $requiredAmount - $maxCoverage
                ];
            }
        }

        return $gaps;
    }

    /**
     * Analyze strengths and weaknesses of plans
     *
     * @param array $plans Array of plans
     * @param array $clientProfile Client profile
     * @return array Strengths and weaknesses analysis
     */
    private function analyzeStrengthsWeaknesses(array $plans, array $clientProfile): array
    {
        $analysis = [];

        foreach ($plans as $plan) {
            $analysis[$plan['id'] ?? 'unknown'] = [
                'strengths' => $this->identifyStrengths($plan, $clientProfile),
                'weaknesses' => $this->identifyWeaknesses($plan, $clientProfile)
            ];
        }

        return $analysis;
    }

    /**
     * Calculate suitability score for a plan
     *
     * @param array $plan Plan data
     * @param array $clientProfile Client profile
     * @return float Suitability score (0-100)
     */
    private function calculateSuitabilityScore(array $plan, array $clientProfile): float
    {
        // Simplified scoring algorithm - in real implementation this would be more sophisticated
        $score = 50.0; // Base score

        // Adjust based on coverage matching needs
        $coverageMatch = $this->calculateCoverageMatch($plan, $clientProfile);
        $score += $coverageMatch * 30;

        // Adjust based on affordability
        $affordability = $this->calculateAffordability($plan, $clientProfile);
        $score += $affordability * 20;

        return min(100.0, max(0.0, $score));
    }

    /**
     * Calculate coverage match percentage
     *
     * @param array $plan Plan data
     * @param array $clientProfile Client profile
     * @return float Match percentage (0-1)
     */
    private function calculateCoverageMatch(array $plan, array $clientProfile): float
    {
        $requiredCoverage = $clientProfile['required_coverage'] ?? [];
        if (empty($requiredCoverage)) {
            return 0.5; // Neutral score if no requirements specified
        }

        $totalMatch = 0.0;
        $totalRequirements = count($requiredCoverage);

        foreach ($requiredCoverage as $type => $required) {
            $provided = $plan['coverage'][$type] ?? 0;
            $match = min(1.0, $provided / $required);
            $totalMatch += $match;
        }

        return $totalMatch / $totalRequirements;
    }

    /**
     * Calculate affordability score
     *
     * @param array $plan Plan data
     * @param array $clientProfile Client profile
     * @return float Affordability score (0-1)
     */
    private function calculateAffordability(array $plan, array $clientProfile): float
    {
        $monthlyPremium = $plan['premiums']['monthly'] ?? 0;
        $monthlyIncome = $clientProfile['monthly_income'] ?? 0;

        if ($monthlyIncome <= 0) {
            return 0.5; // Neutral if no income data
        }

        $premiumRatio = $monthlyPremium / $monthlyIncome;

        // Ideal ratio is 1-3% of income for insurance premiums
        if ($premiumRatio <= 0.01) return 1.0; // Very affordable
        if ($premiumRatio <= 0.03) return 0.8; // Affordable
        if ($premiumRatio <= 0.05) return 0.6; // Moderately affordable
        if ($premiumRatio <= 0.08) return 0.3; // Expensive
        return 0.1; // Very expensive
    }

    /**
     * Calculate cost per thousand coverage
     *
     * @param array $plan Plan data
     * @return float Cost per $1000 coverage
     */
    private function calculateCostPerThousand(array $plan): float
    {
        $annualPremium = $plan['premiums']['annual'] ?? 0;
        $coverageAmount = $plan['coverage_amount'] ?? 0;

        if ($coverageAmount <= 0) {
            return 0.0;
        }

        return ($annualPremium / $coverageAmount) * 1000;
    }

    /**
     * Identify strengths of a plan
     *
     * @param array $plan Plan data
     * @param array $clientProfile Client profile
     * @return array List of strengths
     */
    private function identifyStrengths(array $plan, array $clientProfile): array
    {
        $strengths = [];

        // Check coverage adequacy
        if ($this->calculateCoverageMatch($plan, $clientProfile) > 0.8) {
            $strengths[] = 'Excellent coverage match for client needs';
        }

        // Check affordability
        if ($this->calculateAffordability($plan, $clientProfile) > 0.7) {
            $strengths[] = 'Highly affordable premium';
        }

        // Check for additional features
        if (!empty($plan['features'])) {
            $strengths[] = 'Includes valuable additional features: ' . implode(', ', $plan['features']);
        }

        return $strengths;
    }

    /**
     * Identify weaknesses of a plan
     *
     * @param array $plan Plan data
     * @param array $clientProfile Client profile
     * @return array List of weaknesses
     */
    private function identifyWeaknesses(array $plan, array $clientProfile): array
    {
        $weaknesses = [];

        // Check coverage gaps
        if ($this->calculateCoverageMatch($plan, $clientProfile) < 0.5) {
            $weaknesses[] = 'Insufficient coverage for client needs';
        }

        // Check affordability issues
        if ($this->calculateAffordability($plan, $clientProfile) < 0.4) {
            $weaknesses[] = 'Premium may be unaffordable for client';
        }

        // Check for high cost per thousand
        if ($this->calculateCostPerThousand($plan) > 5.0) {
            $weaknesses[] = 'High cost per thousand dollars of coverage';
        }

        return $weaknesses;
    }
}