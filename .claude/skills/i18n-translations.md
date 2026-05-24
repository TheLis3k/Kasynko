# Skill: Internacjonalizacja (i18n) — Polski / English
Implementuje prosty system tłumaczeń oparty na tablicach asocjacyjnych PHP.

**Struktura plików**:
```
lang/
  pl.php   ← domyślny język
  en.php
```

**Format pliku językowego**:
```php
// lang/pl.php
return [
    'auth.login'            => 'Zaloguj się',
    'auth.register'         => 'Zarejestruj się',
    'auth.logout'           => 'Wyloguj się',
    'nav.home'              => 'Strona główna',
    'nav.games'             => 'Gry',
    'nav.profile'           => 'Profil',
    'nav.admin'             => 'Panel admina',
    'game.roulette'         => 'Ruletka',
    'game.slots'            => 'Jednoręki Bandyta',
    'game.bet'              => 'Zakład',
    'game.spin'             => 'Zakręć',
    'game.win'              => 'Wygrana!',
    'game.loss'             => 'Przegrana',
    'promo.welcome_bonus'   => 'Bonus powitalny',
    'promo.deposit_bonus'   => 'Bonus od wpłaty',
    'flash.saved'           => 'Zapisano pomyślnie.',
    'flash.deleted'         => 'Usunięto pomyślnie.',
    'flash.error'           => 'Wystąpił błąd.',
    'error.404'             => 'Nie znaleziono strony.',
    'error.500'             => 'Wewnętrzny błąd serwera.',
    // ... wszystkie pozostałe klucze
];
```

**Helper `t($key, array $params = [])`**:
```php
function t(string $key, array $params = []): string {
    static $translations = null;
    if ($translations === null) {
        $lang = $_SESSION['lang'] ?? 'pl';
        $file = __DIR__ . "/../lang/{$lang}.php";
        $translations = file_exists($file) ? require $file : require __DIR__ . '/../lang/pl.php';
    }
    $text = $translations[$key] ?? $key;
    foreach ($params as $placeholder => $value) {
        $text = str_replace(":{$placeholder}", htmlspecialchars((string)$value), $text);
    }
    return $text;
}
```

**Zasady stosowania**:
- **Każdy** widoczny tekst w widoku przechodzi przez `<?= t('klucz') ?>`.
- Klucze: namespace.element (np. `auth.login`, `game.bet`, `error.404`).
- Parametry dynamiczne: `t('flash.balance', ['amount' => 100])` → klucz `'flash.balance' => 'Dodano :amount zł'`.
- Nigdy nie hardcode'uj polskiego ani angielskiego tekstu bezpośrednio w widoku.

**Przełącznik języka**:
- Formularz GET `?lang=pl` lub `?lang=en` obsługiwany w `LanguageController::switch()`.
- Ustawia `$_SESSION['lang']` i zapisuje do `users.lang_preference` (jeśli zalogowany).
- Redirect z powrotem do poprzedniej strony (`$_SERVER['HTTP_REFERER']` lub `/`).
- Przełącznik widoczny w navbarze dla zalogowanych i niezalogowanych.

**Domyślny język**: `pl`. Fallback przy brakującym kluczu: zwróć klucz (nie wyrzucaj błędu).
