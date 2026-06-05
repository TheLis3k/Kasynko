<?php

/**
 * Demo seeder — run inside the web container:
 *   docker compose exec web php database/seeder.php
 *
 * Creates test accounts (all pw: test123) + session history sized to
 * produce clear Wieloryb / Casual / Niedzielny Janusz clusters.
 * Safe to re-run: skips users that already exist by email.
 */

define('BASE_PATH', dirname(__DIR__));

// Bootstrap DB
$cfg = require BASE_PATH . '/config/config.php';
$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s',
    $cfg['db']['host'],
    $cfg['db']['port'],
    $cfg['db']['name']
);
$pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function say(string $msg): void { echo $msg . PHP_EOL; }

function insertUser(PDO $pdo, array $u): ?int
{
    $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $check->execute([':email' => $u['email']]);
    if ($row = $check->fetch()) {
        say("  SKIP  {$u['email']} (already exists, id={$row['id']})");
        return null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, email, password_hash, role, balance, lang_preference)
         VALUES (:fn, :ln, :email, :hash, :role, :balance, :lang)
         RETURNING id'
    );
    $stmt->execute([
        ':fn'      => $u['first_name'],
        ':ln'      => $u['last_name'],
        ':email'   => $u['email'],
        ':hash'    => password_hash($u['password'], PASSWORD_ARGON2ID),
        ':role'    => $u['role'] ?? 'user',
        ':balance' => $u['balance'] ?? 1000.00,
        ':lang'    => $u['lang']    ?? 'pl',
    ]);
    $id = (int)$stmt->fetchColumn();
    say("  NEW   {$u['email']} (id={$id})");
    return $id;
}

function gameId(PDO $pdo, string $type): int
{
    static $cache = [];
    if (!isset($cache[$type])) {
        $stmt = $pdo->prepare('SELECT id FROM games WHERE type = :type LIMIT 1');
        $stmt->execute([':type' => $type]);
        $cache[$type] = (int)$stmt->fetchColumn();
    }
    return $cache[$type];
}

/**
 * Insert N game sessions for a user.
 *
 * $profile = [
 *   'games'       => int,          // how many sessions to create
 *   'avg_bet'     => float,        // centre of gaussian bet distribution
 *   'bet_spread'  => float,        // ± spread around avg_bet
 *   'win_rate'    => float,        // 0.0–1.0
 *   'game_types'  => ['roulette', 'slots'],
 *   'days_back'   => int,          // spread sessions over last N days
 * ]
 */
function insertSessions(PDO $pdo, int $userId, array $profile): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO game_sessions
           (user_id, game_id, bet_type, bet_value, bet_amount, outcome, payout, created_at)
         VALUES
           (:uid, :gid, :btype, :bval, :amount, :outcome, :payout, :created_at)'
    );

    $betTypes = [
        'roulette' => ['color', 'parity', 'number'],
        'slots'    => ['bet'],
    ];
    $betValues = [
        'color'  => ['red', 'black'],
        'parity' => ['even', 'odd'],
        'number' => ['7', '13', '21', '36', '0'],
        'bet'    => ['5', '10', '20'],
    ];
    $payouts = [
        'color'  => 1.0,
        'parity' => 1.0,
        'number' => 35.0,
        'bet'    => 3.0,
    ];

    $daysBack = $profile['days_back'] ?? 90;
    mt_srand($userId * 31337); // deterministic per user

    for ($i = 0; $i < $profile['games']; $i++) {
        $gameType = $profile['game_types'][array_rand($profile['game_types'])];
        $gid      = gameId($pdo, $gameType);

        $availTypes = $betTypes[$gameType];
        $betType    = $availTypes[array_rand($availTypes)];
        $betValue   = $betValues[$betType][array_rand($betValues[$betType])];

        // Gaussian-ish bet amount
        $spread    = $profile['bet_spread'] ?? $profile['avg_bet'] * 0.3;
        $amount    = max(1.0, round($profile['avg_bet'] + (mt_rand(-100, 100) / 100.0) * $spread, 2));
        $win       = (mt_rand(0, 99) / 100.0) < $profile['win_rate'];
        $outcome   = $win ? 'win' : 'lose';
        $payout    = $win ? round($amount * $payouts[$betType], 2) : 0.00;

        // Random timestamp in the last $daysBack days
        $secsBack  = mt_rand(0, $daysBack * 86400);
        $createdAt = date('Y-m-d H:i:s', time() - $secsBack);

        $stmt->execute([
            ':uid'        => $userId,
            ':gid'        => $gid,
            ':btype'      => $betType,
            ':bval'       => $betValue,
            ':amount'     => $amount,
            ':outcome'    => $outcome,
            ':payout'     => $payout,
            ':created_at' => $createdAt,
        ]);
    }

    say("         → {$profile['games']} sessions (avg_bet={$profile['avg_bet']}, win_rate={$profile['win_rate']})");
}

function grantWelcomeBonus(PDO $pdo, int $userId): void
{
    $promo = $pdo->query("SELECT id, amount FROM promotions WHERE type='welcome' AND is_active LIMIT 1")->fetch();
    if (!$promo) return;

    $has = $pdo->prepare(
        "SELECT COUNT(*) FROM user_promotions up
         JOIN promotions p ON p.id = up.promotion_id
         WHERE up.user_id = :uid AND p.type = 'welcome'"
    );
    $has->execute([':uid' => $userId]);
    if ((int)$has->fetchColumn() > 0) return;

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET balance = balance + :amt WHERE id = :id')
        ->execute([':amt' => $promo['amount'], ':id' => $userId]);
    $pdo->prepare(
        'INSERT INTO user_promotions (user_id, promotion_id, amount_awarded, note)
         VALUES (:uid, :pid, :amt, :note)'
    )->execute([':uid' => $userId, ':pid' => $promo['id'], ':amt' => $promo['amount'], ':note' => 'Bonus powitalny (seeder)']);
    $pdo->commit();
}

function grantDeposit(PDO $pdo, int $userId, float $depositAmt): void
{
    $promo = $pdo->query("SELECT id, rate FROM promotions WHERE type='deposit' AND is_active LIMIT 1")->fetch();
    if (!$promo || (float)$promo['rate'] <= 0) return;

    $bonus = round($depositAmt * (float)$promo['rate'], 2);
    $total = $depositAmt + $bonus;

    $pdo->beginTransaction();
    $pdo->prepare('UPDATE users SET balance = balance + :amt WHERE id = :id')
        ->execute([':amt' => $total, ':id' => $userId]);
    $pdo->prepare(
        'INSERT INTO user_promotions (user_id, promotion_id, amount_awarded, note)
         VALUES (:uid, :pid, :amt, :note)'
    )->execute([
        ':uid'  => $userId,
        ':pid'  => $promo['id'],
        ':amt'  => $bonus,
        ':note' => sprintf('Bonus od wpłaty %.2f (seeder)', $depositAmt),
    ]);
    $pdo->commit();
}

// ─── User definitions ─────────────────────────────────────────────────────────
//
// Carefully sized so K-means produces clean clusters:
//   Wieloryb       — high avg_bet (200–400), many games (35–60), high win_rate
//   Casual         — moderate (50–120), moderate games (12–28), medium win_rate
//   Niedzielny Janusz — low avg_bet (8–25), few games (2–5), any win_rate
//

$users = [
    // ── Wieloryby (whales) ──────────────────────────────────────────────────
    [
        'user'    => ['first_name'=>'Michał',   'last_name'=>'Kowalski',    'email'=>'michal.kowalski@demo.pl',   'password'=>'test123', 'balance'=>5000.00],
        'profile' => ['games'=>55, 'avg_bet'=>350.0, 'bet_spread'=>80.0,  'win_rate'=>0.55, 'game_types'=>['roulette'],         'days_back'=>120],
        'deposit' => 2000.0,
    ],
    [
        'user'    => ['first_name'=>'Anna',     'last_name'=>'Wiśniewska', 'email'=>'anna.wisniewska@demo.pl',   'password'=>'test123', 'balance'=>4200.00],
        'profile' => ['games'=>42, 'avg_bet'=>280.0, 'bet_spread'=>60.0,  'win_rate'=>0.52, 'game_types'=>['roulette','slots'], 'days_back'=>90],
        'deposit' => 1500.0,
    ],
    [
        'user'    => ['first_name'=>'Rafał',    'last_name'=>'Dąbrowski',  'email'=>'rafal.dabrowski@demo.pl',   'password'=>'test123', 'balance'=>6100.00],
        'profile' => ['games'=>38, 'avg_bet'=>400.0, 'bet_spread'=>100.0, 'win_rate'=>0.48, 'game_types'=>['roulette'],         'days_back'=>60],
        'deposit' => 0.0,
    ],

    // ── Casual ──────────────────────────────────────────────────────────────
    [
        'user'    => ['first_name'=>'Piotr',    'last_name'=>'Nowak',      'email'=>'piotr.nowak@demo.pl',        'password'=>'test123', 'balance'=>1200.00],
        'profile' => ['games'=>22, 'avg_bet'=>85.0,  'bet_spread'=>25.0,  'win_rate'=>0.45, 'game_types'=>['roulette','slots'], 'days_back'=>90],
        'deposit' => 500.0,
    ],
    [
        'user'    => ['first_name'=>'Katarzyna','last_name'=>'Lewandowska','email'=>'kasia.lewandowska@demo.pl',  'password'=>'test123', 'balance'=>900.00],
        'profile' => ['games'=>28, 'avg_bet'=>65.0,  'bet_spread'=>20.0,  'win_rate'=>0.50, 'game_types'=>['slots'],            'days_back'=>120],
        'deposit' => 300.0,
    ],
    [
        'user'    => ['first_name'=>'Tomasz',   'last_name'=>'Wójcik',    'email'=>'tomasz.wojcik@demo.pl',      'password'=>'test123', 'balance'=>1800.00],
        'profile' => ['games'=>18, 'avg_bet'=>110.0, 'bet_spread'=>30.0,  'win_rate'=>0.42, 'game_types'=>['roulette'],         'days_back'=>60],
        'deposit' => 0.0,
    ],
    [
        'user'    => ['first_name'=>'Monika',   'last_name'=>'Zielińska', 'email'=>'monika.zielinska@demo.pl',   'password'=>'test123', 'balance'=>750.00],
        'profile' => ['games'=>15, 'avg_bet'=>75.0,  'bet_spread'=>20.0,  'win_rate'=>0.47, 'game_types'=>['roulette','slots'], 'days_back'=>45],
        'deposit' => 200.0,
    ],

    // ── Niedzielny Janusz ────────────────────────────────────────────────────
    [
        'user'    => ['first_name'=>'Marek',    'last_name'=>'Kamiński',  'email'=>'marek.kaminski@demo.pl',     'password'=>'test123', 'balance'=>1050.00],
        'profile' => ['games'=>3,  'avg_bet'=>15.0,  'bet_spread'=>5.0,   'win_rate'=>0.33, 'game_types'=>['slots'],            'days_back'=>30],
        'deposit' => 0.0,
    ],
    [
        'user'    => ['first_name'=>'Agnieszka','last_name'=>'Szymańska', 'email'=>'agnieszka.szymanska@demo.pl','password'=>'test123', 'balance'=>1000.00],
        'profile' => ['games'=>2,  'avg_bet'=>20.0,  'bet_spread'=>5.0,   'win_rate'=>0.50, 'game_types'=>['roulette'],         'days_back'=>14],
        'deposit' => 0.0,
    ],
    [
        'user'    => ['first_name'=>'Bartosz',  'last_name'=>'Mazur',     'email'=>'bartosz.mazur@demo.pl',      'password'=>'test123', 'balance'=>1010.00],
        'profile' => ['games'=>4,  'avg_bet'=>10.0,  'bet_spread'=>3.0,   'win_rate'=>0.50, 'game_types'=>['slots'],            'days_back'=>20],
        'deposit' => 0.0,
    ],
    [
        'user'    => ['first_name'=>'Ewa',      'last_name'=>'Krawczyk',  'email'=>'ewa.krawczyk@demo.pl',       'password'=>'test123', 'balance'=>1000.00],
        'profile' => ['games'=>2,  'avg_bet'=>8.0,   'bet_spread'=>2.0,   'win_rate'=>0.00, 'game_types'=>['roulette'],         'days_back'=>7],
        'deposit' => 0.0,
    ],
];

// ─── Run ──────────────────────────────────────────────────────────────────────

say('');
say('╔══════════════════════════════════════╗');
say('║   Wirtualne Kasyno — Demo Seeder     ║');
say('╚══════════════════════════════════════╝');
say('');

$created = 0;
$skipped = 0;

foreach ($users as $def) {
    $expectedSegment = match(true) {
        $def['profile']['avg_bet'] >= 200 => 'Wieloryb',
        $def['profile']['games']  <= 5    => 'Niedzielny Janusz',
        default                           => 'Casual',
    };
    say("[{$expectedSegment}] {$def['user']['first_name']} {$def['user']['last_name']}");

    $uid = insertUser($pdo, $def['user']);
    if ($uid === null) {
        $skipped++;
        continue;
    }
    $created++;

    grantWelcomeBonus($pdo, $uid);
    insertSessions($pdo, $uid, $def['profile']);

    if ($def['deposit'] > 0) {
        grantDeposit($pdo, $uid, $def['deposit']);
        say("         → deposit +{$def['deposit']} (with 10% bonus)");
    }

    say('');
}

say('─────────────────────────────────────────');
say("Created: {$created}  Skipped (already exist): {$skipped}");
say('');
say('Login credentials for all demo accounts:');
say('  password: test123');
say('');
say('Quick reference:');
say('  admin@kasynko.pl  / admin123   → admin panel');
say('  gracz@kasynko.pl  / gracz123   → regular user');
say('');
say('Demo accounts by expected K-means cluster:');
say('  Wieloryb       → michal.kowalski@demo.pl, anna.wisniewska@demo.pl, rafal.dabrowski@demo.pl');
say('  Casual         → piotr.nowak@demo.pl, kasia.lewandowska@demo.pl, tomasz.wojcik@demo.pl, monika.zielinska@demo.pl');
say('  Niedzielny Janusz → marek.kaminski@demo.pl, agnieszka.szymanska@demo.pl, bartosz.mazur@demo.pl, ewa.krawczyk@demo.pl');
say('');
say('After running the seeder:');
say('  1. Log in as admin → /admin/segments → click "Uruchom klastrowanie"');
say('  2. Verify Wieloryb/Casual/Niedzielny Janusz match expectations above');
say('  3. /admin/promotions → award reward to a top spender');
say('  4. /bets → test search, filters (date range, game type, min/max amount), sort, pagination, export CSV/JSON');
say('  5. /profile/deposit → top up and see 10% bonus applied');
say('  6. Register a new account → welcome bonus +500 auto-applied');
say('');
