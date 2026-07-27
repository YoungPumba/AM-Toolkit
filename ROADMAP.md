# Roadmap AM Toolkit

## Zasady modułu „Moje konto”

- zachowujemy stylistykę Klaudia Socials: Poppins dla interfejsu, Buffalo dla głównych tytułów, akcent `#F176A4`, ciepłe beże i białe powierzchnie,
- podstawowy promień dużych kart i paneli wynosi `25px`,
- każdy etap kończymy testem na komputerze i telefonie,
- każdy etap sprawdzamy dla użytkownika niezalogowanego, zalogowanego bez zakupów oraz zalogowanego z zakupami,
- najpierw kończymy i zatwierdzamy aktualny widok, dopiero później przechodzimy do następnego modułu,
- funkcje planowane, lecz jeszcze niedostępne, oznaczamy etykietą „W budowie”,
- nowe wersje pakujemy wyłącznie według schematu opisanego w `.build/PACKAGING.md`.

## Powitanie panelu konta

- [x] animacja wyświetlana raz dziennie na głównym widoku konta,
- [x] pełnoekranowe przyciemnienie i pas w kolorze marki,
- [x] obsługa ograniczenia animacji ustawionego w systemie użytkownika,
- [ ] ustawienia wyglądu i częstotliwości w panelu AM Toolkit.

## Aktualny etap — Panel konta

- [x] responsywne kafelki szybkiego dostępu,
- [x] poziome przewijanie kafelków na telefonach,
- [x] dynamiczne powitanie i dane profilu,
- [x] podsumowanie „Wszystkie moje produkty”,
- [x] podgląd ostatnio nabytych produktów,
- [x] pełny widok wszystkich nabytych produktów,
- [x] endpoint `/moje-konto/moje-produkty/` z kursami, konsultacjami i plikami klienta,
- [x] ręczne przyznawanie i odbieranie produktów klientom przez administratora,
- [x] dynamiczna sekcja „Wymaga Twojej uwagi”,
- [x] dynamiczna sekcja „Ostatnie zamówienie”,
- [x] odnośniki i liczniki szybkiego dostępu.

## Audyt konta klienta — 27 lipca 2026

### Elementy działające poprawnie

- [x] panel główny poprawnie pobiera dane klienta, produkty, zamówienie i liczniki,
- [x] widok `/moje-konto/moje-produkty/` działa na komputerze i telefonie bez poziomego przewijania całej strony,
- [x] karuzela szybkiego dostępu przewija się poziomo na telefonie bez rozszerzania dokumentu,
- [x] chronione odnośniki do pobrania plików są generowane poprawnie,
- [x] widok „Moje produkty” ma prawidłową hierarchię nagłówków, opisy alternatywne obrazów i etykiety regionów.

### Problemy wymagające naprawy

- [ ] przywrócić zawartość podstawowych endpointów WooCommerce: `orders`, `view-order`, `edit-account` i `edit-address`,
- [ ] zweryfikować formularz logowania WooCommerce — standardowy formularz WordPress zalogował konto, natomiast formularz na stronie konta wymaga ponownego testu,
- [x] poprawić klasyfikację produktów przypisanych do podkategorii,
- [x] zaokrąglić avatar użytkownika niezależnie od obrazu zwracanego przez WordPress,
- [ ] dodać dostępny opis do ikony konta w nagłówku, np. `aria-label="Moje konto"`.

## v0.9.2 — dopracowanie widoku „Moje produkty”

- [x] mocniej oddzielić kategorie „Konsultacje”, „Kursy”, „Pliki do pobrania” i „Pozostałe produkty”,
- [x] dodać pod nagłówkiem kategorii krótką różową linię oraz delikatną linię na pozostałej szerokości,
- [x] zmniejszyć wysokość zdjęć z około `228px` do `190px` na komputerze,
- [x] zmniejszyć wysokość zdjęć z około `199px` do `170px` na telefonie,
- [x] zmniejszyć pionowe odstępy wewnątrz kart bez pogarszania czytelności,
- [x] zachować trzy kolumny na dużym ekranie, dwie na tablecie i jedną na telefonie,
- [x] zachować obecny wygląd i działanie przycisków pobierania,
- [x] obsłużyć przypisanie produktu do kategorii nadrzędnej lub jej podkategorii,
- [ ] sprawdzić długie nazwy produktów i plików,
- [ ] sprawdzić kategorie puste, pojedynczą kartę oraz wiele kart,
- [ ] przetestować linki produktu i chronione pobieranie plików,
- [ ] wykonać test wizualny przy szerokościach `360px`, `768px`, `1024px` i co najmniej `1440px`.

## v0.9.3 — dedykowane obrazy i kosmetyka panelu

- [x] dodać w edycji produktu WooCommerce pole „Obraz w panelu «Moje produkty»” z wyborem z Biblioteki mediów,
- [x] pod polem wyświetlić informację o zalecanym formacie: proporcje około `1.9:1`, rekomendowane `1200 × 630 px` lub `1600 × 840 px`, najlepiej WebP,
- [x] zastosować kolejność awaryjną: dedykowany obraz panelu → główna miniatura produktu → obraz zastępczy WooCommerce,
- [x] używać dedykowanego obrazu wyłącznie w panelu konta, bez zmiany strony produktu i katalogu sklepu,
- [x] usunąć różowy fragment separatora kategorii i pozostawić delikatną jasnoszarą linię,
- [x] usunąć koła, obramowania i cienie ze strzałek kafelków szybkiego dostępu,
- [x] pozostawić samą różową strzałkę z czytelnym stanem `hover` i `focus-visible`,
- [ ] przetestować obrazy ustawione, brak obrazu dodatkowego oraz całkowity brak miniatury,
- [ ] sprawdzić szybki dostęp na komputerze i telefonie.

## v0.10.0 — podstawowe endpointy konta

- [ ] wyświetlić listę zamówień na `/moje-konto/orders/`,
- [ ] wyświetlić szczegóły zamówienia na `/moje-konto/view-order/{id}/`,
- [ ] wyświetlić formularz danych konta na `/moje-konto/edit-account/`,
- [ ] wyświetlić formularz adresów na `/moje-konto/edit-address/`,
- [ ] przygotować spójne puste widoki i komunikaty błędów,
- [ ] sprawdzić wszystkie odnośniki prowadzące z panelu głównego,
- [ ] przetestować endpointy na komputerze i telefonie.

## v0.11.0 — nawigacja konta

- [ ] kliknięcie ikony niezalogowanego użytkownika prowadzi do logowania i rejestracji,
- [ ] kliknięcie ikony zalogowanego użytkownika otwiera menu konta,
- [ ] jednakowe zachowanie na komputerze i telefonie,
- [ ] obsługa klawiatury, zamykania poza panelem i klawiszem Escape,
- [ ] dostępna nazwa przycisku i poprawne atrybuty `aria-expanded` oraz `aria-controls`.

## Odzyskiwanie i ustawianie hasła

- [x] uzupełnić pusty widok otwierany z odnośnika „Ustaw nowe hasło” w wiadomości rejestracyjnej,
- [x] wyświetlić formularz nowego hasła dla poprawnego klucza WooCommerce,
- [x] obsłużyć nieprawidłowy, wykorzystany lub wygasły odnośnik,
- [x] dodać komunikat po poprawnym zapisaniu hasła i przejście do konfiguracji konta,
- [x] formularz imienia, nazwiska, nazwy wyświetlanej i opcjonalnego telefonu,
- [x] dopasować wygląd formularza oraz komunikatów do panelu konta,
- [ ] przetestować cały proces w trybie niezalogowanym na komputerze i telefonie.

## v0.12.0 — Konsultacje i terminy

- [ ] kafelek pozostaje oznaczony jako „W budowie” do czasu ukończenia modułu,
- [ ] lista kupionych konsultacji,
- [ ] status wykorzystania konsultacji,
- [ ] termin najbliższego spotkania,
- [ ] odnośnik do rezerwacji lub zmiany terminu,
- [ ] historia konsultacji.

## v0.13.0 — Dokumenty i obsługa zamówień

- [ ] dokumenty zakupu i faktury,
- [ ] centrum pomocy,
- [ ] zwroty i reklamacje,
- [ ] czytelne statusy zgłoszeń.

## v0.14.0 — Korzyści klienta

- [ ] kupony przypisane do użytkownika,
- [ ] indywidualne oferty,
- [ ] rekomendacje na podstawie posiadanych produktów,
- [ ] wygasanie i warunki wykorzystania korzyści.

## v1.0.0 — stabilny moduł konta

- [ ] kompletne testy wszystkich endpointów konta,
- [ ] pełna obsługa klawiatury i czytników ekranu,
- [ ] spójne stany ładowania, puste widoki, błędy i komunikaty,
- [ ] brak poziomego przewijania całej strony na obsługiwanych urządzeniach,
- [ ] panel ustawień najważniejszych elementów w AM Toolkit,
- [ ] dokumentacja administratora strony.
