# FR-002-003 PolicyComparison.md: Policy Comparison

**Related:** BR-002 InsurancePlanning, UC-002-001 InsuranceUseCases
**Engine:** `Ksfraser\Insurance\PolicyComparisonAnalyzer`

## Description
Compare competing insurance policies on cost and benefit characteristics.

## Primary actor
Advisor (or System on recalculation).

## Preconditions
Client data (income, debt, policies) available in FA.

## Main flow
1. Advisor invokes the calculation for the client.
2. System applies the engine and returns the result.

## Postconditions
Calculation result available for the client's insurance plan summary.
