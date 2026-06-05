<?php

namespace App\Services;

use PDO;

class KMeansService
{
    private const K         = 3;
    private const MAX_ITER  = 100;
    private const SEED      = 42;

    public function __construct(private PDO $db) {}

    /**
     * Run k-means on all players, persist results, return labelled rows.
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

    /** @param array[] $vectors each has avg_bet, total_games, win_loss_ratio */
    private function kmeans(array $vectors): array
    {
        $n          = count($vectors);
        $normalized = $this->normalize($vectors);

        // Seeded random init
        srand(self::SEED);
        $indices  = array_rand($normalized, self::K);
        $centroids = array_map(fn(int $i) => $normalized[$i], $indices);

        $labels = array_fill(0, $n, 0);
        for ($iter = 0; $iter < self::MAX_ITER; $iter++) {
            $newLabels = [];
            foreach ($normalized as $vec) {
                $newLabels[] = $this->nearest($vec, $centroids);
            }
            if ($newLabels === $labels) {
                break;
            }
            $labels    = $newLabels;
            $centroids = $this->recomputeCentroids($normalized, $labels);
        }

        return $labels;
    }

    /** Map cluster index → segment name using centroid characteristics. */
    private function assignLabels(array $vectors, array $labels): array
    {
        $normalized = $this->normalize($vectors);
        $centroids  = $this->recomputeCentroids($normalized, $labels);

        // Use original-scale avg_bet for Wieloryb (highest avg_bet centroid)
        // and total_games for Niedzielny Janusz (lowest total_games centroid)
        $clusterAvgBet    = [];
        $clusterTotalGames = [];
        foreach (range(0, self::K - 1) as $k) {
            $members = array_keys(array_filter($labels, fn(int $l) => $l === $k));
            $clusterAvgBet[$k]     = empty($members) ? 0
                : array_sum(array_map(fn(int $i) => (float)$vectors[$i]['avg_bet'], $members)) / count($members);
            $clusterTotalGames[$k] = empty($members) ? 0
                : array_sum(array_map(fn(int $i) => (int)$vectors[$i]['total_games'], $members)) / count($members);
        }

        $wielorybCluster  = (int)array_keys($clusterAvgBet, max($clusterAvgBet))[0];
        $januszCluster    = (int)array_keys($clusterTotalGames, min($clusterTotalGames))[0];

        if ($wielorybCluster === $januszCluster) {
            // Tie-break: pick second-lowest for Janusz
            asort($clusterTotalGames);
            $sorted       = array_keys($clusterTotalGames);
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

    private function euclidean(array $a, array $b): float
    {
        $sum = 0.0;
        foreach ($a as $i => $v) {
            $sum += ($v - $b[$i]) ** 2;
        }
        return sqrt($sum);
    }

    /** @return array[] indexed vectors [avg_bet, total_games, win_loss_ratio] */
    private function normalize(array $vectors): array
    {
        $features = ['avg_bet', 'total_games', 'win_loss_ratio'];
        $mins = $maxs = [];

        foreach ($features as $f) {
            $vals   = array_column($vectors, $f);
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

    private function recomputeCentroids(array $normalized, array $labels): array
    {
        $dims      = count($normalized[0]);
        $centroids = array_fill(0, self::K, array_fill(0, $dims, 0.0));
        $counts    = array_fill(0, self::K, 0);

        foreach ($normalized as $i => $vec) {
            $k = $labels[$i];
            $counts[$k]++;
            foreach ($vec as $d => $v) {
                $centroids[$k][$d] += $v;
            }
        }

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
