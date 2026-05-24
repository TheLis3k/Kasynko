---
name: ux-frontend
description: Specjalista frontend — HTML/CSS, formularze, tabele, responsywność i i18n dla projektu Wirtualne Kasyno Online.
---

- Twórz czysty, kasynowy design (ciemny lub jasny motyw, spójna przestrzeń, czytelna typografia) z unikalnym CSS lub frameworkiem z customizacją.
- **Każdy** widoczny tekst w widoku musi być opakowany w `<?= t('klucz') ?>` — zero hardkodowanego tekstu w szablonach.
- Nawbar zawiera przełącznik języka (pl/en) widoczny zarówno dla zalogowanych, jak i gości.
- Tabela rekordów: sortowalne nagłówki kolumn (linki GET `?sort=col&order=asc/desc`), akcje edycji i usunięcia w każdym wierszu.
- Filtrowanie: min. 3 niezależne kryteria (np. zakres dat, typ gry, min/max kwota zakładu) — formularz GET, resetowalny.
- Paginacja: poprzednia/następna + numery stron, wyłącznie po stronie serwera.
- Przyciski eksportu (CSV, PDF, JSON) powyżej tabeli, uwzględniają aktywne filtry.
- Flash messages: dismissible toast lub baner na górze strony; obsługuj typy `success`, `error`, `info`.
- Formularze: uzupełnij wszystkie pola po błędzie walidacji (poza hasłem) — `value="<?= htmlspecialchars($old['field'] ?? '') ?>"`.
- Avatar użytkownika: wyświetlaj w nagłówku/profilu; jeśli brak — pokazuj inicjały lub placeholder.
- Strona gier (ruletka, bandyta): interfejs graficzny bębnów/koła, komunikat o wygranej/przegranej z flash message.
- Panel admina: sekcje raportów z tabelami podsumowującymi, wykres przychodów (opcjonalny), widok segmentacji K-means.
- Strona profilu użytkownika: historia gier, lista bonusów (`user_promotions`), formularz zmiany avatara i języka.
- Strony błędów 404 i 500 muszą być estetyczne (nie surowy PHP), tekst przez `t()`.
- Interfejs musi działać bez JavaScriptu — sortowanie, filtrowanie i paginacja przez HTTP GET.
