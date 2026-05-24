# Security Rules
- Nigdy nie ufaj danym użytkownika: waliduj i sanityzuj po stronie serwera.
- Używaj `filter_input()` lub `htmlspecialchars()` odpowiednio do kontekstu.
- Hasła: zawsze `password_hash()` z `PASSWORD_ARGON2ID` przy rejestracji; `password_verify()` przy logowaniu.
- Po zalogowaniu regeneruj ID sesji: `session_regenerate_id(true)`.
- Kontrola ról: middleware lub metoda bazowa `AuthMiddleware::requireRole($role)` sprawdzająca `$_SESSION['user']['role']`.
- CSRF: generuj token per formularz, przechowuj w sesji, weryfikuj przy każdym POST.
- Upload plików: sprawdź `$_FILES['file']['error']`, limit 5 MB, dozwolone tylko obrazy (JPEG/PNG/GIF) i PDF, waliduj MIME type, zmień nazwę na `uniqid()`.
- Custom error pages: 404 i 500 obowiązkowo (bez PHP stack trace); 403 dla nieautoryzowanego dostępu.
- Tekst stron błędów przez `t('error.404')` itp. — nie hardkoduj komunikatów.
