<?php

namespace App\Services;

use PDO;

/**
 * K-Means Player Segmentation
 *
 * Implements the k-means clustering algorithm from scratch (no external ML libraries)
 * to group casino players into three behavioural segments based on three features:
 *   - avg_bet        : average bet amount across all game sessions
 *   - total_games    : total number of games played
 *   - win_loss_ratio : wins / losses (0 when no losses recorded)
 *
 * Segment labels are assigned deterministically after convergence:
 *   Wieloryb         — cluster with the highest avg_bet (high-value "whale" players)
 *   Niedzielny Janusz — cluster with the lowest total_games (occasional/weekend players)
 *   Casual            — the remaining cluster
 *
 * Algorithm overview:
 *   1. Load raw features from the DB for every player with role = 'user'.
 *   2. Normalise each feature to [0, 1] (min-max) so no single feature dominates distance.
 *   3. Initialise k=3 centroids by picking 3 random data points (fixed seed for reproducibility).
 *   4. Iteratively assign each point to its nearest centroid (Euclidean distance),
 *      then recompute centroids as the mean of all assigned points.
 *   5. Stop when assignments stop changing or MAX_ITER is reached.
 *   6. Map cluster indices → segment names using original-scale feature means.
 *   7. Persist results to player_segments (upsert) and return labelled rows.
 */
class KMeansService
{
    /** Number of clusters — fixed at 3 to match the three defined player segments. */
    private const K        = 3;

    /** Safety cap on iterations; in practice the algorithm converges well before this. */
    private const MAX_ITER = 100;

    /**
     * Fixed seed for srand() so centroid initialisation is identical across runs.
     * Without a fixed seed the resulting segments would differ on every request,
     * making admin reports inconsistent between page loads.
     */
    private const SEED     = 42;

    public function __construct(private PDO $db) {}

    /**
     * Run k-means on all players, persist results, return labelled rows.
     *
     * Returns an empty array when there are fewer players than clusters (k=3),
     * because k-means requires at least k distinct data points.
     *
     * @return array<array{user_id:int, email:string, first_name:string, last_name:string,
     *                      avg_bet:float, total_games:int, win_loss_ratio:float, segment:string}>
     */
    public function clusterAndPersist(): array
    {
        $players = $this->loadFeatures();
        if (count($players) < self::K) {
            return [];
        }

        $vectors  = array_column($players, null);
        $labels   = $this->kmeans($vectors);
        $segments = $this->assignLabels($vectors, $labels);

        $this->persist($players, $labels, $segments);

        $result = [];
        foreach ($players as $i => $p) {
            $result[] = array_merge($p, ['segment' => $segments[$labels[$i]]]);
        }
        return $result;
    }

    /** Return single segment row for one user, or null if clustering hasn't run yet. */
    public function getSegmentForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT segment, avg_bet, total_games, win_loss_ratio, clustered_at
             FROM player_segments WHERE user_id = :uid LIMIT 1'
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Return last persisted segments (or [] if none). */
    public function getSegments(): array
    {
        $sql = <<<SQL
            SELECT u.id AS user_id, u.first_name, u.last_name, u.email,
                   ps.avg_bet, ps.total_games, ps.win_loss_ratio, ps.segment, ps.clustered_at
            FROM player_segments ps
            JOIN users u ON u.id = ps.user_id
            ORDER BY ps.segment, u.last_name
            SQL;
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------

    /**
     * Fetch the three clustering features for every regular player.
     * win_loss_ratio uses NULLIF to avoid division-by-zero when a player has no losses;
     * COALESCE then converts that NULL to 0.
     */
    private function loadFeatures(): array
    {
        $sql = <<<SQL
            SELECT u.id AS user_id, u.email, u.first_name, u.last_name,
                   COALESCE(AVG(gs.bet_amount), 0)                             AS avg_bet,
                   COUNT(gs.id)                                                 AS total_games,
                   COALESCE(
                       SUM(CASE WHEN gs.outcome = 'win'  THEN 1.0 ELSE 0 END)
                     / NULLIF(SUM(CASE WHEN gs.outcome = 'lose' THEN 1.0 ELSE 0 END), 0),
                   0)                                                           AS win_loss_ratio
            FROM users u
            LEFT JOIN game_sessions gs ON gs.user_id = u.id
            WHERE u.role = 'user'
            GROUP BY u.id, u.email, u.first_name, u.last_name
            SQL;
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Core k-means loop.
     *
     * Steps:
     *   1. Normalise vectors so all features are on a [0,1] scale.
     *   2. Seed PHP's RNG and pick K random points as initial centroids.
     *      Using a fixed seed (SEED=42) guarantees the same starting positions
     *      every time, which makes segment results reproducible across requests.
     *   3. Repeat until convergence or MAX_ITER:
     *      a. Assign each point to the nearest centroid (Euclidean distance).
     *      b. Recompute each centroid as the mean of its assigned points.
     *      c. If assignments didn't change, the algorithm has converged — stop early.
     *
     * @param array[] $vectors each has avg_bet, total_games, win_loss_ratio
     * @return int[] cluster index (0..K-1) for each input vector
     */
    private function kmeans(array $vectors): array
    {
        $n          = count($vectors);
        $normalized = $this->normalize($vectors);

        // Fixed seed ensures identical centroid initialisation on every run.
        srand(self::SEED);
        $indices   = array_rand($normalized, self::K);
        $centroids = array_map(fn(int $i) => $normalized[$i], $indices);

        $labels = array_fill(0, $n, 0);
        for ($iter = 0; $iter < self::MAX_ITER; $iter++) {
            // Assignment step: each point goes to the nearest centroid.
            $newLabels = [];
            foreach ($normalized as $vec) {
                $newLabels[] = $this->nearest($vec, $centroids);
            }

            // Convergence check: if nothing moved, further iterations won't change anything.
            if ($newLabels === $labels) {
                break;
            }

            $labels    = $newLabels;

            // Update step: shift each centroid to the mean of its assigned points.
            $centroids = $this->recomputeCentroids($normalized, $labels);
        }

        return $labels;
    }

    /**
     * Map numeric cluster indices (0, 1, 2) to human-readable segment names.
     *
     * Label assignment uses original-scale feature means rather than normalised centroids
     * so the business rules ("Wieloryb = highest spender") map directly to real currency values.
     *
     * Priority order prevents two labels from colliding on the same cluster:
     *   1. Identify Wieloryb  → cluster with the highest mean avg_bet.
     *   2. Identify Niedzielny Janusz → cluster with the lowest mean total_games,
     *      excluding whichever cluster was already claimed by Wieloryb.
     *   3. The remaining cluster becomes Casual.
     *
     * Tie-break: if Wieloryb and Janusz would be the same cluster (e.g. a single player
     * who bets high but plays rarely), Janusz falls back to the second-lowest total_games cluster.
     */
    private function assignLabels(array $vectors, array $labels): array
    {
        // Compute per-cluster means on original (un-normalised) scale.
        $clusterAvgBet     = [];
        $clusterTotalGames = [];
        foreach (range(0, self::K - 1) as $k) {
            $members = array_keys(array_filter($labels, fn(int $l) => $l === $k));
            $clusterAvgBet[$k]     = empty($members) ? 0
                : array_sum(array_map(fn(int $i) => (float)$vectors[$i]['avg_bet'], $members)) / count($members);
            $clusterTotalGames[$k] = empty($members) ? 0
                : array_sum(array_map(fn(int $i) => (int)$vectors[$i]['total_games'], $members)) / count($members);
        }

        $wielorybCluster = (int)array_keys($clusterAvgBet, max($clusterAvgBet))[0];
        $januszCluster   = (int)array_keys($clusterTotalGames, min($clusterTotalGames))[0];

        // Tie-break: Wieloryb already owns this cluster index, so give Janusz the next lowest.
        if ($wielorybCluster === $januszCluster) {
            asort($clusterTotalGames);
            $sorted        = array_keys($clusterTotalGames);
            $januszCluster = ($sorted[0] === $wielorybCluster) ? $sorted[1] : $sorted[0];
        }

        $segments = [];
        foreach (range(0, self::K - 1) as $k) {
            if ($k === $wielorybCluster) {
                $segments[$k] = 'Wieloryb';
            } elseif ($k === $januszCluster) {
                $segments[$k] = 'Niedzielny Janusz';
            } else {
                $segments[$k] = 'Casual';
            }
        }
        return $segments;
    }

    /**
     * Upsert clustering results into player_segments.
     * ON CONFLICT ensures re-running clustering overwrites stale data rather than inserting duplicates.
     */
    private function persist(array $players, array $labels, array $segments): void
    {
        $stmt = $this->db->prepare(<<<SQL
            INSERT INTO player_segments (user_id, segment, avg_bet, total_games, win_loss_ratio, clustered_at)
            VALUES (:user_id, :segment, :avg_bet, :total_games, :win_loss_ratio, NOW())
            ON CONFLICT (user_id) DO UPDATE
                SET segment       = EXCLUDED.segment,
                    avg_bet       = EXCLUDED.avg_bet,
                    total_games   = EXCLUDED.total_games,
                    win_loss_ratio= EXCLUDED.win_loss_ratio,
                    clustered_at  = EXCLUDED.clustered_at
            SQL);

        foreach ($players as $i => $p) {
            $stmt->execute([
                ':user_id'        => (int)$p['user_id'],
                ':segment'        => $segments[$labels[$i]],
                ':avg_bet'        => round((float)$p['avg_bet'], 2),
                ':total_games'    => (int)$p['total_games'],
                ':win_loss_ratio' => round((float)$p['win_loss_ratio'], 4),
            ]);
        }
    }

    // -----------------------------------------------------------------
    // Math helpers
    // -----------------------------------------------------------------

    /**
     * Return the index of the centroid closest to $vec (smallest Euclidean distance).
     * Ties are broken in favour of the lower-index centroid (first one found).
     */
    private function nearest(array $vec, array $centroids): int
    {
        $best     = 0;
        $bestDist = PHP_FLOAT_MAX;
        foreach ($centroids as $k => $c) {
            $dist = $this->euclidean($vec, $c);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best     = $k;
            }
        }
        return $best;
    }

    /**
     * Euclidean distance: sqrt( Σ (a_i − b_i)² )
     * Both vectors must have the same number of dimensions.
     */
    private function euclidean(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $v) {
            $sum += ($v - $b[$i]) ** 2;
        }
        return sqrt($sum);
    }

    /**
     * Min-max normalisation: scales each feature independently to [0, 1].
     *
     *   x_norm = (x − min) / (max − min)
     *
     * Features with zero range (all players have the same value) are mapped to 0.0
     * to avoid division by zero — they contribute nothing to cluster separation anyway.
     *
     * Normalisation is required because avg_bet (e.g. 0–5000 PLN) and total_games
     * (e.g. 0–300) are on completely different scales; without it, avg_bet would
     * dominate the Euclidean distance and total_games would be almost irrelevant.
     *
     * @return array[] indexed numeric vectors [avg_bet_norm, total_games_norm, win_loss_ratio_norm]
     */
    private function normalize(array $vectors): array
    {
        $features = ['avg_bet', 'total_games', 'win_loss_ratio'];
        $mins = $maxs = [];

        foreach ($features as $f) {
            $vals     = array_column($vectors, $f);
            $mins[$f] = min($vals);
            $maxs[$f] = max($vals);
        }

        $result = [];
        foreach ($vectors as $v) {
            $row = [];
            foreach ($features as $f) {
                $range  = $maxs[$f] - $mins[$f];
                $row[]  = $range > 0 ? ((float)$v[$f] - $mins[$f]) / $range : 0.0;
            }
            $result[] = $row;
        }
        return $result;
    }

    /**
     * Recompute each centroid as the arithmetic mean of all points assigned to it.
     *
     * If a cluster ends up empty (can happen with poor initialisation), its centroid
     * remains at the origin [0, 0, 0] — it will attract no new points and effectively
     * disappears from the next iteration. With k=3 and a reasonable player base this
     * edge case is unlikely; the fixed seed further mitigates it.
     */
    private function recomputeCentroids(array $normalized, array $labels): array
    {
        $dims      = count($normalized[0]);
        $centroids = array_fill(0, self::K, array_fill(0, $dims, 0.0));
        $counts    = array_fill(0, self::K, 0);

        // Accumulate dimension sums per cluster.
        foreach ($normalized as $i => $vec) {
            $k = $labels[$i];
            $counts[$k]++;
            foreach ($vec as $d => $v) {
                $centroids[$k][$d] += $v;
            }
        }

        // Divide by member count to get the mean (skip empty clusters).
        foreach ($centroids as $k => &$c) {
            if ($counts[$k] > 0) {
                foreach ($c as &$v) {
                    $v /= $counts[$k];
                }
            }
        }

        return $centroids;
    }
}
