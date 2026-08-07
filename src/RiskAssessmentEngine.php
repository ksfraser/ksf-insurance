<?php

declare(strict_types=1);

namespace Ksfraser\Insurance;
use Ksfraser\Portfolio\PortfolioAnalyticsEngine;
use Ksfraser\Portfolio\DiversificationCalculator;

/**
 * Risk Assessment Engine
 *
 * Assesses portfolio risk including volatility, downside risk, and risk-adjusted returns.
 * Provides risk level classification and risk management recommendations.
 *
 * Single Responsibility: Assess and analyze portfolio risk metrics.
 *
 * @author AI Assistant
 * @version 1.0
 * @since 7 November 2025
 */
class RiskAssessmentEngine
{
    /**
     * Risk level classifications
     */
    public const RISK_LEVEL_VERY_LOW = 'very_low';
    public const RISK_LEVEL_LOW = 'low';
    public const RISK_LEVEL_MODERATE = 'moderate';
    public const RISK_LEVEL_HIGH = 'high';
    public const RISK_LEVEL_VERY_HIGH = 'very_high';

    /**
     * Risk metrics
     */
    public const METRIC_VOLATILITY = 'volatility';
    public const METRIC_SHARPE_RATIO = 'sharpe_ratio';
    public const METRIC_SORTINO_RATIO = 'sortino_ratio';
    public const METRIC_MAX_DRAWDOWN = 'max_drawdown';
    public const METRIC_VALUE_AT_RISK = 'value_at_risk';

    /**
     * Risk tolerance mappings
     */
    private const RISK_TOLERANCE_THRESHOLDS = [
        PortfolioAnalyticsEngine::RISK_CONSERVATIVE => [
            'max_volatility' => 0.08,
            'min_sharpe' => 0.5,
            'max_drawdown' => 0.10
        ],
        PortfolioAnalyticsEngine::RISK_MODERATE => [
            'max_volatility' => 0.12,
            'min_sharpe' => 0.8,
            'max_drawdown' => 0.15
        ],
        PortfolioAnalyticsEngine::RISK_AGGRESSIVE => [
            'max_volatility' => 0.18,
            'min_sharpe' => 1.0,
            'max_drawdown' => 0.25
        ]
    ];

    /**
     * Assess portfolio risk
     *
     * @param array $portfolio Portfolio assets
     * @param string $riskTolerance Investor risk tolerance
     * @param array|null $benchmark Benchmark data for comparison
     * @return array Risk assessment results
     */
    public function assessRisk(array $portfolio, string $riskTolerance, ?array $benchmark = null): array
    {
        $volatility = $this->calculateVolatility($portfolio);
        $sharpeRatio = $this->calculateSharpeRatio($portfolio, $benchmark);
        $sortinoRatio = $this->calculateSortinoRatio($portfolio, $benchmark);
        $maxDrawdown = $this->calculateMaxDrawdown($portfolio);
        $valueAtRisk = $this->calculateValueAtRisk($portfolio);

        $riskLevel = $this->classifyRiskLevel($volatility, $sharpeRatio, $maxDrawdown);
        $riskToleranceAssessment = $this->assessRiskTolerance($volatility, $sharpeRatio, $maxDrawdown, $riskTolerance);

        return [
            'risk_level' => $riskLevel,
            'risk_tolerance_assessment' => $riskToleranceAssessment,
            'metrics' => [
                self::METRIC_VOLATILITY => $volatility,
                self::METRIC_SHARPE_RATIO => $sharpeRatio,
                self::METRIC_SORTINO_RATIO => $sortinoRatio,
                self::METRIC_MAX_DRAWDOWN => $maxDrawdown,
                self::METRIC_VALUE_AT_RISK => $valueAtRisk
            ],
            'risk_contributions' => $this->calculateRiskContributions($portfolio),
            'stress_test_results' => $this->performStressTests($portfolio),
            'recommendations' => $this->generateRiskRecommendations(
                $riskLevel,
                $riskToleranceAssessment,
                $volatility,
                $sharpeRatio,
                $maxDrawdown
            )
        ];
    }

    /**
     * Calculate portfolio volatility (simplified)
     *
     * @param array $portfolio Portfolio assets
     * @return float Portfolio volatility (annualized)
     */
    private function calculateVolatility(array $portfolio): float
    {
        if (empty($portfolio)) {
            return 0.0;
        }

        // Simplified volatility calculation based on asset volatilities and correlations
        $totalVolatility = 0.0;
        $totalAllocation = array_sum(array_column($portfolio, 'allocation'));

        foreach ($portfolio as $asset) {
            $allocation = $asset['allocation'] / $totalAllocation;
            $assetVolatility = $asset['volatility'] ?? $this->estimateAssetVolatility($asset);
            $totalVolatility += $allocation * $allocation * $assetVolatility * $assetVolatility;
        }

        // Add covariance terms (simplified)
        for ($i = 0; $i < count($portfolio); $i++) {
            for ($j = $i + 1; $j < count($portfolio); $j++) {
                $alloc1 = $portfolio[$i]['allocation'] / $totalAllocation;
                $alloc2 = $portfolio[$j]['allocation'] / $totalAllocation;
                $vol1 = $portfolio[$i]['volatility'] ?? $this->estimateAssetVolatility($portfolio[$i]);
                $vol2 = $portfolio[$j]['volatility'] ?? $this->estimateAssetVolatility($portfolio[$j]);
                $correlation = $this->estimateCorrelation($portfolio[$i], $portfolio[$j]);

                $totalVolatility += 2 * $alloc1 * $alloc2 * $vol1 * $vol2 * $correlation;
            }
        }

        return sqrt(max(0, $totalVolatility));
    }

    /**
     * Calculate Sharpe ratio
     *
     * @param array $portfolio Portfolio assets
     * @param array|null $benchmark Benchmark data
     * @return float Sharpe ratio
     */
    private function calculateSharpeRatio(array $portfolio, ?array $benchmark = null): float
    {
        $portfolioReturn = $this->calculatePortfolioReturn($portfolio);
        $portfolioVolatility = $this->calculateVolatility($portfolio);

        if ($portfolioVolatility == 0.0) {
            return 0.0;
        }

        $riskFreeRate = 0.03; // Assume 3% risk-free rate
        $benchmarkReturn = $benchmark['return'] ?? $riskFreeRate;

        return ($portfolioReturn - $benchmarkReturn) / $portfolioVolatility;
    }

    /**
     * Calculate Sortino ratio (downside risk only)
     *
     * @param array $portfolio Portfolio assets
     * @param array|null $benchmark Benchmark data
     * @return float Sortino ratio
     */
    private function calculateSortinoRatio(array $portfolio, ?array $benchmark = null): float
    {
        $portfolioReturn = $this->calculatePortfolioReturn($portfolio);
        $downsideVolatility = $this->calculateDownsideVolatility($portfolio);

        if ($downsideVolatility == 0.0) {
            return 0.0;
        }

        $riskFreeRate = 0.03;
        $benchmarkReturn = $benchmark['return'] ?? $riskFreeRate;

        return ($portfolioReturn - $benchmarkReturn) / $downsideVolatility;
    }

    /**
     * Calculate maximum drawdown
     *
     * @param array $portfolio Portfolio assets
     * @return float Maximum drawdown percentage
     */
    private function calculateMaxDrawdown(array $portfolio): float
    {
        // Simplified max drawdown calculation
        // In real implementation, would use historical price data
        $maxDrawdown = 0.0;

        foreach ($portfolio as $asset) {
            $assetMaxDrawdown = $asset['max_drawdown'] ?? $this->estimateMaxDrawdown($asset);
            $allocation = $asset['allocation'] / array_sum(array_column($portfolio, 'allocation'));
            $maxDrawdown += $allocation * $assetMaxDrawdown;
        }

        return $maxDrawdown;
    }

    /**
     * Calculate Value at Risk (VaR)
     *
     * @param array $portfolio Portfolio assets
     * @param float $confidence Confidence level (default 95%)
     * @return float Value at Risk percentage
     */
    private function calculateValueAtRisk(array $portfolio, float $confidence = 0.95): float
    {
        $volatility = $this->calculateVolatility($portfolio);
        $zScore = $this->getZScore($confidence);

        // Simplified VaR calculation assuming normal distribution
        return $zScore * $volatility * sqrt(252); // Annualized for 252 trading days
    }

    /**
     * Classify overall risk level
     *
     * @param float $volatility Portfolio volatility
     * @param float $sharpeRatio Sharpe ratio
     * @param float $maxDrawdown Maximum drawdown
     * @return string Risk level classification
     */
    private function classifyRiskLevel(float $volatility, float $sharpeRatio, float $maxDrawdown): string
    {
        $riskScore = 0;

        // Volatility scoring
        if ($volatility > 0.20) $riskScore += 3;
        elseif ($volatility > 0.15) $riskScore += 2;
        elseif ($volatility > 0.10) $riskScore += 1;

        // Sharpe ratio scoring (lower is riskier)
        if ($sharpeRatio < 0.5) $riskScore += 3;
        elseif ($sharpeRatio < 0.8) $riskScore += 2;
        elseif ($sharpeRatio < 1.0) $riskScore += 1;

        // Max drawdown scoring
        if ($maxDrawdown > 0.25) $riskScore += 3;
        elseif ($maxDrawdown > 0.20) $riskScore += 2;
        elseif ($maxDrawdown > 0.15) $riskScore += 1;

        if ($riskScore >= 7) return self::RISK_LEVEL_VERY_HIGH;
        if ($riskScore >= 5) return self::RISK_LEVEL_HIGH;
        if ($riskScore >= 3) return self::RISK_LEVEL_MODERATE;
        if ($riskScore >= 1) return self::RISK_LEVEL_LOW;
        return self::RISK_LEVEL_VERY_LOW;
    }

    /**
     * Assess if portfolio matches risk tolerance
     *
     * @param float $volatility Portfolio volatility
     * @param float $sharpeRatio Sharpe ratio
     * @param float $maxDrawdown Maximum drawdown
     * @param string $riskTolerance Risk tolerance level
     * @return array Risk tolerance assessment
     */
    private function assessRiskTolerance(
        float $volatility,
        float $sharpeRatio,
        float $maxDrawdown,
        string $riskTolerance
    ): array {
        $thresholds = self::RISK_TOLERANCE_THRESHOLDS[$riskTolerance] ?? self::RISK_TOLERANCE_THRESHOLDS[PortfolioAnalyticsEngine::RISK_MODERATE];

        $volatilityOk = $volatility <= $thresholds['max_volatility'];
        $sharpeOk = $sharpeRatio >= $thresholds['min_sharpe'];
        $drawdownOk = $maxDrawdown <= $thresholds['max_drawdown'];

        $overallMatch = $volatilityOk && $sharpeOk && $drawdownOk;

        return [
            'matches_tolerance' => $overallMatch,
            'volatility_within_tolerance' => $volatilityOk,
            'sharpe_within_tolerance' => $sharpeOk,
            'drawdown_within_tolerance' => $drawdownOk,
            'thresholds' => $thresholds
        ];
    }

    /**
     * Calculate risk contribution of each asset
     *
     * @param array $portfolio Portfolio assets
     * @return array Risk contributions by asset
     */
    private function calculateRiskContributions(array $portfolio): array
    {
        $totalAllocation = array_sum(array_column($portfolio, 'allocation'));
        $portfolioVolatility = $this->calculateVolatility($portfolio);

        $contributions = [];
        foreach ($portfolio as $asset) {
            $allocation = $asset['allocation'] / $totalAllocation;
            $assetVolatility = $asset['volatility'] ?? $this->estimateAssetVolatility($asset);

            // Simplified risk contribution calculation
            $marginalContribution = $allocation * $assetVolatility * $assetVolatility / $portfolioVolatility;
            $contributions[] = [
                'asset_name' => $asset['name'] ?? 'Unknown Asset',
                'risk_contribution' => $marginalContribution,
                'contribution_percent' => $marginalContribution / $portfolioVolatility * 100.0
            ];
        }

        return $contributions;
    }

    /**
     * Perform stress tests on portfolio
     *
     * @param array $portfolio Portfolio assets
     * @return array Stress test results
     */
    private function performStressTests(array $portfolio): array
    {
        // Simplified stress test scenarios
        $scenarios = [
            'market_crash' => ['shock' => -0.20, 'description' => '20% market decline'],
            'recession' => ['shock' => -0.15, 'description' => '15% economic downturn'],
            'inflation_spike' => ['shock' => -0.10, 'description' => '10% inflation increase'],
            'interest_rate_hike' => ['shock' => -0.08, 'description' => '8% interest rate increase']
        ];

        $results = [];
        foreach ($scenarios as $scenario => $config) {
            $impact = $this->calculateScenarioImpact($portfolio, $config['shock']);
            $results[$scenario] = [
                'description' => $config['description'],
                'shock_percentage' => $config['shock'] * 100,
                'portfolio_impact' => $impact,
                'recovery_time_months' => $this->estimateRecoveryTime($impact)
            ];
        }

        return $results;
    }

    /**
     * Generate risk management recommendations
     *
     * @param string $riskLevel Current risk level
     * @param array $toleranceAssessment Risk tolerance assessment
     * @param float $volatility Portfolio volatility
     * @param float $sharpeRatio Sharpe ratio
     * @param float $maxDrawdown Maximum drawdown
     * @return array Risk recommendations
     */
    private function generateRiskRecommendations(
        string $riskLevel,
        array $toleranceAssessment,
        float $volatility,
        float $sharpeRatio,
        float $maxDrawdown
    ): array {
        $recommendations = [];

        if (!$toleranceAssessment['matches_tolerance']) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'risk_tolerance_adjustment',
                'message' => 'Portfolio risk does not match stated risk tolerance. Consider rebalancing.',
                'action_items' => [
                    'Reduce equity exposure for conservative investors',
                    'Increase diversification to lower volatility',
                    'Add fixed income for stability'
                ]
            ];
        }

        if ($riskLevel === self::RISK_LEVEL_VERY_HIGH) {
            $recommendations[] = [
                'priority' => 'high',
                'type' => 'risk_reduction',
                'message' => 'Portfolio exhibits very high risk. Immediate risk reduction recommended.',
                'action_items' => [
                    'Reduce allocation to volatile assets',
                    'Increase cash or fixed income holdings',
                    'Implement stop-loss orders',
                    'Consider professional risk management consultation'
                ]
            ];
        }

        if ($sharpeRatio < 0.5) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'return_optimization',
                'message' => 'Risk-adjusted returns are suboptimal. Consider optimizing asset selection.',
                'action_items' => [
                    'Replace underperforming assets',
                    'Add assets with better risk-adjusted returns',
                    'Review investment strategy'
                ]
            ];
        }

        if ($maxDrawdown > 0.20) {
            $recommendations[] = [
                'priority' => 'medium',
                'type' => 'drawdown_protection',
                'message' => 'Portfolio vulnerable to significant losses. Consider downside protection.',
                'action_items' => [
                    'Add put options or collars',
                    'Implement trailing stop losses',
                    'Diversify into uncorrelated assets'
                ]
            ];
        }

        return $recommendations;
    }

    /**
     * Estimate asset volatility based on asset class
     *
     * @param array $asset Asset data
     * @return float Estimated volatility
     */
    private function estimateAssetVolatility(array $asset): float
    {
        $assetClass = $asset['asset_class'] ?? DiversificationCalculator::ASSET_CLASS_EQUITY;

        return match($assetClass) {
            DiversificationCalculator::ASSET_CLASS_EQUITY => 0.15,
            DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => 0.08,
            DiversificationCalculator::ASSET_CLASS_ALTERNATIVE => 0.12,
            DiversificationCalculator::ASSET_CLASS_CASH => 0.02,
            default => 0.15
        };
    }

    /**
     * Estimate maximum drawdown based on asset class
     *
     * @param array $asset Asset data
     * @return float Estimated max drawdown
     */
    private function estimateMaxDrawdown(array $asset): float
    {
        $assetClass = $asset['asset_class'] ?? DiversificationCalculator::ASSET_CLASS_EQUITY;

        return match($assetClass) {
            DiversificationCalculator::ASSET_CLASS_EQUITY => 0.30,
            DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => 0.15,
            DiversificationCalculator::ASSET_CLASS_ALTERNATIVE => 0.25,
            DiversificationCalculator::ASSET_CLASS_CASH => 0.01,
            default => 0.30
        };
    }

    /**
     * Calculate portfolio return (simplified)
     *
     * @param array $portfolio Portfolio assets
     * @return float Portfolio return
     */
    private function calculatePortfolioReturn(array $portfolio): float
    {
        $totalAllocation = array_sum(array_column($portfolio, 'allocation'));
        $weightedReturn = 0.0;

        foreach ($portfolio as $asset) {
            $allocation = $asset['allocation'] / $totalAllocation;
            $return = $asset['expected_return'] ?? $this->estimateAssetReturn($asset);
            $weightedReturn += $allocation * $return;
        }

        return $weightedReturn;
    }

    /**
     * Estimate asset return based on asset class
     *
     * @param array $asset Asset data
     * @return float Estimated return
     */
    private function estimateAssetReturn(array $asset): float
    {
        $assetClass = $asset['asset_class'] ?? DiversificationCalculator::ASSET_CLASS_EQUITY;

        return match($assetClass) {
            DiversificationCalculator::ASSET_CLASS_EQUITY => 0.08,
            DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => 0.04,
            DiversificationCalculator::ASSET_CLASS_ALTERNATIVE => 0.06,
            DiversificationCalculator::ASSET_CLASS_CASH => 0.03,
            default => 0.08
        };
    }

    /**
     * Calculate downside volatility (Sortino ratio denominator)
     *
     * @param array $portfolio Portfolio assets
     * @return float Downside volatility
     */
    private function calculateDownsideVolatility(array $portfolio): float
    {
        // Simplified: use 70% of total volatility as downside volatility
        return $this->calculateVolatility($portfolio) * 0.7;
    }

    /**
     * Estimate correlation between assets
     *
     * @param array $asset1 First asset
     * @param array $asset2 Second asset
     * @return float Correlation coefficient
     */
    private function estimateCorrelation(array $asset1, array $asset2): float
    {
        $class1 = $asset1['asset_class'] ?? DiversificationCalculator::ASSET_CLASS_EQUITY;
        $class2 = $asset2['asset_class'] ?? DiversificationCalculator::ASSET_CLASS_EQUITY;

        // Simplified correlation matrix
        $correlations = [
            DiversificationCalculator::ASSET_CLASS_EQUITY => [
                DiversificationCalculator::ASSET_CLASS_EQUITY => 0.8,
                DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => 0.2,
                DiversificationCalculator::ASSET_CLASS_ALTERNATIVE => 0.3,
                DiversificationCalculator::ASSET_CLASS_CASH => 0.0
            ],
            DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => [
                DiversificationCalculator::ASSET_CLASS_EQUITY => 0.2,
                DiversificationCalculator::ASSET_CLASS_FIXED_INCOME => 0.7,
                DiversificationCalculator::ASSET_CLASS_ALTERNATIVE => 0.4,
                DiversificationCalculator::ASSET_CLASS_CASH => 0.1
            ]
        ];

        return $correlations[$class1][$class2] ?? 0.5;
    }

    /**
     * Calculate impact of stress test scenario
     *
     * @param array $portfolio Portfolio assets
     * @param float $shock Shock percentage
     * @return float Portfolio impact percentage
     */
    private function calculateScenarioImpact(array $portfolio, float $shock): float
    {
        // Simplified impact calculation
        $totalAllocation = array_sum(array_column($portfolio, 'allocation'));
        $impact = 0.0;

        foreach ($portfolio as $asset) {
            $allocation = $asset['allocation'] / $totalAllocation;
            $assetShock = $shock * ($asset['shock_sensitivity'] ?? 1.0);
            $impact += $allocation * $assetShock;
        }

        return $impact;
    }

    /**
     * Estimate recovery time after stress event
     *
     * @param float $impact Impact percentage
     * @return int Estimated recovery time in months
     */
    private function estimateRecoveryTime(float $impact): int
    {
        $absImpact = abs($impact);
        if ($absImpact > 0.25) return 24; // 2 years
        if ($absImpact > 0.20) return 18; // 1.5 years
        if ($absImpact > 0.15) return 12; // 1 year
        if ($absImpact > 0.10) return 8;  // 8 months
        if ($absImpact > 0.05) return 4;  // 4 months
        return 2; // 2 months
    }

    /**
     * Get Z-score for confidence level
     *
     * @param float $confidence Confidence level (0-1)
     * @return float Z-score
     */
    private function getZScore(float $confidence): float
    {
        // Simplified Z-scores for common confidence levels
        if ($confidence >= 0.99) return 2.33;
        if ($confidence >= 0.95) return 1.65;
        if ($confidence >= 0.90) return 1.28;
        return 1.65; // Default to 95%
    }
}