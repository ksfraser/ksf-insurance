# FR-002-006 CashValueAccumulation.md: Cash Value Accumulation

**Related:** BR-002 InsurancePlanning, UC-002-001 InsuranceUseCases
**Engine:** `Ksfraser\Insurance\CashValueAccumulator`

## Description
Accumulate and project policy cash values over time.

## Primary actor
Advisor (or System on recalculation).

## Preconditions
Client data (income, debt, policies) available in FA.

## Main flow
1. Advisor invokes the calculation for the client.
2. System applies the engine and returns the result.

## Postconditions
Calculation result available for the client's insurance plan summary.
