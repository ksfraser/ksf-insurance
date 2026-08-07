<?php

declare(strict_types=1);

namespace Tests\Unit\Calculations;

use Ksfraser\Insurance\;
use Ksfraser\ModulesCommon\;
use Ksfraser\ModulesCommon\;
use Ksfraser\ModulesCommon\;
use KSFII\AssumptionManagement\AssumptionManager;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use Ksfraser\Insurance\InsuranceNeedsCalculator;
use Ksfraser\ModulesCommon\CalculationContext;

/**
 * Test for the InsuranceNeedsCalculator class.
 */
class InsuranceNeedsCalculatorTest extends TestCase
{
    private AssumptionManager $assumptionManager;
    private InsuranceNeedsCalculator $calculator;

    protected function setUp(): void
    {
        $this->assumptionManager = $this->createMock(AssumptionManager::class);
        $this->calculator = new InsuranceNeedsCalculator($this->assumptionManager);
    }

    public function testGetCalculationType(): void
    {
        $this->assertEquals('insurance_needs_analysis', $this->calculator->getCalculationType());
    }

    public function testValidateValidContext(): void
    {
        $context = new CalculationContext(
            'insurance_needs_analysis',
            ['analysis_type' => 'life_insurance']
        );

        $result = $this->calculator->validate($context);

        $this->assertTrue($result->isValid);
        $this->assertEmpty($result->errors);
    }

    public function testValidateMissingRequiredParameter(): void
    {
        $context = new CalculationContext(
            'insurance_needs_analysis',
            [] // Missing analysis_type
        );

        $result = $this->calculator->validate($context);

        $this->assertFalse($result->isValid);
        $this->assertContains('Required parameter missing: analysis_type', $result->errors);
    }

    public function testValidateInvalidAnalysisType(): void
    {
        $context = new CalculationContext(
            'insurance_needs_analysis',
            ['analysis_type' => 'invalid_type']
        );

        $result = $this->calculator->validate($context);

        $this->assertFalse($result->isValid);
        $this->assertContains('Invalid analysis type: invalid_type', $result->errors);
    }

    public function testCalculateLifeInsuranceNeedsApproach(): void
    {
        // Mock assumption manager
        $this->assumptionManager->expects($this->any())
            ->method('getEffectiveValue')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'inflation_rate' => 0.02,
                    'income_replacement_ratio' => 0.70,
                    default => 0.0
                };
            });

        $context = new CalculationContext(
            'insurance_needs_analysis',
            [
                'analysis_type' => 'life_insurance',
                'methodology' => 'needs_approach',
                'mortgage_balance' => 300000,
                'other_debt' => 50000,
                'final_expenses' => 15000,
                'family_income_need' => 80000,
                'survivor_pension_income' => 20000,
                'income_replacement_years' => 20,
                'expected_rate_of_return' => 0.05,
                'savings_balance' => 50000,
                'current_life_insurance' => 100000,
                'safety_margin' => 0.10
            ]
        );

        $result = $this->calculator->calculate($context);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals('insurance_needs_analysis', $result->calculationType);
        $this->assertIsArray($result->primaryResult);

        $lifeInsurance = $result->primaryResult;
        $this->assertEquals('needs_approach', $lifeInsurance['methodology']);
        $this->assertEquals(365000, $lifeInsurance['debt_final_expenses']); // 300k + 50k + 15k
        $this->assertGreaterThan(0, $lifeInsurance['annual_income_needs']);
        $this->assertEquals(150000, $lifeInsurance['total_assets_coverage']); // 50k + 100k
        $this->assertGreaterThan(0, $lifeInsurance['calculated_need']);
        $this->assertGreaterThan($lifeInsurance['calculated_need'], $lifeInsurance['recommended_coverage']);
    }

    public function testCalculateCriticalIllnessNeeds(): void
    {
        $context = new CalculationContext(
            'insurance_needs_analysis',
            [
                'analysis_type' => 'critical_illness',
                'healthcare_costs_medication' => 12000,
                'healthcare_costs_hospital' => 25000,
                'healthcare_costs_homecare' => 30000,
                'monthly_income_loss_client' => 5000,
                'mortgage_payments' => 2000,
                'lump_sum_mortgage_payoff' => 200000,
                'lump_sum_emergency_fund' => 50000,
                'safety_margin' => 0.10
            ]
        );

        $result = $this->calculator->calculate($context);

        $this->assertTrue($result->isSuccessful());
        $this->assertIsArray($result->primaryResult);

        $ci = $result->primaryResult;
        $this->assertEquals(67000, $ci['healthcare_costs']); // 12k + 25k + 30k
        $this->assertEquals(168000, $ci['income_supplementation']); // (5k + 2k) * 24 months
        $this->assertEquals(250000, $ci['lump_sum_costs']); // 200k + 50k
        $this->assertEquals(485000, $ci['calculated_need']); // 67k + 168k + 250k
        $this->assertEquals(533500, $ci['recommended_coverage']); // 485k * 1.1
    }

    public function testCalculateCombinedNeeds(): void
    {
        // Mock assumption manager
        $this->assumptionManager->expects($this->any())
            ->method('getEffectiveValue')
            ->willReturn(0.02); // inflation_rate

        $context = new CalculationContext(
            'insurance_needs_analysis',
            [
                'analysis_type' => 'combined',
                'methodology' => 'needs_approach',
                'mortgage_balance' => 200000,
                'final_expenses' => 10000,
                'family_income_need' => 60000,
                'income_replacement_years' => 15,
                'savings_balance' => 30000,
                'healthcare_costs_medication' => 10000,
                'monthly_income_loss_client' => 3000,
                'lump_sum_mortgage_payoff' => 150000,
                'safety_margin' => 0.05
            ]
        );

        $result = $this->calculator->calculate($context);

        $this->assertTrue($result->isSuccessful());
        $this->assertIsArray($result->primaryResult);

        $combined = $result->primaryResult;
        $this->assertGreaterThan(0, $combined['life_insurance_need']);
        $this->assertGreaterThan(0, $combined['critical_illness_need']);
        $this->assertGreaterThan(0, $combined['combined_calculated_need']);
        $this->assertGreaterThan($combined['combined_calculated_need'], $combined['recommended_coverage']);
    }

    public function testCalculateWithClientAssumptions(): void
    {
        $clientId = 'client123';

        // Mock assumption manager to return client-specific values
        $this->assumptionManager->expects($this->any())
            ->method('getEffectiveValue')
            ->with($this->anything(), $clientId)
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'income_replacement_ratio' => 0.80, // Higher replacement ratio for this client
                    'inflation_rate' => 0.025,
                    default => 0.0
                };
            });

        $context = new CalculationContext(
            'insurance_needs_analysis',
            [
                'analysis_type' => 'life_insurance',
                'methodology' => 'income_replacement',
                'annual_income' => 100000,
                'income_replacement_years' => 20,
                'mortgage_balance' => 250000,
                'savings_balance' => 50000
            ],
            [],
            $clientId
        );

        $result = $this->calculator->calculate($context);

        $this->assertTrue($result->isSuccessful());

        // Should use client-specific 80% replacement ratio
        $lifeInsurance = $result->primaryResult;
        $expectedReplacement = (100000 * 0.80 * 20) + 250000 - 50000; // Should be positive
        $this->assertGreaterThan(0, $lifeInsurance['calculated_need']);
    }

    public function testGetRequiredParameters(): void
    {
        $required = $this->calculator->getRequiredParameters();

        $this->assertArrayHasKey('analysis_type', $required);
        $this->assertTrue($required['analysis_type']->required);
        $this->assertEquals('string', $required['analysis_type']->type);
    }

    public function testGetOptionalParameters(): void
    {
        $optional = $this->calculator->getOptionalParameters();

        $this->assertArrayHasKey('annual_income', $optional);
        $this->assertFalse($optional['annual_income']->required);
        $this->assertEquals('float', $optional['annual_income']->type);
        $this->assertEquals(0.0, $optional['annual_income']->defaultValue);

        $this->assertArrayHasKey('methodology', $optional);
        $this->assertEquals('needs_approach', $optional['methodology']->defaultValue);

        $this->assertArrayHasKey('expected_rate_of_return', $optional);
        $this->assertEquals(0.05, $optional['expected_rate_of_return']->defaultValue);
    }

    public function testCalculateWithZeroValues(): void
    {
        $context = new CalculationContext(
            'insurance_needs_analysis',
            ['analysis_type' => 'life_insurance']
        );

        $result = $this->calculator->calculate($context);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals(0, $result->primaryResult['calculated_need']);
        $this->assertEquals(0, $result->primaryResult['recommended_coverage']);
    }

    public function testCalculateErrorHandling(): void
    {
        // Test with invalid methodology
        $context = new CalculationContext(
            'insurance_needs_analysis',
            [
                'analysis_type' => 'life_insurance',
                'methodology' => 'invalid_methodology'
            ]
        );

        $result = $this->calculator->calculate($context);

        $this->assertFalse($result->isSuccessful());
        $this->assertNotEmpty($result->validationResult->errors);
    }
}