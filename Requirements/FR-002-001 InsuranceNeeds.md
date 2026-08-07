# FR-002-001 InsuranceNeeds.md: Insurance Needs Calculation

**Related:** BR-002 InsurancePlanning, UC-002-001 InsuranceUseCases
**Engine:** `Ksfraser\Insurance\InsuranceNeedsCalculator`

## Description
Quantify the client's life/disability insurance need from income, debt, and obligations.

## Primary actor
Advisor (or System on recalculation).

## Preconditions
Client data (income, debt, policies) available in FA.

## Main flow
1. Advisor invokes the calculation for the client.
2. System applies the engine and returns the result.

## Postconditions
Calculation result available for the client's insurance plan summary.
