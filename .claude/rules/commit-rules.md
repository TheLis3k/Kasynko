# Commit Message Rules

Repozytorium stosuje standard **Conventional Commits**. Każdy commit musi być zgodny z poniższym formatem — wymusza go hook `commit-msg` w `.githooks/`.

## Format

```
<type>(<scope>): <subject>

[opcjonalne body]

[opcjonalna stopka: BREAKING CHANGE, Refs, Co-Authored-By]
```

- **Pierwsza linia (subject)**: max 72 znaki, tryb rozkazujący, bez kropki na końcu.
- **type**: obowiązkowy, małe litery (lista poniżej).
- **scope**: opcjonalny, małe litery, z listy modułów (poniżej).
- **body**: opcjonalne, oddzielone pustą linią; wyjaśnia *co* i *dlaczego*, nie *jak*.

## Dozwolone typy (`type`)

| Type       | Zastosowanie |
|------------|--------------|
| `feat`     | nowa funkcjonalność dla użytkownika |
| `fix`      | poprawka błędu |
| `docs`     | dokumentacja (CLAUDE.md, README, Docs/, komentarze) |
| `style`    | formatowanie, białe znaki, CSS — bez zmiany logiki |
| `refactor` | zmiana kodu bez nowej funkcji ani poprawki błędu |
| `perf`     | poprawa wydajności |
| `test`     | dodanie lub poprawa testów |
| `build`    | system budowania, zależności (composer) |
| `ci`       | konfiguracja CI/CD, hooki |
| `chore`    | rutynowe zadania, konfiguracja, porządki |
| `revert`   | wycofanie wcześniejszego commita |

## Dozwolone zakresy (`scope`)

Zgodne z modułami projektu: `auth`, `roulette`, `slots`, `bets`, `crud`, `db`, `i18n`, `admin`, `reports`, `kmeans`, `promo`, `export`, `files`, `ui`, `errors`, `config`, `docs`.

Jeśli zmiana dotyczy wielu modułów lub całego repo — pomiń scope.

## Breaking changes

- Dodaj `!` po typie/scope: `feat(db)!: zmiana schematu users`.
- Lub stopka `BREAKING CHANGE: opis` w body.

## Przykłady

```
feat(roulette): dodaj wypłatę dla zakładów na kolor
fix(auth): regeneruj ID sesji przed zapisem danych użytkownika
refactor(export): wydziel ExporterInterface dla CSV/PDF/JSON
docs(kmeans): opisz etykiety segmentów Wieloryb/Casual/Niedzielny Janusz
chore(config): dodaj szablon connection string dla PostgreSQL
feat(db)!: zmień user_promotions na relację many-to-many
```

## Zasady ogólne

- Język subjectu: angielski lub polski — trzymaj się jednego w całym repo (domyślnie: angielski, tryb rozkazujący).
- Jeden commit = jedna logiczna zmiana. Nie mieszaj feature + refactor + formatowanie w jednym commicie.
- Nie commituj zakomentowanego kodu ani plików tymczasowych.
- Stopka `Co-Authored-By:` dozwolona (np. dla commitów współtworzonych).
