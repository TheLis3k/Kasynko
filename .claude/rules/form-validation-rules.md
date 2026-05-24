# Form Validation & PRG Rules
- Formularze modyfikujące dane (CREATE/UPDATE/DELETE) używają metody **POST**; wyszukiwanie/filtrowanie używa **GET**.
- Po przetworzeniu POST zawsze redirect do GET (wzorzec PRG) — odświeżenie strony nie wysyła formularza ponownie.
- Błędy walidacji przechowuj w flash danych sesji i wyświetlaj na stronie po redirectie.
- Uzupełnij pola formularza z flash danych — nigdy bezpośrednio z `$_POST` po redirectie.
- Wyjątek: pola haseł nigdy nie są uzupełniane po redirectie — zawsze puste.
- Używaj klasy `Validation`, która zwraca tablicę błędów i oczyszczone wejście.
- Przełącznik języka (`?lang=pl/en`): GET z redirectem na poprzednią stronę (`HTTP_REFERER` lub `/`).
- Każdy komunikat błędu i sukcesu pochodzi z pliku tłumaczeń przez helper `t('klucz')` — zero hardkodowanego tekstu.
