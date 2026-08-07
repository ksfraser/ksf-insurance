# FR-002-005 CostPerThousand.md: Cost per Thousand

**Related:** BR-002 InsurancePlanning, UC-002-001 InsuranceUseCases
**Engine:** `Ksfraser\Insurance\CostPerThousandCalculator`

## Description
Compute insurance cost per $1,000 of coverage for comparison shopping.

## Primary actor
Advisor (or System on recalculation).

## Preconditions
Client data (income, debt, policies) available in FA.

## Main flow
1. Advisor invokes the calculation for the client.
2. System applies the engine and returns the result.

## Postconditions
Calculation result available for the client's insurance plan summary.
