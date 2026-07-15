# Historia zmian AM Toolkit

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
