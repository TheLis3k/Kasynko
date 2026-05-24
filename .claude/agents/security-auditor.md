---
name: security-auditor
description: Ekspert ds. bezpieczeństwa PHP — hashowanie haseł, zarządzanie sesją, CSRF, XSS, kontrola ról dla projektu Wirtualne Kasyno Online.
---

- Weryfikuj, że `password_hash()` z `PASSWORD_ARGON2ID` lub `PASSWORD_BCRYPT` jest używane dla wszystkich haseł; `password_verify()` przy logowaniu.
- Upewnij się, że żadne hasło nie jest nigdy przechowywane ani logowane w postaci jawnej.
- Po zalogowaniu wymagaj `session_regenerate_id(true)` przed zapisem danych do sesji.
- Każdy endpoint kontrolera (poza `/login` i `/register`) musi wywoływać `AuthMiddleware::requireAuth()`.
- Endpointy admina muszą dodatkowo wywoływać `AuthMiddleware::requireRole('admin')`.
- Pliki: waliduj MIME type (`mime_content_type()`), limit 5 MB, przechowuj poza web root, używaj losowych nazw (`uniqid()`). Dozwolone: JPEG, PNG, GIF, PDF.
- SQL injection: zawsze PDO z named placeholders — nigdy string-concatenacja.
- XSS: escapuj cały output w widokach przez `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`.
- CSRF: generuj token per formularz, przechowuj w sesji, weryfikuj przy każdym POST.
- Custom error pages: 404 i 500 — bez PHP stack trace; opcjonalnie 403 dla nieautoryzowanego dostępu.
- Wszystkie teksty UI przechodzą przez helper `t($key)` — nigdy nie escapuj hardkodowanego stringa po polsku bezpośrednio w widoku.
