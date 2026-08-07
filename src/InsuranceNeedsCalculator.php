<?php

declare(strict_types=1);

namespace Ksfraser\Insurance;

use KSFII\AssumptionManagement\AssumptionManager;
use DateTimeImmutable;

/**
 * Insurance Needs Analysis Calculator
 *
 * Implements comprehensive insurance needs calculations compatible with
 * Canada Life needsAnalysis.xlsm and BMO calculator methodologies.
 */
class InsuranceNeedsCalculator implements CalculationEngineInterface
{
    private AssumptionManager $assumptionManager;

    public function __construct(AssumptionManager $assumptionManager)
    {
        $this->assumptionManager = $assumptionManager;
    }

    /**
     * Calculate insurance needs based on client financial profile.
     */
    public function calculate(CalculationContext $context): CalculationResult
    {
        // Validate the context first
        $validation = $this->validate($context);
        if (!$validation->isValid) {
            return CalculationResult::failure(
                $this->getCalculationType(),
                $validation->errors,
                $validation->warnings
            );
        }

        try {
            $analysisType = $context->getParameter('analysis_type', 'life_insurance');
            $methodology = $context->getParameter('methodology', 'needs_approach');

            $results = [];

            switch ($analysisType) {
                case 'life_insurance':
                    $results = $this->calculateLifeInsuranceNeeds($context, $methodology);
                    break;
                case 'critical_illness':
                    $results = $this->calculateCriticalIllnessNeeds($context);
                    break;
                case 'combined':
                    $lifeResults = $this->calculateLifeInsuranceNeeds($context, $methodology);
                    $ciResults = $this->calculateCriticalIllnessNeeds($context);
                    $results = $this->calculateCombinedNeeds($lifeResults, $ciResults, $context);
                    break;
                default:
                    throw new CalculationException("Unsupported analysis type: {$analysisType}", $this->getCalculationType());
            }

            return CalculationResult::success(
                $this->getCalculationType(),
                $results,
                $results, // intermediate results
                [], // assumptions used
                [
                    'analysis_type' => $analysisType,
                    'methodology' => $methodology,
                    'client_id' => $context->clientId
                ]
            );

        } catch (\Exception $e) {
            return CalculationResult::failure(
                $this->getCalculationType(),
                [$e->getMessage()]
            );
        }
    }

    /**
     * Validate the calculation context.
     */
    public function validate(CalculationContext $context): ValidationResult
    {
        $errors = [];

        // Check required parameters
        $requiredParams = $this->getRequiredParameters();
        foreach ($requiredParams as $paramName => $definition) {
            if ($context->getParameter($paramName) === null) {
                $errors[] = "Required parameter missing: {$paramName}";
            }
        }

        // Validate analysis type
        $analysisType = $context->getParameter('analysis_type', 'life_insurance');
        $validTypes = ['life_insurance', 'critical_illness', 'combined'];
        if (!in_array($analysisType, $validTypes)) {
            $errors[] = "Invalid analysis type: {$analysisType}";
        }

        // Validate methodology if life insurance
        if ($analysisType === 'life_insurance' || $analysisType === 'combined') {
            $methodology = $context->getParameter('methodology', 'needs_approach');
            $validMethodologies = ['income_replacement', 'needs_approach', 'capital_retention'];
            if (!in_array($methodology, $validMethodologies)) {
                $errors[] = "Invalid methodology: {$methodology}";
            }
        }

        return new ValidationResult(empty($errors), $errors);
    }

    /**
     * Get the calculation type identifier.
     */
    public function getCalculationType(): string
    {
        return 'insurance_needs_analysis';
    }

    /**
     * Get required parameters.
     */
    public function getRequiredParameters(): array
    {
        return [
            'analysis_type' => new ParameterDefinition('analysis_type', 'string', 'Type of analysis (life_insurance, critical_illness, combined)', true)
        ];
    }

    /**
     * Get optional parameters.
     */
    public function getOptionalParameters(): array
    {
        return [
            // Analysis Configuration
            'methodology' => new ParameterDefinition('methodology', 'string', 'Calculation methodology for life insurance', false, 'needs_approach'),

            // Client Income Information
            'annual_income' => new ParameterDefinition('annual_income', 'float', 'Client\'s gross annual income', false, 0.0),
            'spouse_annual_income' => new ParameterDefinition('spouse_annual_income', 'float', 'Spouse\'s gross annual income', false, 0.0),
            'survivor_pension_income' => new ParameterDefinition('survivor_pension_income', 'float', 'Expected survivor pension income (CPP/OAS)', false, 0.0),
            'family_income_need' => new ParameterDefinition('family_income_need', 'float', 'Required family income if client dies', false, 0.0),
            'income_replacement_years' => new ParameterDefinition('income_replacement_years', 'int', 'Years family needs income replacement', false, 20),

            // Debts and Liabilities
            'mortgage_balance' => new ParameterDefinition('mortgage_balance', 'float', 'Outstanding mortgage amount', false, 0.0),
            'other_debt' => new ParameterDefinition('other_debt', 'float', 'Other debt (credit cards, loans)', false, 0.0),
            'final_expenses' => new ParameterDefinition('final_expenses', 'float', 'Final expenses (funeral, probate)', false, 0.0),
            'insured_mortgage_balance' => new ParameterDefinition('insured_mortgage_balance', 'float', 'Balance of insured mortgage portion', false, 0.0),

            // Family Financial Needs
            'emergency_fund_needed' => new ParameterDefinition('emergency_fund_needed', 'float', 'Required emergency fund amount', false, 0.0),
            'childcare_costs' => new ParameterDefinition('childcare_costs', 'float', 'Annual childcare expenses', false, 0.0),
            'education_funding' => new ParameterDefinition('education_funding', 'float', 'Total education funding for children', false, 0.0),
            'child_education_expenses' => new ParameterDefinition('child_education_expenses', 'float', 'Annual education expenses', false, 0.0),

            // Assets and Existing Coverage
            'savings_balance' => new ParameterDefinition('savings_balance', 'float', 'Cash savings and chequing', false, 0.0),
            'non_registered_investments' => new ParameterDefinition('non_registered_investments', 'float', 'Non-registered investment accounts', false, 0.0),
            'registered_investments' => new ParameterDefinition('registered_investments', 'float', 'RRSP/LIRA balances', false, 0.0),
            'tfsa_balance' => new ParameterDefinition('tfsa_balance', 'float', 'TFSA account balance', false, 0.0),
            'real_estate_value' => new ParameterDefinition('real_estate_value', 'float', 'Owned real estate value', false, 0.0),
            'business_assets_value' => new ParameterDefinition('business_assets_value', 'float', 'Business ownership value', false, 0.0),
            'current_life_insurance' => new ParameterDefinition('current_life_insurance', 'float', 'Existing life insurance coverage', false, 0.0),
            'other_death_benefits' => new ParameterDefinition('other_death_benefits', 'float', 'CPP and pension death benefits', false, 0.0),

            // Critical Illness Healthcare Costs
            'healthcare_costs_medication' => new ParameterDefinition('healthcare_costs_medication', 'float', 'Annual medication costs', false, 0.0),
            'healthcare_costs_hospital' => new ParameterDefinition('healthcare_costs_hospital', 'float', 'Hospital upgrade costs', false, 0.0),
            'healthcare_costs_homecare' => new ParameterDefinition('healthcare_costs_homecare', 'float', 'Homecare service costs', false, 0.0),
            'healthcare_costs_specialty' => new ParameterDefinition('healthcare_costs_specialty', 'float', 'Specialty medical care costs', false, 0.0),
            'healthcare_costs_equipment' => new ParameterDefinition('healthcare_costs_equipment', 'float', 'Medical equipment costs', false, 0.0),
            'healthcare_costs_renovation' => new ParameterDefinition('healthcare_costs_renovation', 'float', 'Home renovation costs', false, 0.0),
            'healthcare_costs_vehicle' => new ParameterDefinition('healthcare_costs_vehicle', 'float', 'Vehicle conversion costs', false, 0.0),
            'healthcare_costs_other' => new ParameterDefinition('healthcare_costs_other', 'float', 'Other healthcare expenses', false, 0.0),

            // Critical Illness Income Supplementation
            'monthly_income_loss_client' => new ParameterDefinition('monthly_income_loss_client', 'float', 'Client\'s monthly income loss', false, 0.0),
            'monthly_income_loss_spouse' => new ParameterDefinition('monthly_income_loss_spouse', 'float', 'Spouse\'s monthly income loss', false, 0.0),
            'mortgage_payments' => new ParameterDefinition('mortgage_payments', 'float', 'Monthly mortgage payments', false, 0.0),
            'other_debt_payments' => new ParameterDefinition('other_debt_payments', 'float', 'Monthly other debt payments', false, 0.0),
            'business_expenses' => new ParameterDefinition('business_expenses', 'float', 'Monthly business expenses', false, 0.0),
            'other_income_supplement' => new ParameterDefinition('other_income_supplement', 'float', 'Other monthly supplement costs', false, 0.0),

            // Critical Illness Lump Sum Costs
            'lump_sum_mortgage_payoff' => new ParameterDefinition('lump_sum_mortgage_payoff', 'float', 'Mortgage payoff amount', false, 0.0),
            'lump_sum_debt_payoff' => new ParameterDefinition('lump_sum_debt_payoff', 'float', 'Other debt payoff amount', false, 0.0),
            'lump_sum_family_vacation' => new ParameterDefinition('lump_sum_family_vacation', 'float', 'Family vacation funding', false, 0.0),
            'lump_sum_early_retirement' => new ParameterDefinition('lump_sum_early_retirement', 'float', 'Early retirement funding', false, 0.0),
            'lump_sum_emergency_fund' => new ParameterDefinition('lump_sum_emergency_fund', 'float', 'Emergency fund amount', false, 0.0),

            // Calculation Parameters
            'expected_rate_of_return' => new ParameterDefinition('expected_rate_of_return', 'float', 'Expected investment return rate', false, 0.05),
            'safety_margin' => new ParameterDefinition('safety_margin', 'float', 'Safety margin percentage', false, 0.10),
        ];
    }

    /**
     * Calculate life insurance needs using specified methodology.
     */
    private function calculateLifeInsuranceNeeds(CalculationContext $context, string $methodology): array
    {
        // Calculate debt and final expenses
        $debtFinalExpenses = $this->calculateDebtAndFinalExpenses($context);

        // Calculate family income needs
        $annualIncomeNeeds = $this->calculateAnnualFamilyIncomeNeeds($context);

        // Calculate total assets and existing coverage
        $totalAssetsCoverage = $this->calculateTotalAssetsAndCoverage($context);

        // Calculate insurance need based on methodology
        $lifeInsuranceNeed = match ($methodology) {
            'income_replacement' => $this->calculateIncomeReplacementNeeds($context),
            'needs_approach' => $debtFinalExpenses + $annualIncomeNeeds - $totalAssetsCoverage,
            'capital_retention' => $this->calculateCapitalRetentionNeeds($context),
            default => throw new CalculationException("Unsupported methodology: {$methodology}", $this->getCalculationType())
        };

        // Ensure non-negative result
        $lifeInsuranceNeed = max(0, $lifeInsuranceNeed);

        // Return results
        return [
            'methodology' => $methodology,
            'debt_final_expenses' => $debtFinalExpenses,
            'annual_income_needs' => $annualIncomeNeeds,
            'total_assets_coverage' => $totalAssetsCoverage,
            'calculated_need' => $lifeInsuranceNeed,
            'recommended_coverage' => $this->calculateRecommendedCoverage($lifeInsuranceNeed, $context)
        ];
    }

    /**
     * Calculate critical illness insurance needs.
     */
    private function calculateCriticalIllnessNeeds(CalculationContext $context): array
    {
        // Calculate healthcare costs
        $healthcareCosts = $this->calculateHealthcareCosts($context);

        // Calculate income supplementation needs
        $incomeSupplementation = $this->calculateIncomeSupplementation($context);

        // Calculate lump sum costs
        $lumpSumCosts = $this->calculateLumpSumCosts($context);

        // Total critical illness need
        $criticalIllnessNeed = $healthcareCosts + $incomeSupplementation + $lumpSumCosts;

        // Return results
        return [
            'healthcare_costs' => $healthcareCosts,
            'income_supplementation' => $incomeSupplementation,
            'lump_sum_costs' => $lumpSumCosts,
            'calculated_need' => $criticalIllnessNeed,
            'recommended_coverage' => $this->calculateRecommendedCoverage($criticalIllnessNeed, $context)
        ];
    }

    /**
     * Calculate combined life and critical illness needs.
     */
    private function calculateCombinedNeeds(array $lifeResults, array $ciResults, CalculationContext $context): array
    {
        $lifeNeed = $lifeResults['calculated_need'] ?? 0;
        $ciNeed = $ciResults['calculated_need'] ?? 0;

        // Calculate potential overlap (some coverage might serve both purposes)
        $overlapFactor = 0.1; // 10% overlap assumption
        $overlapAmount = min($lifeNeed, $ciNeed) * $overlapFactor;

        $combinedNeed = $lifeNeed + $ciNeed - $overlapAmount;

        return [
            'life_insurance_need' => $lifeNeed,
            'critical_illness_need' => $ciNeed,
            'overlap_amount' => $overlapAmount,
            'combined_calculated_need' => $combinedNeed,
            'recommended_coverage' => $this->calculateRecommendedCoverage($combinedNeed, $context)
        ];
    }

    /**
     * Calculate debt elimination and final expenses.
     */
    private function calculateDebtAndFinalExpenses(CalculationContext $context): float
    {
        return ($context->getParameter('mortgage_balance', 0.0)) +
               ($context->getParameter('other_debt', 0.0)) +
               ($context->getParameter('final_expenses', 0.0));
    }

    /**
     * Calculate annual family income needs.
     */
    private function calculateAnnualFamilyIncomeNeeds(CalculationContext $context): float
    {
        $familyIncomeNeed = $context->getParameter('family_income_need', 0.0);
        $survivorPensionIncome = $context->getParameter('survivor_pension_income', 0.0);
        $replacementYears = $context->getParameter('income_replacement_years', 20);

        // Calculate net income need
        $annualIncomeNeed = max(0, $familyIncomeNeed - $survivorPensionIncome);

        // Apply inflation and investment return assumptions
        $inflationRate = $this->getAssumptionValue('inflation_rate', $context->clientId, 0.02);
        $investmentReturn = $context->getParameter('expected_rate_of_return', 0.05);

        // Present value calculation for income needs
        if ($investmentReturn > 0) {
            $annualIncomeNeed = $annualIncomeNeed * ((1 - pow(1 + $investmentReturn, -$replacementYears)) / $investmentReturn);
        } else {
            $annualIncomeNeed = $annualIncomeNeed * $replacementYears;
        }

        return $annualIncomeNeed;
    }

    /**
     * Calculate total assets and existing coverage.
     */
    private function calculateTotalAssetsAndCoverage(CalculationContext $context): float
    {
        return ($context->getParameter('savings_balance', 0.0)) +
               ($context->getParameter('non_registered_investments', 0.0)) +
               ($context->getParameter('registered_investments', 0.0)) +
               ($context->getParameter('tfsa_balance', 0.0)) +
               ($context->getParameter('real_estate_value', 0.0)) +
               ($context->getParameter('business_assets_value', 0.0)) +
               ($context->getParameter('current_life_insurance', 0.0)) +
               ($context->getParameter('other_death_benefits', 0.0)) +
               ($context->getParameter('insured_mortgage_balance', 0.0));
    }

    /**
     * Calculate income replacement methodology needs.
     */
    private function calculateIncomeReplacementNeeds(CalculationContext $context): float
    {
        $annualIncome = $context->getParameter('annual_income', 0.0);
        $replacementRatio = $this->getAssumptionValue('income_replacement_ratio', $context->clientId, 0.70);
        $replacementYears = $context->getParameter('income_replacement_years', 20);

        $annualReplacement = $annualIncome * $replacementRatio;
        $totalReplacement = $annualReplacement * $replacementYears;

        // Add debt and final expenses
        $totalReplacement += $this->calculateDebtAndFinalExpenses($context);

        // Subtract existing assets
        $totalReplacement -= $this->calculateTotalAssetsAndCoverage($context);

        return max(0, $totalReplacement);
    }

    /**
     * Calculate capital retention methodology needs.
     */
    private function calculateCapitalRetentionNeeds(CalculationContext $context): float
    {
        // Capital retention focuses on preserving estate value
        $annualIncome = $context->getParameter('annual_income', 0.0);
        $capitalRetentionYears = $context->getParameter('income_replacement_years', 25);
        $retentionRate = $this->getAssumptionValue('capital_retention_rate', $context->clientId, 0.80);

        $capitalNeeded = $annualIncome * $capitalRetentionYears * $retentionRate;
        $capitalNeeded += $this->calculateDebtAndFinalExpenses($context);
        $capitalNeeded -= $this->calculateTotalAssetsAndCoverage($context);

        return max(0, $capitalNeeded);
    }

    /**
     * Calculate healthcare costs for critical illness.
     */
    private function calculateHealthcareCosts(CalculationContext $context): float
    {
        return ($context->getParameter('healthcare_costs_medication', 0.0)) +
               ($context->getParameter('healthcare_costs_hospital', 0.0)) +
               ($context->getParameter('healthcare_costs_homecare', 0.0)) +
               ($context->getParameter('healthcare_costs_specialty', 0.0)) +
               ($context->getParameter('healthcare_costs_equipment', 0.0)) +
               ($context->getParameter('healthcare_costs_renovation', 0.0)) +
               ($context->getParameter('healthcare_costs_vehicle', 0.0)) +
               ($context->getParameter('healthcare_costs_other', 0.0));
    }

    /**
     * Calculate income supplementation needs for critical illness.
     */
    private function calculateIncomeSupplementation(CalculationContext $context): float
    {
        $monthlyLoss = ($context->getParameter('monthly_income_loss_client', 0.0)) +
                      ($context->getParameter('monthly_income_loss_spouse', 0.0)) +
                      ($context->getParameter('mortgage_payments', 0.0)) +
                      ($context->getParameter('other_debt_payments', 0.0)) +
                      ($context->getParameter('business_expenses', 0.0)) +
                      ($context->getParameter('other_income_supplement', 0.0));

        // Assume 24 months of supplementation (2 years typical recovery)
        return $monthlyLoss * 24;
    }

    /**
     * Calculate lump sum costs for critical illness.
     */
    private function calculateLumpSumCosts(CalculationContext $context): float
    {
        return ($context->getParameter('lump_sum_mortgage_payoff', 0.0)) +
               ($context->getParameter('lump_sum_debt_payoff', 0.0)) +
               ($context->getParameter('lump_sum_family_vacation', 0.0)) +
               ($context->getParameter('lump_sum_early_retirement', 0.0)) +
               ($context->getParameter('lump_sum_emergency_fund', 0.0));
    }

    /**
     * Calculate recommended coverage with safety margins.
     */
    private function calculateRecommendedCoverage(float $calculatedNeed, CalculationContext $context): float
    {
        $safetyMargin = $context->getParameter('safety_margin', 0.10); // 10% safety margin
        return $calculatedNeed * (1 + $safetyMargin);
    }

    /**
     * Get assumption value from assumption manager.
     */
    private function getAssumptionValue(string $key, ?string $clientId, float $default = 0.0): float
    {
        try {
            $value = $this->assumptionManager->getEffectiveValue($key, $clientId ?? '');
            return is_numeric($value) ? (float) $value : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
}