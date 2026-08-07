<?php

declare(strict_types=1);

namespace Ksfraser\Insurance;

use KSFII\AssumptionManagement\AssumptionManager;
use DateTimeImmutable;
use Ksfraser\ModulesCommon\ParameterDefinition;
use Ksfraser\ModulesCommon\ValidationResult;
use Ksfraser\ModulesCommon\CalculationResult;
use Ksfraser\ModulesCommon\CalculationEngineInterface;
use Ksfraser\ModulesCommon\CalculationContext;

/**
 * Insurance Value Analysis Engine
 *
 * Implements comprehensive insurance value analysis compatible with
 * Canada Life valueOfInsurance.xlsm functionality.
 *
 * This engine analyzes the lifetime value of insurance policies by:
 * - Projecting lifetime premium payments
 * - Modeling cash value accumulation over time
 * - Calculating cost-benefit ratios
 * - Analyzing tax implications
 * - Comparing against alternative investments
 */
class InsuranceValueAnalyzer implements CalculationEngineInterface
{
    private AssumptionManager $assumptionManager;
    private PremiumProjectionCalculator $premiumCalculator;
    private CashValueAccumulator $cashValueCalculator;
    private CostPerThousandCalculator $costCalculator;

    public function __construct(AssumptionManager $assumptionManager)
    {
        $this->assumptionManager = $assumptionManager;
        $this->premiumCalculator = new PremiumProjectionCalculator();
        $this->cashValueCalculator = new CashValueAccumulator($assumptionManager);
        $this->costCalculator = new CostPerThousandCalculator($assumptionManager);
    }

    /**
     * Analyze the lifetime value of an insurance policy.
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
            $policyType = $context->getParameter('policy_type', 'whole_life');
            $analysisType = $context->getParameter('analysis_type', 'comprehensive');

            // Get base policy information
            $currentAge = (int) $context->getParameter('current_age');
            $premiumAmount = (float) $context->getParameter('annual_premium');
            $policyDuration = (int) $context->getParameter('policy_duration_years', 30);
            $cashValue = (float) $context->getParameter('current_cash_value', 0.0);

            $results = [
                'policy_summary' => [
                    'policy_type' => $policyType,
                    'current_age' => $currentAge,
                    'annual_premium' => $premiumAmount,
                    'policy_duration' => $policyDuration,
                    'current_cash_value' => $cashValue
                ]
            ];

            // Perform different types of analysis
            switch ($analysisType) {
                case 'comprehensive':
                    $results = array_merge($results, $this->performComprehensiveAnalysis($context));
                    break;
                case 'premium_projection':
                    $results['premium_analysis'] = $this->premiumCalculator->calculateLifetimePremiums($context);
                    break;
                case 'cash_value':
                    $results['cash_value_analysis'] = $this->cashValueCalculator->projectCashValue($context);
                    break;
                case 'cost_benefit':
                    $results['cost_benefit_analysis'] = $this->analyzeCostBenefit($context);
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
                    'policy_type' => $policyType,
                    'analysis_type' => $analysisType,
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
     * Perform comprehensive insurance value analysis.
     */
    private function performComprehensiveAnalysis(CalculationContext $context): array
    {
        $premiumAnalysis = $this->premiumCalculator->calculateLifetimePremiums($context);
        $cashValueAnalysis = $this->cashValueCalculator->projectCashValue($context);
        $costBenefitAnalysis = $this->analyzeCostBenefit($context);
        $taxAnalysis = $this->analyzeTaxImplications($context);
        $investmentComparison = $this->compareWithInvestments($context);

        return [
            'premium_analysis' => $premiumAnalysis,
            'cash_value_analysis' => $cashValueAnalysis,
            'cost_benefit_analysis' => $costBenefitAnalysis,
            'tax_analysis' => $taxAnalysis,
            'investment_comparison' => $investmentComparison,
            'summary_metrics' => $this->calculateSummaryMetrics($premiumAnalysis, $cashValueAnalysis, $costBenefitAnalysis)
        ];
    }

    /**
     * Analyze cost-benefit ratio of the insurance policy.
     */
    private function analyzeCostBenefit(CalculationContext $context): array
    {
        return $this->costCalculator->calculateCostPerThousand($context);
    }

    /**
     * Analyze tax implications of the policy.
     */
    private function analyzeTaxImplications(CalculationContext $context): array
    {
        // Get tax assumptions
        $taxRate = $this->assumptionManager->getEffectiveValue('tax.marginal_rate') ?? 0.35;
        $capitalGainsRate = $this->assumptionManager->getEffectiveValue('tax.capital_gains_rate') ?? 0.25;

        $cashValue = (float) $context->getParameter('current_cash_value', 0.0);
        $annualPremium = (float) $context->getParameter('annual_premium');

        // Calculate tax-deferred growth benefit
        $taxDeferredBenefit = $cashValue * $taxRate;

        // Calculate premium tax deductibility (if applicable)
        $premiumTaxBenefit = $annualPremium * $taxRate * 0.5; // Assuming 50% deductibility for some policies

        return [
            'tax_deferred_growth_benefit' => $taxDeferredBenefit,
            'annual_premium_tax_benefit' => $premiumTaxBenefit,
            'effective_tax_rate' => $taxRate,
            'capital_gains_rate' => $capitalGainsRate,
            'net_tax_advantage' => $taxDeferredBenefit + $premiumTaxBenefit
        ];
    }

    /**
     * Compare insurance policy returns with alternative investments.
     */
    private function compareWithInvestments(CalculationContext $context): array
    {
        $annualPremium = (float) $context->getParameter('annual_premium');
        $cashValue = (float) $context->getParameter('current_cash_value', 0.0);
        $policyDuration = (int) $context->getParameter('policy_duration_years', 30);

        // Get investment return assumptions
        $stockReturn = $this->assumptionManager->getEffectiveValue('investment.stock_return') ?? 0.08;
        $bondReturn = $this->assumptionManager->getEffectiveValue('investment.bond_return') ?? 0.04;
        $cashReturn = $this->assumptionManager->getEffectiveValue('investment.cash_return') ?? 0.02;

        // Calculate alternative investment values
        $stockValue = $this->calculateFutureValue($annualPremium, $stockReturn, $policyDuration);
        $bondValue = $this->calculateFutureValue($annualPremium, $bondReturn, $policyDuration);
        $cashValueAlt = $this->calculateFutureValue($annualPremium, $cashReturn, $policyDuration);

        return [
            'insurance_cash_value' => $cashValue,
            'alternative_investments' => [
                'stock_portfolio_value' => $stockValue,
                'bond_portfolio_value' => $bondValue,
                'cash_equivalent_value' => $cashValueAlt
            ],
            'opportunity_cost' => [
                'vs_stocks' => $stockValue - $cashValue,
                'vs_bonds' => $bondValue - $cashValue,
                'vs_cash' => $cashValueAlt - $cashValue
            ]
        ];
    }

    /**
     * Calculate summary metrics for the analysis.
     */
    private function calculateSummaryMetrics(array $premiumAnalysis, array $cashValueAnalysis, array $costBenefitAnalysis): array
    {
        $totalPremiums = $premiumAnalysis['total_lifetime_premiums'] ?? 0;
        $finalCashValue = $cashValueAnalysis['projected_values'][count($cashValueAnalysis['projected_values']) - 1]['cash_value'] ?? 0;
        $costPerThousand = $costBenefitAnalysis['cost_per_thousand'] ?? 0;

        $netValue = $finalCashValue - $totalPremiums;
        $returnOnInvestment = $totalPremiums > 0 ? ($netValue / $totalPremiums) * 100 : 0;

        return [
            'total_lifetime_premiums' => $totalPremiums,
            'final_projected_cash_value' => $finalCashValue,
            'net_value_created' => $netValue,
            'return_on_investment_percent' => $returnOnInvestment,
            'cost_per_thousand_dollars' => $costPerThousand,
            'value_rating' => $this->calculateValueRating($returnOnInvestment, $costPerThousand)
        ];
    }

    /**
     * Calculate future value of an annuity (investment alternative).
     */
    private function calculateFutureValue(float $annualPayment, float $annualRate, int $years): float
    {
        if ($annualRate == 0) {
            return $annualPayment * $years;
        }

        return $annualPayment * ((pow(1 + $annualRate, $years) - 1) / $annualRate);
    }

    /**
     * Calculate a qualitative value rating.
     */
    private function calculateValueRating(float $roi, float $costPerThousand): string
    {
        if ($roi > 50 && $costPerThousand < 50) {
            return 'Excellent';
        } elseif ($roi > 25 && $costPerThousand < 75) {
            return 'Good';
        } elseif ($roi > 0 && $costPerThousand < 100) {
            return 'Fair';
        } elseif ($roi > -25) {
            return 'Poor';
        } else {
            return 'Very Poor';
        }
    }

    /**
     * Validate the calculation context.
     */
    public function validate(CalculationContext $context): ValidationResult
    {
        $errors = [];
        $warnings = [];

        // Check required parameters
        $requiredParams = $this->getRequiredParameters();
        foreach ($requiredParams as $paramName => $definition) {
            if ($context->getParameter($paramName) === null) {
                $errors[] = "Required parameter missing: {$paramName}";
            }
        }

        // Validate policy type
        $policyType = $context->getParameter('policy_type', 'whole_life');
        $validTypes = ['whole_life', 'term_life', 'universal_life', 'variable_life'];
        if (!in_array($policyType, $validTypes)) {
            $errors[] = "Invalid policy type: {$policyType}. Must be one of: " . implode(', ', $validTypes);
        }

        // Validate analysis type
        $analysisType = $context->getParameter('analysis_type', 'comprehensive');
        $validAnalysisTypes = ['comprehensive', 'premium_projection', 'cash_value', 'cost_benefit'];
        if (!in_array($analysisType, $validAnalysisTypes)) {
            $errors[] = "Invalid analysis type: {$analysisType}. Must be one of: " . implode(', ', $validAnalysisTypes);
        }

        // Validate numeric parameters
        $numericParams = ['current_age', 'annual_premium', 'policy_duration_years', 'current_cash_value'];
        foreach ($numericParams as $param) {
            $value = $context->getParameter($param);
            if ($value !== null && !is_numeric($value)) {
                $errors[] = "Parameter {$param} must be numeric";
            } elseif ($value !== null && $value < 0) {
                $errors[] = "Parameter {$param} cannot be negative";
            }
        }

        // Validate age range
        $age = (int) $context->getParameter('current_age', 0);
        if ($age > 0 && ($age < 18 || $age > 100)) {
            $warnings[] = "Age {$age} is outside typical insurance analysis range (18-100)";
        }

        return new ValidationResult(count($errors) === 0, $errors, $warnings);
    }

    /**
     * Get the calculation type identifier.
     */
    public function getCalculationType(): string
    {
        return 'insurance_value_analysis';
    }

    /**
     * Get the list of required input parameters.
     */
    public function getRequiredParameters(): array
    {
        return [
            'current_age' => new ParameterDefinition(
                'current_age',
                'integer',
                'Current age of the insured person',
                true,
                18,
                100
            ),
            'annual_premium' => new ParameterDefinition(
                'annual_premium',
                'float',
                'Annual premium amount in dollars',
                true,
                100.0,
                100000.0
            )
        ];
    }

    /**
     * Get the list of optional input parameters.
     */
    public function getOptionalParameters(): array
    {
        return [
            'policy_type' => new ParameterDefinition(
                'policy_type',
                'string',
                'Type of insurance policy',
                false,
                'whole_life',
                null,
                ['whole_life', 'term_life', 'universal_life', 'variable_life']
            ),
            'analysis_type' => new ParameterDefinition(
                'analysis_type',
                'string',
                'Type of analysis to perform',
                false,
                'comprehensive',
                null,
                ['comprehensive', 'premium_projection', 'cash_value', 'cost_benefit']
            ),
            'policy_duration_years' => new ParameterDefinition(
                'policy_duration_years',
                'integer',
                'Number of years the policy has been in force',
                false,
                30,
                1,
                50
            ),
            'current_cash_value' => new ParameterDefinition(
                'current_cash_value',
                'float',
                'Current cash value of the policy',
                false,
                0.0,
                0.0,
                1000000.0
            )
        ];
    }
}