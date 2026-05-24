# Wirtualne Kasyno Online

Zaawansowana platforma hazardowa w PHP 8+ z ruletką, jednorękim bandytą, systemem kont, promocjami i panelem administracyjnym.

## Wymagania

- PHP 8.0+
- PostgreSQL 14+
- Composer (opcjonalnie, jeśli dodasz zależności jak dompdf)

## Uruchomienie

```bash
# 1. Sklonuj repozytorium
git clone <repo-url> kasynko
cd kasynko

# 2. Skopiuj i uzupełnij konfigurację
cp config/config.example.php config/config.php
# Ustaw dane połączenia z PostgreSQL

# 3. Zainicjuj bazę danych
psql -U postgres -d kasynko -f database/schema.sql
psql -U postgres -d kasynko -f database/seed.sql   # dane testowe (opcjonalnie)

# 4. Utwórz katalog na pliki (poza web root)
mkdir -p storage/uploads

# 5. Uruchom serwer deweloperski
php -S localhost:8000 -t public/
```

Aplikacja dostępna pod adresem: http://localhost:8000

## Struktura projektu

```
kasynko/
├── public/          # web root (index.php, CSS, JS, obrazy)
├── src/
│   ├── Controllers/ # obsługa żądań HTTP, PRG, flash messages
│   ├── Models/      # logika biznesowa, zapytania PDO
│   ├── Services/    # KMeansService, PromotionService, ReportService
│   └── Helpers/     # helper t(), Validation, AuthMiddleware
├── views/           # szablony HTML (.phtml)
├── lang/
│   ├── pl.php       # tłumaczenia polskie (domyślne)
│   └── en.php       # tłumaczenia angielskie
├── storage/
│   └── uploads/     # pliki użytkowników (poza web root)
├── database/
│   ├── schema.sql   # schemat PostgreSQL
│   └── seed.sql     # dane inicjalne
└── config/
    └── config.php   # dane połączenia z DB
```

## Konta testowe

| Rola  | E-mail             | Hasło    |
|-------|--------------------|----------|
| Admin | admin@kasynko.pl   | admin123 |
| User  | gracz@kasynko.pl   | gracz123 |

## Funkcje

- Rejestracja i logowanie z hashowaniem haseł (Argon2ID)
- Ruletka: zakłady na numer, kolor, parzyste/nieparzyste
- Jednoręki Bandyta: konfigurowalny zakład, 3 bębny, tabela wypłat
- System promocji: bonus powitalny, bonus od wpłaty, nagrody top spenderów
- Panel admina: raporty, segmentacja K-means (Wieloryb/Casual/Niedzielny Janusz)
- Eksport danych: CSV, PDF, JSON
- Dwujęzyczny interfejs: Polski (domyślny) / English
