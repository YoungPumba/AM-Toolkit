# Historia zmian AM Toolkit

## Unreleased — AM Courses foundation

- rejestracja modułu `Courses` zależnego od `Core` i `Access`, domyślnie
  wyłączonego feature flagą,
- jawny model domeny kursu, wersji programu, sekcji, lekcji, materiału,
  spotkania, postępu i ukończenia,
- publiczne kontrakty repozytoriów, polityki dostępu i oceny ukończenia bez
  zależności od UI, Elementora i WooCommerce,
- dwie wersjonowane migracje tworzące sześć tabel katalogu i dwie tabele stanu
  transakcyjnego,
- trwałe publiczne UUID, snapshot wymaganych lekcji oraz ograniczenia
  unikalności chroniące przed duplikacją postępu i ukończeń,
- testy migracji pustej bazy, ponownego uruchomienia, ograniczeń integralności
  i podstawowych reguł domenowych.
- mapowanie wielu produktów WooCommerce na kursy w trzeciej migracji Courses,
- idempotentne granty zakupu, subskrypcji, ręczne i demo przez jeden kontrakt
  `AM Access Core`,
- selektywne cofanie wszystkich grantów danego źródła bez naruszania innych
  aktywnych źródeł dostępu,
- warunkowy adapter WooCommerce Subscriptions z jawną polityką statusów,
- stronicowana, wznawialna migracja opłaconych zamówień historycznych,
- osobna flaga awaryjna automatyzacji dostępu oraz wspólny `request_id` dla
  grantów utworzonych przez jedno zdarzenie.
- panel **Kursy** do tworzenia, publikowania i bezpiecznej archiwizacji kursów,
  sekcji, lekcji oraz materiałów bez fizycznego usuwania historii,
- niezmienne snapshoty opublikowanego programu i automatyczne tworzenie
  kolejnej wersji roboczej,
- edycja mapowań produktów, ręczny dostęp przez `AM Access Core` oraz widok
  uczestników i historii zdarzeń,
- osobne capability, nonce, walidacja danych i responsywny układ panelu.
- endpoint `/moje-konto/kursy/`, pozycja nawigacji i rozszerzenie kafelka
  szybkiego dostępu dostępne wyłącznie przy aktywnym module Courses,
- sekcja „Twoje kursy” przeznaczona nad kafelkami szybkiego dostępu,
  dostępna jako `[am_courses_dashboard]` i przez standardowy dashboard WooCommerce,
- bezpieczny hub aktywnych, ukończonych, zaplanowanych i wygasłych kursów
  wynikających wyłącznie z własnej historii grantów klientki,
- chroniony widok opublikowanego programu, który ponownie sprawdza aktywny
  dostęp w AM Access Core przed odczytem opisu, sekcji i lekcji,
- responsywne stany puste i błędne oraz odtwarzalny test integracyjny
  z syntetycznymi danymi i transakcyjnym `ROLLBACK`.
- wygląd części uczestniczki zgodny z systemem „Moje konto”: Buffalo, Poppins,
  akcent `#F176A4`, ciepłe tło, białe powierzchnie i promień 25 px.
- nazwy konkretnych kursów w Poppins z pełną obsługą polskich znaków oraz
  przerywane separatory porządkujące grupy kursów według statusu.

## 0.11.5 — 2026-08-11

- dodanie PHPUnit oraz analizy statycznej PHPStan dla kluczowych modułów wtyczki,
- wprowadzenie wspólnego identyfikatora `request_id` do śledzenia operacji zmieniających stan,
- rozdzielenie zdarzeń domenowych od technicznych logów błędów,
- wersjonowany i ograniczony kontrakt zdarzeń aktywności wraz z migracją istniejącej tabeli,
- bezpieczny interfejs odczytu zdarzeń bez bezpośrednich zapytań widoków do bazy danych,
- diagnostyka integralności i pseudonimizowany eksport danych bez możliwości modyfikowania stanu,
- testy kontraktu identyfikatorów, zdarzeń, filtrów diagnostycznych i bezpiecznego eksportu,
- poprawki wykryte przez analizę statyczną w modułach konta i integracji WooCommerce.

## 0.11.4 — 2026-08-06

- zastąpienie ręcznej listy `require_once` produkcyjnym autoloadingiem PSR-4,
- centralny `ModuleRegistry` z jawnymi zależnościami i kontrolą dostępności,
- moduły `Core`, `Access`, `WooCommerce` oraz `Account` uruchamiane w ustalonej kolejności,
- osobne, sekwencyjne migracje schematu dla modułów bez oznaczania nieudanej migracji jako wykonanej,
- centralne capabilities i polityki autoryzacji ustawień oraz ręcznego dostępu,
- feature flags, stałe wyłączające moduły i tryb awaryjny `AM_TOOLKIT_SAFE_MODE`,
- produkcyjny autoloader dołączany automatycznie do paczki ZIP,
- testy bootstrapu, rejestru modułów, mechanizmu migracji i capabilities.

## 0.11.3 — 2026-08-06

- opis docelowej architektury modułowej przed rozpoczęciem implementacji kursów,
- model domeny kursów, wersjonowania programu, dostępu i postępu,
- zasady diagnostyki, identyfikatorów żądań oraz bezpiecznych narzędzi naprawczych,
- wspólne ustawienia edytora i zakończeń linii dla pracy zespołowej,
- automatyczna kontrola składni PHP 8.0 i 8.3 oraz test AM Access Core w GitHub Actions,
- rozszerzenie `composer check` o kontraktowy test dostępu,
- aktualizacja PHP_CodeSniffer do wersji usuwającej znaną podatność bezpieczeństwa.

## 0.11.2

- wspólny fundament `AM Access Core` przygotowany dla kursów, konsultacji i chronionych materiałów,
- osobne, idempotentne granty dostępu pozwalające łączyć zakup, ręczne nadanie, migrację, pakiet i przyszłą subskrypcję,
- centralne API do sprawdzania, nadawania i odbierania dostępu bez uzależniania widoków od identyfikatora produktu WooCommerce,
- obsługa początku i wygaśnięcia dostępu oraz wielu niezależnych źródeł dostępu do tego samego zasobu,
- ponowne nadanie wcześniej cofniętego dostępu z tego samego źródła bez tworzenia zduplikowanego grantu,
- dziennik istotnych zdarzeń dostępu z unikalnymi kluczami i hookami dla przyszłych powiadomień, analityki i automatyzacji,
- wersjonowany instalator tworzący i aktualizujący tabele AM Toolkit przy aktywacji oraz aktualizacji wtyczki,
- dodany kontraktowy test idempotencji, wielu źródeł, wygaśnięcia, cofania i ponownego nadawania dostępu.

## 0.11.1

- identyczny rozmiar i wygląd ikony konta dla gościa oraz zalogowanego klienta,
- zabezpieczenie przycisku menu przed globalnym tłem, paddingiem i wymiarami przycisków motywu oraz Elementora,
- zmiana koloru wyłącznie samej ikony na różowy po najechaniu lub otwarciu menu,
- stała szerokość przycisku zapobiegająca rozsuwaniu mobilnego nagłówka.

## 0.11.0

- shortcode `[am_account_menu]` zastępujący statyczną ikonę konta w nagłówku,
- bezpośrednie przejście gościa do widoku logowania i rejestracji,
- rozwijane menu zalogowanego klienta z panelem głównym, produktami, zamówieniami, danymi i adresami,
- wyróżniony odnośnik „Kontakt i pomoc” prowadzący do kotwicy `#pomoc-konto`,
- responsywny panel odporny na przycinanie przez kontenery nagłówka Elementora,
- zamykanie poza panelem i klawiszem Escape, obsługa klawiatury oraz atrybuty dostępności,
- automatyczne pobieranie numeru wersji JavaScriptu z wersji wtyczki w PHP.

## 0.10.6

- dedykowany widok `/moje-konto/edit-address/` spójny z pozostałymi endpointami AM Toolkit,
- formularze adresu rozliczeniowego i dostawy dostępne jednocześnie na jednej stronie,
- pola krajów i regionów generowane dynamicznie przez WooCommerce,
- niezależny zapis każdego adresu przez standardową walidację, nonce i mechanizm zapisu WooCommerce,
- zachowanie wpisanych wartości po błędzie walidacji oraz czytelne komunikaty sukcesu i błędu,
- responsywny układ dwóch kart na komputerze i pojedynczej kolumny na tablecie oraz telefonie.

## 0.10.5

- poprawne kwadratowe kadrowanie świeżo wybranego avatara niezależnie od stylów obrazów motywu,
- komunikat informujący, że nowe zdjęcie oczekuje na zapisanie formularza,
- komunikat o zaplanowanym usunięciu własnego avatara,
- natychmiastowa walidacja typu JPG, PNG lub WebP oraz limitu 3 MB przed wysłaniem formularza,
- przywracanie aktualnego podglądu po anulowaniu wyboru albo wskazaniu nieprawidłowego pliku.

## 0.10.4

- małe, tekstowe przyciski „Pokaż” i „Ukryj” zgodne z formularzem odzyskiwania hasła,
- mocniejsze zabezpieczenie wyglądu przycisków przed globalnymi stylami motywu i WooCommerce,
- możliwość przesłania własnego avatara z poziomu danych konta,
- natychmiastowy podgląd wybranego zdjęcia przed zapisaniem formularza,
- obsługa obrazów JPG, PNG i WebP o rozmiarze do 3 MB,
- możliwość usunięcia własnego avatara i powrotu do Gravatara,
- automatyczne używanie własnego avatara we wszystkich elementach AM Toolkit korzystających z mechanizmu WordPressa,
- bezpieczne usuwanie wyłącznie plików avatara utworzonych dla danego użytkownika.

## 0.10.3

- dedykowany widok `/moje-konto/edit-account/` spójny wizualnie z biblioteką produktów i zamówieniami,
- bezpieczny zapis imienia, nazwiska, nazwy wyświetlanej, adresu e-mail oraz hasła przez standardowy mechanizm WooCommerce,
- opcjonalny numer telefonu zapisywany w danych rozliczeniowych klienta,
- sekcje podstawowych informacji i bezpieczeństwa z czytelną hierarchią oraz zaokrąglonym avatarem,
- lekkie przyciski „Pokaż” i „Ukryj” z obsługą klawiatury oraz atrybutami dostępności,
- responsywny układ pól, komunikatów walidacji i przycisku zapisu na telefonie,
- zachowanie wpisanych wartości formularza po błędzie walidacji.

## 0.10.2

- dedykowany widok `/moje-konto/view-order/{id}/` niezależny od szablonów ShopEngine,
- kontrola właściciela zamówienia chroniąca dane klienta przed odczytem po zmianie numeru w adresie,
- nagłówek ze statusem oraz podsumowanie daty, wartości, płatności i liczby pozycji,
- responsywna lista produktów z ilością, wariantami, ceną i odnośnikami do produktu,
- bezpieczne przyciski pobierania plików udostępnionych klientowi przez WooCommerce,
- podsumowanie kwot, przycisk płatności dla nieopłaconego zamówienia, adresy oraz notatka klienta,
- spójny komunikat dla nieistniejącego albo niedostępnego zamówienia.

## 0.10.1

- zachowanie komórki „Akcje” jako prawidłowej komórki tabeli i ciągłe tło całego wiersza na komputerze,
- osobny wewnętrzny kontener działań bez naruszania układu tabeli,
- wyrównanie odnośnika „Szczegóły” do kolumny wartości na telefonie,
- ograniczenie mobilnej etykiety statusu do szerokości jej treści.

## 0.10.0

- dedykowany widok `/moje-konto/orders/` renderowany niezależnie od szablonów ShopEngine,
- stylistyka zgodna z biblioteką „Moje produkty”: Buffalo, Poppins, biała powierzchnia, promień 25 px i różowe akcenty,
- responsywna tabela zamówień na komputerze oraz osobne karty na tablecie i telefonie,
- numer, data, produkty, typ produktu, status, kwota i działania dla każdego zamówienia,
- kolorystyczne etykiety statusów WooCommerce,
- filtrowanie według statusu i sortowanie według daty albo kwoty,
- bezpieczne odnośniki do szczegółów, płatności i plików cyfrowych udostępnionych przez WooCommerce,
- paginacja oraz czytelny stan pustej historii zamówień.

## 0.9.3

- dodatkowe pole „Obraz w panelu «Moje produkty»” w edycji produktu WooCommerce,
- wybór dedykowanej grafiki z Biblioteki mediów wraz z podglądem i możliwością usunięcia,
- opis zalecanych proporcji, rozdzielczości, formatu oraz bezpiecznych marginesów grafiki,
- kolejność awaryjna obrazu: dedykowana grafika panelu, główna miniatura produktu, obraz zastępczy WooCommerce,
- dedykowany obraz używany wyłącznie w bibliotece klienta bez zmiany katalogu i strony produktu,
- uproszczone separatory kategorii bez różowego fragmentu,
- same różowe strzałki szybkiego dostępu bez koła, obramowania i cienia.

## 0.9.2

- mocniejsze oddzielenie kategorii w bibliotece produktów za pomocą różowo-szarych separatorów,
- niższe zdjęcia i bardziej zwarte karty produktów na komputerze, tablecie i telefonie,
- zachowany układ trzech, dwóch i jednej kolumny zależnie od szerokości ekranu,
- klasyfikowanie produktów przypisanych do podkategorii właściwej grupy konta,
- zaokrąglony avatar użytkownika niezależnie od obrazu zwracanego przez WordPress,
- zachowany wygląd i działanie chronionych przycisków pobierania.

## 0.9.1

- poprawna polska odmiana licznika: „1 produkt”, „2–4 produkty” i „5+ produktów”,
- zdjęcia kart na telefonach wyświetlane nad treścią i dopasowane do szerokości ekranu,
- przyciski pobierania wszystkich plików udostępnionych klientowi przez WooCommerce,
- bezpieczne pobieranie plików z produktów ręcznie przyznanych przez AM Toolkit,
- kontrola zalogowanego użytkownika, przypisania produktu i ważności odnośnika przed wydaniem ręcznie przyznanego pliku.

## 0.9.0

- wyszukiwarka produktów WooCommerce w profilu użytkownika w panelu WordPress,
- ręczne przyznawanie i odbieranie produktów przez administratora lub kierownika sklepu,
- ręcznie przyznane produkty uwzględniane w endpointcie, licznikach i ostatnich produktach,
- zachowanie daty pierwszego ręcznego przypisania,
- rozróżnienie daty zakupu i daty przyznania produktu,
- tytuł „Moje produkty” zapisany krojem `buffalo-regular` w rozmiarze 46 px.

## 0.8.1

- dedykowany szablon endpointu „Moje produkty” zgodny z headerem i footerem motywu,
- obejście pustego widoku powodowanego przez nierozpoznawanie własnego endpointu przez ShopEngine,
- spójne tło, szerokość i odstępy widoku na komputerze oraz telefonie.

## 0.8.0

- własny endpoint `/moje-konto/moje-produkty/`,
- wszystkie kupione produkty podzielone na konsultacje, kursy i pliki do pobrania,
- usuwanie duplikatów produktów kupionych w kilku zamówieniach,
- responsywne karty produktów oraz czytelne stany pustych kategorii,
- pozycja „Moje produkty” w menu WooCommerce,
- wspólny adres dla podsumowania „Wszystkie moje produkty” i kafelka szybkiego dostępu,
- automatycznie klikalny cały kafel podsumowania zbudowany w Elementorze.

## 0.7.2

- poprawione dwukrotne odtwarzanie napisu `Welcome` w mobilnym Safari,
- rozdzielone rysowanie i wymazywanie ścieżki SVG na dwa niezależne etapy,
- globalna blokada ponownego uruchomienia animacji powitalnej na tej samej stronie.

## 0.7.1

- usunięta ikona WooCommerce nachodząca na treść komunikatu formularza,
- usunięte automatyczne zaznaczenie i obramowanie nagłówka,
- mniejsze, tekstowe przyciski pokazywania hasła bez tła i zbędnej wysokości,
- zabezpieczenie komunikatu kończącego konfigurację przed podwójnym wyświetleniem,
- globalne pomijanie identycznego Toastu, jeśli jego poprzednia kopia jest nadal widoczna,
- usuwanie parametru potwierdzenia z adresu po pokazaniu Toastu.

## 0.7.0

- kompletny widok ustawiania hasła otwierany z wiadomości rejestracyjnej WooCommerce,
- bezpieczne wykorzystanie klucza resetowania, nonce i mechanizmu logowania WooCommerce,
- obsługa nieprawidłowego, wykorzystanego i wygasłego odnośnika,
- formularz ponownego wysłania wiadomości z odnośnikiem do hasła,
- drugi krok aktywacji z imieniem, nazwiskiem i nazwą wyświetlaną,
- opcjonalny numer telefonu zapisywany w danych rozliczeniowych,
- wskaźnik siły hasła i możliwość pokazania wpisywanej wartości,
- automatyczne przejście do panelu oraz potwierdzenie przez Toast.

## 0.6.0

- powitalna animacja na głównym widoku panelu konta,
- poziomy pas w kolorze `#E9D7CA` i przyciemnienie rozchodzące się od środka ekranu,
- lekki, samodzielny renderer wektorowy korzystający z dostarczonego pliku `Welcome.json`,
- wyświetlanie raz dziennie dla danego użytkownika i przeglądarki,
- tryb podglądu dla administratora oraz awaryjny napis przy problemie z plikiem animacji,
- obsługa ustawienia systemowego ograniczającego animacje.

## 0.5.8

- shortcode `[am_account_products_summary]`,
- dynamiczne liczniki zakupionych konsultacji, kursów i plików,
- klasyfikacja według istniejących kategorii WooCommerce,
- współdzielona lista produktów ograniczająca powtarzanie zapytań na dashboardzie,
- wygładzona strzałka SVG w kolorze różowym na białym kółku.

## 0.5.7

- bardziej widoczne strzałki szybkich kafelków,
- okrągłe różowe tło, biały symbol i subtelny cień,
- mocniejsza reakcja odnośnika po najechaniu,
- zabezpieczenie opisu kafelka przed nachodzeniem na przycisk.

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
