# Audit Domain: admin-platform

| # | Test | Input | Expected | Actual | Status |
|---|---|---|---|---|---|
| 1 | 2.1 Admin dashboard | GET /admin | status < 500, h1 visible | status=200 | PASS |
| 2 | 2.2 Tenants list | GET /admin/tenants | status < 500 | status=200 | PASS |
| 3 | 2.3 Tenant create form | GET /admin/tenants/create | status < 500, Kode field visible | status=200, hasKodeField=false | FAIL |
| 4 | 2.4 Plans list | GET /admin/plans | status < 500 | status=200 | PASS |
| 5 | 2.5 Plan create submit | code=AUDIT_40991303, price=100000 | POST /admin/plans < 400 | status=302 | PASS |
| 6 | 2.6 Invoices list | GET /admin/invoices | status < 500 | status=200 | PASS |
| 7 | 2.7 Payment gateways page | GET /admin/payment-gateways | status < 500, form visible | status=200, hasForm=false | FAIL |
| 8 | 2.8 AI assistant page | GET /admin/ai-assistant | status < 500 | status=200 | PASS |
| 9 | 2.9 Migration page | GET /admin/migration | h1 contains "Migrasi", Host: & Database: visible | title="Migrasi & Cutover Tenant", host=true, db=true | PASS |
| 10 | 2.10 Personas API | GET /admin/ai-assistant/personas | status < 500 | status=200 | PASS |
| 11 | 2.11 Tools API | GET /admin/ai-assistant/tools | status < 500 | status=200 | PASS |
| 12 | 2.1 Admin dashboard | GET /admin | status < 500, h1 visible | status=200 | PASS |
| 13 | 2.2 Tenants list | GET /admin/tenants | status < 500 | status=200 | PASS |
| 14 | 2.3 Tenant create form | GET /admin/tenants/create | status < 500, Kode field visible | status=200, hasKodeField=false | FAIL |
| 15 | 2.4 Plans list | GET /admin/plans | status < 500 | status=200 | PASS |
| 16 | 2.5 Plan create submit | code=AUDIT_51081020, price=100000 | POST /admin/plans < 400 | status=302 | PASS |
| 17 | 2.6 Invoices list | GET /admin/invoices | status < 500 | status=200 | PASS |
| 18 | 2.7 Payment gateways page | GET /admin/payment-gateways | status < 500, form visible | status=200, hasForm=false | FAIL |
| 19 | 2.8 AI assistant page | GET /admin/ai-assistant | status < 500 | status=200 | PASS |
| 20 | 2.9 Migration page | GET /admin/migration | h1 contains "Migrasi", Host: & Database: visible | title="Migrasi & Cutover Tenant", host=true, db=true | PASS |
| 21 | 2.10 Personas API | GET /admin/ai-assistant/personas | status < 500 | status=200 | PASS |
| 22 | 2.11 Tools API | GET /admin/ai-assistant/tools | status < 500 | status=200 | PASS |
| 23 | 2.1 Admin dashboard | GET /admin | status < 500, h1 visible | status=200 | PASS |
| 24 | 2.2 Tenants list | GET /admin/tenants | status < 500 | status=200 | PASS |
| 25 | 2.3 Tenant create form | GET /admin/tenants/create | status < 500, Kode field visible | status=200, hasKodeField=false | FAIL |
| 26 | 2.4 Plans list | GET /admin/plans | status < 500 | status=200 | PASS |
| 27 | 2.5 Plan create submit | code=AUDIT_56961042, price=100000 | POST /admin/plans < 400 | status=302 | PASS |
| 28 | 2.6 Invoices list | GET /admin/invoices | status < 500 | status=200 | PASS |
| 29 | 2.7 Payment gateways page | GET /admin/payment-gateways | status < 500, form visible | status=200, hasForm=false | FAIL |
| 30 | 2.8 AI assistant page | GET /admin/ai-assistant | status < 500 | status=200 | PASS |
| 31 | 2.9 Migration page | GET /admin/migration | h1 contains "Migrasi", Host: & Database: visible | title="Migrasi & Cutover Tenant", host=true, db=true | PASS |
| 32 | 2.10 Personas API | GET /admin/ai-assistant/personas | status < 500 | status=200 | PASS |
| 33 | 2.11 Tools API | GET /admin/ai-assistant/tools | status < 500 | status=200 | PASS |
| 34 | 2.1 Admin dashboard | GET /admin | status < 500, h1 visible | status=200 | PASS |
| 35 | 2.2 Tenants list | GET /admin/tenants | status < 500 | status=200 | PASS |
| 36 | 2.3 Tenant create form | GET /admin/tenants/create | status < 500, Kode field visible | status=200, hasKodeField=false | FAIL |
| 37 | 2.4 Plans list | GET /admin/plans | status < 500 | status=200 | PASS |
| 38 | 2.5 Plan create submit | code=AUDIT_59335107, price=100000 | POST /admin/plans < 400 | status=302 | PASS |
| 39 | 2.6 Invoices list | GET /admin/invoices | status < 500 | status=200 | PASS |
| 40 | 2.7 Payment gateways page | GET /admin/payment-gateways | status < 500, form visible | status=200, hasForm=false | FAIL |
| 41 | 2.8 AI assistant page | GET /admin/ai-assistant | status < 500 | status=200 | PASS |
| 42 | 2.9 Migration page | GET /admin/migration | h1 contains "Migrasi", Host: & Database: visible | title="Migrasi & Cutover Tenant", host=true, db=true | PASS |
| 43 | 2.10 Personas API | GET /admin/ai-assistant/personas | status < 500 | status=200 | PASS |
| 44 | 2.11 Tools API | GET /admin/ai-assistant/tools | status < 500 | status=200 | PASS |
| 45 | 2.1 Admin dashboard | GET /admin | status < 500, h1 visible | status=200 | PASS |
| 46 | 2.2 Tenants list | GET /admin/tenants | status < 500 | status=200 | PASS |
| 47 | 2.3 Tenant create form | GET /admin/tenants/create | status < 500, Kode field visible | status=200, hasKodeField=false | FAIL |
| 48 | 2.4 Plans list | GET /admin/plans | status < 500 | status=200 | PASS |
| 49 | 2.5 Plan create submit | code=AUDIT_71066458, price=100000 | POST /admin/plans < 400 | status=302 | PASS |
| 50 | 2.6 Invoices list | GET /admin/invoices | status < 500 | status=200 | PASS |
| 51 | 2.7 Payment gateways page | GET /admin/payment-gateways | status < 500, form visible | status=200, hasForm=false | FAIL |
| 52 | 2.8 AI assistant page | GET /admin/ai-assistant | status < 500 | status=200 | PASS |
| 53 | 2.9 Migration page | GET /admin/migration | h1 contains "Migrasi", Host: & Database: visible | title="Migrasi & Cutover Tenant", host=true, db=true | PASS |
| 54 | 2.10 Personas API | GET /admin/ai-assistant/personas | status < 500 | status=200 | PASS |
| 55 | 2.11 Tools API | GET /admin/ai-assistant/tools | status < 500 | status=200 | PASS |
| 56 | 2.1 Admin dashboard | GET /admin | status < 500, h1 visible | status=200 | PASS |
| 57 | 2.2 Tenants list | GET /admin/tenants | status < 500 | status=200 | PASS |
| 58 | 2.3 Tenant create form | GET /admin/tenants/create | status < 500, Kode field visible | status=200, hasKodeField=false | FAIL |
| 59 | 2.4 Plans list | GET /admin/plans | status < 500 | status=200 | PASS |
| 60 | 2.5 Plan create submit | code=AUDIT_05322393, price=100000 | POST /admin/plans < 400 | status=302 | PASS |
| 61 | 2.6 Invoices list | GET /admin/invoices | status < 500 | status=200 | PASS |
| 62 | 2.7 Payment gateways page | GET /admin/payment-gateways | status < 500, form visible | status=200, hasForm=false | FAIL |
| 63 | 2.8 AI assistant page | GET /admin/ai-assistant | status < 500 | status=200 | PASS |
| 64 | 2.9 Migration page | GET /admin/migration | h1 contains "Migrasi", Host: & Database: visible | title="Migrasi & Cutover Tenant", host=true, db=true | PASS |
| 65 | 2.10 Personas API | GET /admin/ai-assistant/personas | status < 500 | status=200 | PASS |
| 66 | 2.11 Tools API | GET /admin/ai-assistant/tools | status < 500 | status=200 | PASS |
| 67 | 2.1 Admin dashboard | GET /admin | status < 500, h1 visible | status=200 | PASS |
| 68 | 2.2 Tenants list | GET /admin/tenants | status < 500 | status=200 | PASS |
| 69 | 2.3 Tenant create form | GET /admin/tenants/create | status < 500, Kode field visible | status=200, hasKodeField=false | FAIL |
| 70 | 2.4 Plans list | GET /admin/plans | status < 500 | status=200 | PASS |
| 71 | 2.5 Plan create submit | code=AUDIT_40094204, price=100000 | POST /admin/plans < 400 | status=302 | PASS |
| 72 | 2.6 Invoices list | GET /admin/invoices | status < 500 | status=200 | PASS |
| 73 | 2.7 Payment gateways page | GET /admin/payment-gateways | status < 500, form visible | status=200, hasForm=false | FAIL |
| 74 | 2.8 AI assistant page | GET /admin/ai-assistant | status < 500 | status=200 | PASS |
| 75 | 2.9 Migration page | GET /admin/migration | h1 contains "Migrasi", Host: & Database: visible | title="Migrasi & Cutover Tenant", host=true, db=true | PASS |
| 76 | 2.10 Personas API | GET /admin/ai-assistant/personas | status < 500 | status=200 | PASS |
| 77 | 2.11 Tools API | GET /admin/ai-assistant/tools | status < 500 | status=200 | PASS |
