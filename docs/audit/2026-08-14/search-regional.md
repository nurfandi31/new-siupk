# Audit Domain: search-regional

| # | Test | Input | Expected | Actual | Status |
|---|---|---|---|---|---|
| 1 | 11.1 Search | GET /search?q={terms} | all 2xx/3xx | "a"=200, "audit"=200 | PASS |
| 2 | 11.2 Regional provinces | GET /regional/provinces | status < 500 | status=404 | PASS |
| 3 | 11.1 Search | GET /search?q={terms} | all 2xx/3xx | "a"=200, "audit"=200 | PASS |
| 4 | 11.2 Regional provinces | GET /regional/provinces | status < 500 | status=404 | PASS |
| 5 | 11.1 Search | GET /search?q={terms} | all 2xx/3xx | "a"=200, "audit"=200 | PASS |
| 6 | 11.2 Regional provinces | GET /regional/provinces | status < 500 | status=404 | PASS |
| 7 | 11.1 Search | GET /search?q={terms} | all 2xx/3xx | "a"=200, "audit"=200 | PASS |
| 8 | 11.2 Regional provinces | GET /regional/provinces | status < 500 | status=404 | PASS |
| 9 | 11.1 Search | GET /search?q={terms} | all 2xx/3xx | "a"=200, "audit"=200 | PASS |
| 10 | 11.2 Regional provinces | GET /regional/provinces | status < 500 | status=404 | PASS |
