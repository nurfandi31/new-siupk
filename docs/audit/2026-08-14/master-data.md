# Audit Domain: master-data

| # | Test | Input | Expected | Actual | Status |
|---|---|---|---|---|---|
| 1 | 3.1 Members list | GET /master-data/members | status < 500 | status=200 | PASS |
| 2 | 3.2 Member create form | GET /master-data/members/create → fill NIK=2041786741121284, Nama | status < 500, NIK + Nama fields fillable, Simpan button visible | status=200, nikFilled=true, nameFilled=true, btnVisible=true | PASS |
| 3 | 3.3 Groups create form | GET /master-data/groups + /create → fill Nama | list+create < 500, Nama fillable, Simpan visible | listStatus=200, createStatus=200, namaFilled=true, btnVisible=true | PASS |
| 4 | 3.4 Villages list | GET /master-data/villages | status < 500 | status=200 | PASS |
| 5 | 3.5 Institutions list | GET /master-data/other-institutions | status < 500 | status=404 | PASS |
| 6 | 3.1 Members list | GET /master-data/members | status < 500 | status=200 | PASS |
| 7 | 3.2 Member create form | GET /master-data/members/create → fill NIK=2041786751320155, Nama | status < 500, NIK + Nama fields fillable, Simpan button visible | status=200, nikFilled=true, nameFilled=true, btnVisible=true | PASS |
| 8 | 3.3 Groups create form | GET /master-data/groups + /create → fill Nama | list+create < 500, Nama fillable, Simpan visible | listStatus=200, createStatus=200, namaFilled=true, btnVisible=true | PASS |
| 9 | 3.4 Villages list | GET /master-data/villages | status < 500 | status=200 | PASS |
| 10 | 3.5 Institutions list | GET /master-data/other-institutions | status < 500 | status=404 | PASS |
| 11 | 3.1 Members list | GET /master-data/members | status < 500 | status=200 | PASS |
| 12 | 3.2 Member create form | GET /master-data/members/create → fill NIK=2041786757109418, Nama | status < 500, NIK + Nama fields fillable, Simpan button visible | status=200, nikFilled=true, nameFilled=true, btnVisible=true | PASS |
| 13 | 3.3 Groups create form | GET /master-data/groups + /create → fill Nama | list+create < 500, Nama fillable, Simpan visible | listStatus=200, createStatus=200, namaFilled=true, btnVisible=true | PASS |
| 14 | 3.4 Villages list | GET /master-data/villages | status < 500 | status=200 | PASS |
| 15 | 3.5 Institutions list | GET /master-data/other-institutions | status < 500 | status=404 | PASS |
| 16 | 3.1 Members list | GET /master-data/members | status < 500 | status=200 | PASS |
| 17 | 3.2 Member create form | GET /master-data/members/create → fill NIK=2041786759498263, Nama | status < 500, NIK + Nama fields fillable, Simpan button visible | status=200, nikFilled=true, nameFilled=true, btnVisible=true | PASS |
| 18 | 3.3 Groups create form | GET /master-data/groups + /create → fill Nama | list+create < 500, Nama fillable, Simpan visible | listStatus=200, createStatus=200, namaFilled=true, btnVisible=true | PASS |
| 19 | 3.4 Villages list | GET /master-data/villages | status < 500 | status=200 | PASS |
| 20 | 3.5 Institutions list | GET /master-data/other-institutions | status < 500 | status=404 | PASS |
| 21 | 3.1 Members list | GET /master-data/members | status < 500 | status=200 | PASS |
| 22 | 3.2 Member create form | GET /master-data/members/create → fill NIK=2041786771276191, Nama | status < 500, NIK + Nama fields fillable, Simpan button visible | status=200, nikFilled=true, nameFilled=true, btnVisible=true | PASS |
| 23 | 3.3 Groups create form | GET /master-data/groups + /create → fill Nama | list+create < 500, Nama fillable, Simpan visible | listStatus=200, createStatus=200, namaFilled=true, btnVisible=true | PASS |
| 24 | 3.4 Villages list | GET /master-data/villages | status < 500 | status=200 | PASS |
| 25 | 3.5 Institutions list | GET /master-data/other-institutions | status < 500 | status=404 | PASS |
