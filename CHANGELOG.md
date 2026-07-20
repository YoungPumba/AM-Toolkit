# Historia zmian AM Toolkit

## 0.5.6

- shortcode `[am_account_shortcut]` dla szybkich kafelków panelu,
- dynamiczna liczba zakupionych produktów i zamówień,
- informacja o kompletności podstawowych danych konta,
- bezpośrednie odnośniki do produktów, zamówień i edycji danych,
- osobny, nieaktywny stan „W budowie” dla konsultacji,
- dedykowane ikony SVG i obsługa klawiatury.

## 0.5.5

- shortcode `[am_account_attention]`,
- dynamiczne zadania dotyczące brakujących danych profilu i adresu rozliczeniowego,
- odnośnik do płatności za najnowsze nieopłacone zamówienie,
- klikalne elementy prowadzące bezpośrednio do właściwych sekcji konta,
- pozytywny komunikat, gdy konto nie wymaga działania.

## 0.5.4

- nieco większa typografia listy ostatnio kupionych produktów,
- shortcode `[am_account_last_order]`,
- dynamiczny numer, data, status i wartość ostatniego zamówienia,
- odnośniki do szczegółów zamówienia i pełnej historii,
- czytelny stan pusty dla konta bez zamówień.

## 0.5.3

- typografia Poppins dla powitania i profilu użytkownika,
- nieco większy tekst dynamicznego powitania,
- shortcode `[am_account_recent_products]`,
- lista maksymalnie trzech ostatnio kupionych, unikalnych produktów,
- obsługa pustego konta oraz produktów, które nie są już publicznie widoczne.

## 0.5.2

- dynamiczne powitanie zalogowanego użytkownika,
- shortcode `[am_account_greeting]`,
- dynamiczne imię, login i avatar użytkownika,
- shortcode `[am_account_profile]` z odnośnikiem do edycji konta,
- responsywny układ profilu zgodny z panelem konta.

## 0.5.1

- poprawione ograniczenie szerokości toru szybkich kafelków w kontenerach Elementora,
- przepełnienie poziome pozostaje wewnątrz karuzeli zamiast rozszerzać całą stronę,
- wzmocnione reguły szerokości kafelków na telefonach.

## 0.5.0

- pierwszy etap modułu panelu konta,
- responsywna lista kafelków szybkiego dostępu,
- poziome przewijanie dotykiem na telefonach,
- zatrzymywanie przewijania na kolejnych kafelkach (`scroll-snap`),
- czytelna szerokość kafelków bez ściskania ich zawartości,
- oznaczenie funkcji planowanych etykietą „W budowie”.

## 0.4.4

- domyślna typografia komunikatu checkoutu Poppins 14 px / 400,
- domyślna grubość odnośników 500,
- panel **AM Toolkit → Checkout** do konfiguracji wyglądu komunikatu,
- podgląd zmian kolorów, typografii, ramki i zaokrąglenia w kokpicie.

## 0.4.3

- styl podsumowania błędów walidacji nad formularzem zamówienia,
- białe tło, jasnoszara ramka i zaokrąglenie 25 px,
- typografia Poppins 18 px oraz wyróżnione odnośniki w kolorze marki.

## 0.4.2

- ukrycie standardowego linku WooCommerce „Zobacz koszyk” po dodaniu produktu przez AJAX,
- usuwanie tego linku również z DOM, aby akcję przejścia do koszyka obsługiwał wyłącznie Toast.

## 0.4.1

- natychmiastowa obsługa własnych przycisków `?add-to-cart=ID` przez AJAX,
- poprawiona aktualizacja fragmentów, licznika i wartości koszyka,
- niezawodne powiadomienia po kolejnych usunięciach produktów,
- zabezpieczenie przed duplikatami i nieaktualnymi komunikatami,
- zgodność kluczowych skryptów z odraczaniem i opóźnianiem JavaScript przez LiteSpeed Cache.

## 0.4.0

- przeniesienie shortcode’u `[custom_cart]` z Code Snippets do AM Toolkit,
- własna ikona koszyka z licznikiem i łączną wartością,
- odświeżanie danych koszyka przez fragmenty WooCommerce,
- animacja licznika po zmianie zawartości,
- responsywny wygląd koszyka w nagłówku.

## 0.3.0

- panel **AM Toolkit → Powiadomienia** w kokpicie WordPressa,
- edycja tytułów i treści komunikatów,
- obsługa zmiennej `{product_name}`,
- włączanie i wyłączanie typów powiadomień,
- konfiguracja czasu wyświetlania,
- podgląd powiadomienia i przywracanie ustawień domyślnych.

## 0.2.0

- integracja Toast Engine z WooCommerce,
- komunikaty po dodaniu i usunięciu produktu z koszyka,
- przekazywanie nazwy produktu i adresu koszyka do powiadomienia,
- ograniczenie standardowych komunikatów WooCommerce zastępowanych przez Toast.

## 0.1.0

- pierwszy niezależny Toast Engine,
- pozycja w prawym dolnym rogu i responsywna szerokość 360 px,
- radius 25 px i lekki cień,
- animacja slide, fade i scale,
- automatyczne zamykanie po 4 sekundach,
- pasek postępu, pauza po najechaniu i gest przesunięcia na telefonie,
- ikony SVG i obsługa `aria-live`,
- bazowy Design System i Motion System AM Toolkit.
