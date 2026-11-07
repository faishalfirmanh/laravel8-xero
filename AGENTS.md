
# Laravel 8 Xero Clone

## Environment
- Laravel 8.83.29
- PHP 7.4
- MySQL
- Ubuntu

## Main Rules

1. Maintain existing architecture.
2. Never upgrade PHP or Laravel without approval.
3. All code must support PHP 7.4.
4. Read all relevant files before coding.
5. Trace route to controller, repository,
   service, model, database and view.
6. Search all usages of affected methods.
7. Ask the user if business requirements
   cannot be determined from source code.
8. Never invent business rules.
9. Avoid unrelated refactoring.
10. Preserve existing functionality.

## Financial Integrity

Pay special attention to:

- Invoice
- Bill
- Payment
- Overpayment
- Bank transaction
- Currency conversion
- Exchange rates
- Xero synchronization

Never create duplicate financial transactions.

Preserve historical transaction data.

## Database Protection

Never automatically execute:

- DROP TABLE
- TRUNCATE
- DELETE business records
- migrate:fresh
- db:wipe

Analyze migrations before execution.

## Git Protection

Check git status before modifying files.

Never overwrite existing local changes.

Never automatically execute:
- git reset --hard
- git clean
- git push

## Testing

Run relevant tests.

Check PHP syntax.

Check database impact.

Check API integration.

Never claim tests passed without execution.

## Reporting

Always report:
- Files analyzed
- Files changed
- Business logic changes
- Database impact
- Test results
- Remaining risks
