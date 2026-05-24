# Skill: Uwierzytelnienie, Role i Sesja
Implementuje rejestrację, logowanie, role i preferencje użytkownika.

**Klasy**:
```php
class AuthController {
    public function showRegister(): void;
    public function register(): void;   // POST → PRG
    public function showLogin(): void;
    public function login(): void;      // POST → PRG
    public function logout(): void;     // POST → PRG
}

class AuthMiddleware {
    public static function requireAuth(): void;           // redirect /login jeśli gość
    public static function requireRole(string $role): void; // 403 jeśli rola niewystarczająca
}
```

**Rejestracja** — pola formularza:
| Pole         | Typ       | Walidacja                                  |
|--------------|-----------|--------------------------------------------|
| first_name   | text      | wymagane, max 100 znaków                   |
| last_name    | text      | wymagane, max 100 znaków                   |
| email        | email     | wymagane, unikalny, format e-mail          |
| password     | password  | wymagane, min 8 znaków                     |
| avatar       | file      | opcjonalne; JPEG/PNG/GIF; max 5 MB         |

**Po rejestracji**:
1. Zahashuj hasło: `password_hash($password, PASSWORD_ARGON2ID)`.
2. Zapisz avatara: `uploads/avatars/{id}_{uniqid()}.ext`.
3. Nalicz bonus powitalny (patrz skill `promotions.md`).
4. Flash "Konto zostało założone. Zaloguj się." → redirect `/login`.

**Logowanie**:
1. Pobierz użytkownika po e-mailu (PDO, prepared statement).
2. Weryfikacja: `password_verify($input, $hash)`.
3. `session_regenerate_id(true)` przed ustawieniem sesji.
4. `$_SESSION['user'] = ['id' => ..., 'role' => ..., 'lang' => ...]`.
5. Redirect na `/dashboard` (user) lub `/admin` (admin).

**Middleware — użycie w każdym kontrolerze**:
```php
// Na początku każdej akcji wymagającej loginu:
AuthMiddleware::requireAuth();

// Na początku akcji tylko dla admina:
AuthMiddleware::requireRole('admin');
```

**Preferencje użytkownika**:
- Język: `users.lang_preference` (pl/en); aktualizowany przez `LanguageController::switch()`.
- Przy każdym loginie wczytaj preferencję do sesji: `$_SESSION['lang'] = $user->lang_preference`.

**Schemat tabeli**:
```sql
CREATE TABLE users (
    id              SERIAL PRIMARY KEY,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            VARCHAR(20)  NOT NULL DEFAULT 'user', -- 'user' | 'admin'
    balance         NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    avatar_path     VARCHAR(500),
    lang_preference VARCHAR(5)   NOT NULL DEFAULT 'pl',
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```
