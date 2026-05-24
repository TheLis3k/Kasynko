# Architecture Rules
- **MVC separation**: Kontrolery nie zawierają SQL ani logiki biznesowej. Modele nie generują HTML.
- **No procedural files**: Każdy plik `.php` definiuje co najmniej jedną klasę.
- **DRY principle**: Powtarzająca się logika (linki paginacji, wyświetlanie flash messages, helper `t()`) wyodrębniona do klas bazowych lub funkcji pomocniczych.
- **No long functions**: Funkcje >30 linii dzielone na prywatne metody.
- **View templates**: Tylko `echo` i proste pętle/warunki. Żadnego `PDO`, `new Model()` ani złożonej logiki w plikach widoków (`.phtml` lub `.php` w katalogu views).
- **i18n**: Każdy tekst w widoku przez `<?= t('klucz') ?>`. Nigdy hardkodowany tekst po polsku ani angielsku bezpośrednio w HTML.
