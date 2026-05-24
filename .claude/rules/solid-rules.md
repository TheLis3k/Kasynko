# SOLID Rules

Każda klasa i metoda w projekcie musi przestrzegać zasad SOLID. Poniżej konkretne zastosowania w kontekście tego projektu.

## S — Single Responsibility Principle
Klasa ma dokładnie jeden powód do zmiany.

- `KMeansService` — tylko algorytm klastrowania; nie pobiera danych z DB, nie renderuje widoku.
- `PromotionService` — tylko logika naliczania bonusów; nie wysyła e-maili, nie zarządza sesją.
- `ReportService` — tylko budowanie zapytań agregujących; nie eksportuje plików.
- `CsvExporter` / `PdfExporter` / `JsonExporter` — każdy eksporter to osobna klasa.
- `AuthMiddleware` — tylko sprawdzanie uwierzytelnienia i ról; nie zajmuje się hashowaniem.
- `Validation` — tylko walidacja i sanityzacja danych wejściowych; zero logiki domenowej.
- Kontroler obsługuje jedną grupę zasobów (np. `RouletteController`, `SlotController`, `BetController`) — nie łączy niespokrewnionych akcji w jednym kontrolerze.

## O — Open/Closed Principle
Klasy otwarte na rozszerzenie, zamknięte na modyfikację.

- Eksportery implementują wspólny interfejs `ExporterInterface::export(array $data, string $filename): void`. Dodanie formatu XML = nowa klasa, zero zmian w istniejącym kodzie.
- Typy promocji (`welcome`, `deposit`, `reward`) obsługiwane przez polimorfizm lub strategię — nie przez rosnący `switch` w `PromotionService`.
- Typy zakładów w ruletce (`NumberBet`, `ColorBet`, `ParityBet`) implementują `BetInterface::calculatePayout(int $winningNumber): int`.

## L — Liskov Substitution Principle
Klasa pochodna musi być w pełni wymienną dla klasy bazowej.

- Każdy konkretny eksporter (`CsvExporter`, `PdfExporter`) musi spełniać kontrakt `ExporterInterface` — identyczne sygnatury, te same wyjątki.
- Jeśli tworzysz `BaseController` — metody pomocnicze (`redirect()`, `flash()`, `view()`) muszą działać identycznie we wszystkich kontrolerach potomnych bez nadpisywania ich zachowania.

## I — Interface Segregation Principle
Interfejsy małe i specyficzne; klient nie zależy od metod, których nie używa.

- `ExporterInterface` → tylko `export(array $data, string $filename): void`.
- `BetInterface` → tylko `calculatePayout(int $winningNumber): int`.
- `ClusterableInterface` → tylko `toFeatureVector(): array` (używany przez `KMeansService`).
- Nie twórz "bożego interfejsu" z dziesiątkami metod — lepiej kilka małych.

## D — Dependency Inversion Principle
Moduły wysokiego poziomu zależą od abstrakcji, nie od konkretnych implementacji.

- Kontrolery otrzymują serwisy przez konstruktor (dependency injection), nie tworzą ich przez `new` w metodach akcji.
- `KMeansService`, `PromotionService`, `ReportService` wstrzykiwane do kontrolerów — łatwa podmiana i testowanie.
- `Database` wstrzykiwana do modeli przez konstruktor — modele nie wywołują `Database::getInstance()` wewnątrz metod.
- Przykład prawidłowego kontrolera:
```php
class AdminController {
    public function __construct(
        private ReportService  $reports,
        private KMeansService  $kmeans,
        private PromotionService $promotions,
    ) {}
}
```

## Zasady ogólne

- Jeśli piszesz `switch` lub `if/elseif` po typie/roli w kilku miejscach — to sygnał, że brakuje polimorfizmu.
- Jeśli klasa ma więcej niż ~200 linii — rozważ podział zgodnie z SRP.
- Jeśli konstruktor przyjmuje więcej niż 4–5 zależności — to sygnał zbyt dużej odpowiedzialności klasy.
- Prywatne metody pomocnicze są OK; jeśli prywatna metoda rozrosła się do >15 linii — wydziel ją do osobnej klasy/serwisu.
