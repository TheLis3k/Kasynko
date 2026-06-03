# CLAUDE.md – Virtual Casino Online (Wirtualne Kasyno Online)

## Project Overview
- **Name**: Virtual Casino Online (Wirtualne Kasyno Online)
- **Tech Stack**: PHP 8+, PostgreSQL, PDO (OOP), HTML5/CSS3, MVC pattern
- **Key Features**:
  - Rejestracja i logowanie użytkowników (role: admin/user)
  - Gry: Ruletka (numery, kolory, parzystość) & Jednoręki Bandyta (zakład przed spinem)
  - Pełny CRUD na głównym zasobie (sesje gier / zakłady)
  - Zaawansowane wyszukiwanie, filtrowanie (≥3 kryteria), sortowanie i paginacja po stronie serwera
  - Eksport do CSV / PDF / JSON
  - Przesyłanie plików (avatar użytkownika, dokumenty KYC) z automatycznym usuwaniem z dysku przy kasowaniu rekordu
  - Raporty administratora (aktywność użytkowników, statystyki gier, przychody)
  - K-means clustering — segmentacja graczy: Wieloryb / Casual / Niedzielny Janusz
  - System promocji i programu lojalnościowego (bonus powitalny, bonus od wpłaty, nagrody top spenderów)
  - i18n (Polski — domyślny / English) — każdy tekst w UI przetłumaczony; preferencja zapamiętana w profilu
  - Flash messages & wzorzec Post/Redirect/Get (PRG)

## Definition of Done
Projekt uznaje się za gotowy, gdy spełnione są **wszystkie** poniższe warunki:

### Kod i architektura
- [ ] Cały kod jest obiektowy (OOP); min. 3 klasy domenowe z metodami publicznymi i prywatnymi
- [ ] Zastosowany wzorzec MVC: kontrolery nie zawierają SQL, widoki nie zawierają logiki biznesowej
- [ ] Brak długich funkcji proceduralnych (funkcje >30 linii podzielone na prywatne metody)
- [ ] DRY: brak powtarzającej się logiki

### Baza danych
- [ ] Schemat PostgreSQL z min. 3 tabelami powiązanymi kluczami obcymi
- [ ] Min. jedna relacja many-to-many z dedykowaną tabelą łączącą
- [ ] Każda główna tabela ma `created_at DEFAULT CURRENT_TIMESTAMP`
- [ ] Wszystkie zapytania przez PDO z named placeholders; zero string-interpolacji w SQL

### Użytkownicy i sesja
- [ ] Rejestracja z polami: imię, nazwisko, e-mail, hasło, avatar (plik)
- [ ] Logowanie; po loginie `session_regenerate_id(true)`
- [ ] Hasła hashowane `password_hash()` z Argon2ID lub bcrypt; nigdy plain-text
- [ ] Bez zalogowania dostęp do żadnej funkcji
- [ ] Role: admin i user z oddzielonymi uprawnieniami
- [ ] Użytkownik może ustawić preferowany język (pl/en) zapisywany w `users.lang_preference`
- [ ] Avatar użytkownika: upload, wyświetlanie, podmiana; usunięcie konta = usunięcie pliku z dysku

### Gry
- [ ] Ruletka: zakłady na numer (0–36), kolor (red/black), parzyste/nieparzyste; wypłaty poprawnie wyliczone
- [ ] Jednoręki Bandyta: użytkownik ustawia kwotę zakładu przed spinem; min. 3 bębny z symbolami; tabela wypłat
- [ ] Saldo użytkownika aktualizowane po każdej grze; nie można zagrać przy niewystarczającym saldzie

### CRUD i tabela rekordów
- [ ] Pełny CRUD na głównym zasobie (np. `game_sessions` lub `bets`)
- [ ] Tabela HTML z nagłówkami, sortowalnymi kolumnami (link GET), akcjami edycji i usunięcia w każdym wierszu
- [ ] Formularz dodawania/edycji z ≥4 typami pól: tekstowe, liczbowe/datowe, select/radio, textarea/checkbox
- [ ] Walidacja serwerowa: wymagalność, długość, format; przy błędzie zachowanie wartości (poza hasłem)

### Wyszukiwanie, filtrowanie, paginacja
- [ ] Filtrowanie po ≥3 niezależnych kryteriach (np. zakres dat, typ gry, min/max kwota)
- [ ] Paginacja wyników po stronie serwera (poprzednia/następna + numery stron)
- [ ] Sortowanie po wybranej kolumnie (ASC/DESC) po stronie serwera

### Eksport i raporty
- [ ] Eksport do CSV (obowiązkowy), PDF i JSON (opcjonalny, ale docelowy)
- [ ] Eksport obejmuje cały wynik filtrowania (nie tylko bieżącą stronę)
- [ ] Panel admina z raportami: aktywność użytkowników, statystyki gier, przychody (sumy, zestawienia)

### K-means
- [ ] `KMeansService` zaimplementowany from scratch (brak zewnętrznych bibliotek ML)
- [ ] k=3, cechy: avg_bet, total_games, win_loss_ratio
- [ ] Segment labels: Wieloryb (highest avg_bet) / Casual / Niedzielny Janusz (lowest total_games)
- [ ] Wyniki widoczne w panelu admina (tabela graczy z przypisanym segmentem)

### Promocje i program lojalnościowy
- [ ] Bonus powitalny: automatyczne dopisanie środków przy pierwszej rejestracji
- [ ] Bonus od wpłaty: procentowy bonus naliczany przy każdym doładowaniu konta
- [ ] Nagrody dla top spenderów: ranking graczy wg. łącznych wydatków; admin może przyznać nagrodę

### Pliki
- [ ] Upload pliku (avatar lub dokument KYC) powiązany z rekordem
- [ ] Pliki przechowywane poza web root (lub chronione `.htaccess`), nazwy: `{id}_{uniqid()}.ext`
- [ ] Usunięcie rekordu → `unlink()` pliku z dysku przed DELETE z bazy
- [ ] Limit 5 MB; dozwolone typy: obraz (JPEG, PNG, GIF) lub PDF; walidacja MIME

### i18n
- [ ] Każdy widoczny tekst w UI pochodzi z pliku tłumaczeń (`lang/pl.php`, `lang/en.php`)
- [ ] Polski jest językiem domyślnym
- [ ] Przełącznik języka dostępny bez logowania i po zalogowaniu
- [ ] Preferencja zapamiętana w sesji i w profilu użytkownika

### UX i bezpieczeństwo
- [ ] Flash messages (jednorazowe komunikaty) po każdej operacji CREATE/UPDATE/DELETE/ERROR
- [ ] PRG: odświeżenie strony po POST nie wysyła formularza ponownie
- [ ] Custom error pages: 404, 500 (bez PHP stack trace)
- [ ] CSRF tokens na wszystkich formularzach POST
- [ ] XSS: cały output w widokach escapowany przez `htmlspecialchars()`
- [ ] Estetyczny wygląd: spójne kolory, czytelna typografia, unikalny CSS (lub framework z customizacją)
- [ ] Interfejs działa bez JavaScriptu (sortowanie/filtrowanie przez HTTP GET)

## Architecture & Coding Standards
- **OOP**: Cały projekt obiektowy; minimum 3 klasy domenowe.
- **MVC**:
  - Kontrolery: obsługa żądań HTTP, walidacja serwerowa, PRG, flash messages.
  - Modele: logika biznesowa (silniki gier, K-means, zapytania DB przez PDO).
  - Widoki: czyste szablony HTML/CSS, zero SQL, zero `new Model()`.
- **Baza**: PostgreSQL; schemat z ≥3 tabelami, min. jedna relacja many-to-many, klucze obce, `created_at`.
- **Bezpieczeństwo**:
  - Hasła: `password_hash()` z Argon2ID lub bcrypt.
  - Dostęp: niezalogowani blokowani na wszystkich zasobach poza login/register.
  - Custom error pages (404, 500) — żadnych surowych błędów PHP.
- **Walidacja**:
  - Wyłącznie serwerowa: wymagalność, długość, format.
  - Przy błędzie: zachowanie wartości (poza hasłami), wyświetlenie komunikatów.
- **Stylowanie**: własny CSS lub framework z indywidualną customizacją; spójna kolorystyka, czytelna typografia.
- **Pliki**: przesyłane na dysk; usunięcie rekordu = usunięcie pliku przez `unlink()`.

## Key Algorithms & Logic
- **K-means clustering** (w `KMeansService`):
  - k=3 (stałe), cechy: `avg_bet`, `total_games`, `win_loss_ratio`.
  - Dystans: euklidesowy `sqrt(sum((x_j - μ_j)^2))`.
  - Inicjalizacja: losowa (fixed seed dla powtarzalności demo).
  - Etykiety segmentów: **Wieloryb** (centroid o najwyższym avg_bet), **Niedzielny Janusz** (centroid o najniższym total_games), **Casual** (pozostały).
- **Ruletka**:
  - Typy zakładów: konkretny numer (0–36), kolor (red/black), parzyste/nieparzyste.
  - Wypłata: numer 35:1, kolor 1:1, parzyste/nieparzyste 1:1.
- **Jednoręki Bandyta**:
  - Użytkownik ustala kwotę zakładu przed spinem.
  - Min. 3 bębny, symbole z tabelą wypłat.
- **Promocje**:
  - Bonus powitalny: jednorazowy, naliczany przy rejestracji (konfigurowalny w adminie).
  - Bonus od wpłaty: procentowy, naliczany automatycznie przy każdym doładowaniu.
  - Top spenderzy: ranking wg. `SUM(bets.amount)`, admin może przyznać nagrodę ręcznie.

## Development Commands
```bash
# Lokalny serwer PHP
php -S localhost:8000 -t public/

# PostgreSQL (dostosuj do swojego środowiska)
pg_ctl -D /usr/local/var/postgres start
# lub przez Docker:
docker run --name kasynko-pg -e POSTGRES_PASSWORD=secret -p 5432:5432 -d postgres:16

# Testy (jeśli są)
phpunit tests/

# Jednorazowa konfiguracja po sklonowaniu repo (włącza hook commit-msg + szablon)
git config core.hooksPath .githooks
git config commit.template .gitmessage
```

## Commit Standard
- Repozytorium stosuje **Conventional Commits**: `<type>(<scope>): <subject>`.
- Pełny standard i lista typów/zakresów: `.claude/rules/commit-rules.md`.
- Format wymuszany przez hook `.githooks/commit-msg` (włączany komendą `git config core.hooksPath .githooks`).
- Subject ≤ 72 znaki, tryb rozkazujący, bez kropki. Przykład: `feat(roulette): dodaj wypłatę dla zakładów na kolor`.

## Important Conventions
- Zawsze **PRG** po POST → redirect do GET, flash w sesji.
- Flash messages: jednorazowe, widoczne po redirectie.
- Paginacja, sortowanie, filtrowanie — wyłącznie **po stronie serwera**.
- Eksport: cały wynik filtrowania (nie tylko bieżąca strona).
- Upload plików: poza web root lub `.htaccess`, nazwa pliku: `{id}_{uniqid()}.ext`.
- PDO z named placeholders; nigdy string-interpolacja w SQL.
- i18n: helper `t($key)` zwraca tłumaczenie z aktywnego pliku języka; pliki: `lang/pl.php`, `lang/en.php`.
- Domyślny język: **polski**. Angielski jako preferencja użytkownika.

## Claude's Role & Constraints
- Pomagasz deweloperowi w implementacji, przeglądaniu i refaktoryzacji projektu.
- Zawsze stosuj architekturę MVC opisaną powyżej — nie proponuj rozwiązań proceduralnych.
- Generując kod, zapewnij:
  - Brak zduplikowanej logiki (DRY).
  - Brak długich funkcji proceduralnych.
  - Separację warstw (Model: DB/biznes, Kontroler: wejście/wyjście, Widok: tylko renderowanie).
- Zapytania SQL: zawsze PDO ze named placeholders.
- Formularze: min. 4 typy pól.
- K-means: from scratch, bez zewnętrznych bibliotek ML, w dedykowanej klasie `KMeansService`.
- Eksport: CSV obowiązkowy; PDF i JSON preferowane.
- `created_at` ustawiany automatycznie (DEFAULT CURRENT_TIMESTAMP lub w `save()`).
- Każdy tekst UI musi przechodzić przez helper `t($key)`.

## Dokumentacja postępu prac
- Żywa mapa implementacji znajduje się w `Docs/IMPLEMENTATION.md` (utrzymywana przez agenta `doc-maintainer`).
- **Przed** rozpoczęciem nowej funkcjonalności sprawdź ten plik — żeby nie dublować istniejącego kodu.
- **Po** znaczącej zmianie (nowa klasa/kontroler/serwis/widok/migracja) zaktualizuj mapę (lub deleguj do `doc-maintainer`).
- Dokument zawiera: mapę funkcja→pliki, rejestr klas i odpowiedzialności (SRP), schemat bazy, ustalone konwencje, status Definition of Done oraz listę duplikacji do refaktoryzacji.
