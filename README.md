# ksf_insurance

Insurance calculation engines — business logic for insurance needs, valuation, and
policy comparison. Part of the `ksf_estate` / `ksf_insurance` / `ksf_retirement` /
`ksf_business_valuation` / `ksf_portfolio` / `ksf_recommendation` family of
calculation packages.

- Namespace: `Ksfraser\Insurance`
- Shared framework (engine contract, context/result, validation): `ksfraser/ksf_modules_common` (`Ksfraser\ModulesCommon`)
- Exceptions: `ksfraser/exceptions` (`Ksfraser\Exceptions\Domain\CalculationException`)

## Engines
| Engine | Purpose |
|--------|---------|
| `InsuranceNeedsCalculator` | Quantify life/disability insurance need |
| `InsuranceValueAnalyzer` | Analyze in-force policy cash/surrender values |
| `PolicyComparisonAnalyzer` | Compare competing policies |
| `PremiumProjectionCalculator` | Project future premium outlays |
| `CostPerThousandCalculator` | Cost per $1,000 of coverage |
| `CashValueAccumulator` | Accumulate/project policy cash values |
| `RiskAssessmentEngine` | Assess client risk profile |

## Known dependency
`InsuranceNeedsCalculator` references `KSFII\AssumptionManagement\AssumptionManager`
(still in ksfii_app). That module will be extracted to its own package
(`ksfraser/ksf_assumption_management`) in a later step; until then ksf_insurance is
not fully runnable in isolation.

## Requirements (BABOK)
- `Requirements/BR-002 InsurancePlanning.md`
- `Requirements/FR-002-001..007 *.md`
- `Requirements/UC-002-001 InsuranceUseCases.md`

## Status
Scaffold — engines extracted and namespaced; not yet wired to a live FA install.
