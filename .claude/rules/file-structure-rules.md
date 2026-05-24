# File Structure Rules

Obowiązująca struktura katalogów projektu. Każdy nowy plik musi trafić we właściwe miejsce.

```
kasynko/
├── public/                  # JEDYNY katalog dostępny z web (web root)
│   ├── index.php            # front controller — jedyny punkt wejścia
│   ├── css/                 # arkusze stylów
│   ├── js/                  # skrypty klienckie
│   └── img/                 # publiczne obrazy (logo, ikony gier)
│
├── src/
│   ├── Controllers/         # po jednej klasie per plik, nazwa: FooController.php
│   ├── Models/              # klasy domenowe + dostęp do DB, nazwa: Foo.php
│   ├── Services/            # logika biznesowa niezwiązana z HTTP
│   │   ├── KMeansService.php
│   │   ├── PromotionService.php
│   │   └── ReportService.php
│   └── Helpers/             # funkcje/klasy pomocnicze
│       ├── AuthMiddleware.php
│       ├── Validation.php
│       ├── Database.php     # singleton PDO
│       └── helpers.php      # globalne funkcje pomocnicze: t(), flash(), redirect()
│
├── views/                   # szablony HTML
│   ├── layout/
│   │   ├── header.phtml
│   │   ├── footer.phtml
│   │   └── navbar.phtml
│   ├── auth/                # login.phtml, register.phtml
│   ├── games/               # roulette.phtml, slots.phtml
│   ├── bets/                # index.phtml, create.phtml, edit.phtml
│   ├── admin/               # dashboard.phtml, reports.phtml, segments.phtml
│   ├── profile/             # show.phtml, edit.phtml
│   └── errors/              # 404.phtml, 500.phtml, 403.phtml
│
├── lang/
│   ├── pl.php               # domyślny język
│   └── en.php
│
├── storage/
│   └── uploads/             # pliki użytkowników — POZA web root
│       ├── avatars/
│       └── documents/       # dokumenty KYC i inne
│
├── database/
│   ├── schema.sql           # pełny schemat PostgreSQL
│   └── seed.sql             # dane inicjalne (admin, gry, promocje)
│
├── config/
│   └── config.php           # dane połączenia DB, stałe aplikacji
│
└── tests/                   # testy jednostkowe (jeśli są)
```

## Zasady nazywania i lokowania plików

- **Kontroler**: `src/Controllers/FooController.php` → klasa `FooController`.
- **Model**: `src/Models/Foo.php` → klasa `Foo`.
- **Serwis**: `src/Services/FooService.php` → klasa `FooService`.
- **Widok**: `views/{moduł}/{akcja}.phtml` — bez logiki PHP poza `echo`, pętlami i prostymi warunkami.
- **Upload pliku**: `storage/uploads/{kategoria}/{id}_{uniqid()}.ext` — nigdy w `public/`.
- **Jeden plik = jedna klasa** (poza `helpers.php` z funkcjami globalnymi).
- **Namespace**: `App\Controllers`, `App\Models`, `App\Services`, `App\Helpers`.
- Nie twórz plików PHP poza tą strukturą bez wyraźnego powodu. Jeśli coś nie pasuje do żadnej kategorii — omów z developerem.
