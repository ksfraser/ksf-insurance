# FR-002-002 InsuranceValueAnalysis.md: Insurance Value Analysis

**Related:** BR-002 InsurancePlanning, UC-002-001 InsuranceUseCases
**Engine:** `Ksfraser\Insurance\InsuranceValueAnalyzer`

## Description
Analyze in-force policy cash values and surrender values for planning.

## Primary actor
Advisor (or System on recalculation).

## Preconditions
Client data (income, debt, policies) available in FA.

## Main flow
1. Advisor invokes the calculation for the client.
2. System applies the engine and returns the result.

## Postconditions
Calculation result available for the client's insurance plan summary.
