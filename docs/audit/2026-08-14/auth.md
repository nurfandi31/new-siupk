# Audit Domain: auth

| # | Test | Input | Expected | Actual | Status |
|---|---|---|---|---|---|
| 1 | 1.1 Landing hero + CTA | GET / | h1 "Dana Bergulir" visible, status 200 | status=200, hero=true | PASS |
| 2 | 1.2 Login wrong creds | POST /login {user:nobody_here, pass:wrongpass} | redirected away? NO — stay on /login | url=http://localhost:56586/login, stillLogin=true | PASS |
| 3 | 1.3 Login superadmin | POST /login {user:superadmin, pass:password} | redirect to /admin | url=http://localhost:56586/admin | PASS |
| 4 | 1.4 Login dev | POST /login {user:dev, pass:password} | redirect to /dashboard | url=http://localhost:56586/dashboard | PASS |
| 5 | 1.5 Logout | POST /logout then GET /dashboard | redirected to /login (302/30x) | status=302 | PASS |
| 6 | 1.1 Landing hero + CTA | GET / | h1 "Dana Bergulir" visible, status 200 | status=200, hero=true | PASS |
| 7 | 1.2 Login wrong creds | POST /login {user:nobody_here, pass:wrongpass} | redirected away? NO — stay on /login | url=http://localhost:56586/login, stillLogin=true | PASS |
| 8 | 1.3 Login superadmin | POST /login {user:superadmin, pass:password} | redirect to /admin | url=http://localhost:56586/admin | PASS |
| 9 | 1.4 Login dev | POST /login {user:dev, pass:password} | redirect to /dashboard | url=http://localhost:56586/dashboard | PASS |
| 10 | 1.5 Logout | POST /logout then GET /dashboard | redirected to /login (302/30x) | status=302 | PASS |
| 11 | 1.1 Landing hero + CTA | GET / | h1 "Dana Bergulir" visible, status 200 | status=200, hero=true | PASS |
| 12 | 1.2 Login wrong creds | POST /login {user:nobody_here, pass:wrongpass} | redirected away? NO — stay on /login | url=http://localhost:56586/login, stillLogin=true | PASS |
| 13 | 1.3 Login superadmin | POST /login {user:superadmin, pass:password} | redirect to /admin | url=http://localhost:56586/admin | PASS |
| 14 | 1.4 Login dev | POST /login {user:dev, pass:password} | redirect to /dashboard | url=http://localhost:56586/dashboard | PASS |
| 15 | 1.5 Logout | POST /logout then GET /dashboard | redirected to /login (302/30x) | status=302 | PASS |
| 16 | 1.1 Landing hero + CTA | GET / | h1 "Dana Bergulir" visible, status 200 | status=200, hero=true | PASS |
| 17 | 1.2 Login wrong creds | POST /login {user:nobody_here, pass:wrongpass} | redirected away? NO — stay on /login | url=http://localhost:56586/login, stillLogin=true | PASS |
| 18 | 1.3 Login superadmin | POST /login {user:superadmin, pass:password} | redirect to /admin | url=http://localhost:56586/admin | PASS |
| 19 | 1.4 Login dev | POST /login {user:dev, pass:password} | redirect to /dashboard | url=http://localhost:56586/dashboard | PASS |
| 20 | 1.5 Logout | POST /logout then GET /dashboard | redirected to /login (302/30x) | status=302 | PASS |
| 21 | 1.1 Landing hero + CTA | GET / | h1 "Dana Bergulir" visible, status 200 | status=200, hero=true | PASS |
| 22 | 1.2 Login wrong creds | POST /login {user:nobody_here, pass:wrongpass} | redirected away? NO — stay on /login | url=http://localhost:56586/login, stillLogin=true | PASS |
| 23 | 1.3 Login superadmin | POST /login {user:superadmin, pass:password} | redirect to /admin | url=http://localhost:56586/admin | PASS |
| 24 | 1.4 Login dev | POST /login {user:dev, pass:password} | redirect to /dashboard | url=http://localhost:56586/dashboard | PASS |
| 25 | 1.5 Logout | POST /logout then GET /dashboard | redirected to /login (302/30x) | status=302 | PASS |
