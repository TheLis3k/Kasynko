# Skill: K-means Clustering — Segmentacja graczy
Implementuje `KMeansService` klasyfikujący graczy do segmentów: **Wieloryb / Casual / Niedzielny Janusz**.

**Interface**:
```php
class KMeansService {
    private const K = 3;
    private const MAX_ITER = 100;
    private const SEED = 42;

    public function cluster(array $playersData): array;
    private function initCentroids(array $data): array;
    private function assign(array $data, array $centroids): array;
    private function recalculate(array $data, array $assignments): array;
    private function euclidean(array $a, array $b): float;
    private function labelCentroids(array $centroids): array;
}
```

**Wejście** — `$playersData`: tablica asocjacyjna z kluczami:
- `user_id` (int)
- `avg_bet` (float) — średnia kwota zakładu
- `total_games` (int) — łączna liczba gier
- `win_loss_ratio` (float) — wygrane / przegrane (0 jeśli brak gier)

**Algorytm**:
1. Losowa inicjalizacja k=3 centroidów z danych (fixed seed `mt_srand(42)` dla powtarzalności).
2. Przypisanie każdego gracza do najbliższego centroidu (dystans euklidesowy).
3. Przeliczenie centroidów jako średnia punktów w klastrze.
4. Powtarzaj do konwergencji lub 100 iteracji.

**Etykietowanie segmentów** (po konwergencji):
- Centroid z najwyższym `avg_bet` → **Wieloryb**
- Centroid z najniższym `total_games` → **Niedzielny Janusz**
- Pozostały → **Casual**

**Wyjście** — `cluster()` zwraca:
```php
[
    ['user_id' => 5, 'segment' => 'Wieloryb',          'avg_bet' => 500.0, ...],
    ['user_id' => 3, 'segment' => 'Casual',             'avg_bet' => 50.0,  ...],
    ['user_id' => 9, 'segment' => 'Niedzielny Janusz',  'avg_bet' => 10.0,  ...],
]
```

**Integracja**:
- Admin panel uruchamia klastrowanie przyciskiem POST → PRG.
- Wyniki zapisywane do tabeli `player_segments (user_id, segment, clustered_at)`.
- Widok admina: tabela graczy z kolumną Segment, filtrowalna po segmencie.
- Wymagaj min. 3 graczy; jeśli brak danych — flash "Za mało danych do klastrowania".
