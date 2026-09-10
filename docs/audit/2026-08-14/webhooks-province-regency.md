# Audit Domain: webhooks-province-regency

| # | Test | Input | Expected | Actual | Status |
|---|---|---|---|---|---|
| 1 | 12.1 Province auth boundary | GET /province/dashboard (anon) | redirect to /login | status=302 | PASS |
| 2 | 12.2 Regency auth boundary | GET /regency/dashboard (anon) | redirect to /login | status=302 | PASS |
| 3 | 12.3 Webhooks | POST fake payload to 3 webhooks | all status < 500 | tripay=403, duitku=419, xendit=419 | PASS |
| 4 | 12.1 Province auth boundary | GET /province/dashboard (anon) | redirect to /login | status=302 | PASS |
| 5 | 12.2 Regency auth boundary | GET /regency/dashboard (anon) | redirect to /login | status=302 | PASS |
| 6 | 12.3 Webhooks | POST fake payload to 3 webhooks | all status < 500 | tripay=403, duitku=419, xendit=419 | PASS |
| 7 | 12.1 Province auth boundary | GET /province/dashboard (anon) | redirect to /login | status=302 | PASS |
| 8 | 12.2 Regency auth boundary | GET /regency/dashboard (anon) | redirect to /login | status=302 | PASS |
| 9 | 12.3 Webhooks | POST fake payload to 3 webhooks | all status < 500 | tripay=403, duitku=419, xendit=419 | PASS |
| 10 | 12.1 Province auth boundary | GET /province/dashboard (anon) | redirect to /login | status=302 | PASS |
| 11 | 12.2 Regency auth boundary | GET /regency/dashboard (anon) | redirect to /login | status=302 | PASS |
| 12 | 12.3 Webhooks | POST fake payload to 3 webhooks | all status < 500 | tripay=403, duitku=419, xendit=419 | PASS |
| 13 | 12.1 Province auth boundary | GET /province/dashboard (anon) | redirect to /login | status=302 | PASS |
| 14 | 12.2 Regency auth boundary | GET /regency/dashboard (anon) | redirect to /login | status=302 | PASS |
| 15 | 12.3 Webhooks | POST fake payload to 3 webhooks | all status < 500 | tripay=403, duitku=419, xendit=419 | PASS |
