# Architektura AM Toolkit

Status: zaakceptowany kierunek rozwoju; fundament, cykl dostępu i panel
redakcyjny AM Courses wdrożone w VIA-29, VIA-39 oraz VIA-30.

## Cel

AM Toolkit ma pozostać jedną wtyczką WordPressa, ale jej moduły muszą być
od siebie odseparowane. Kursy, konto klienta, dostęp, powiadomienia i przyszłe
konsultacje nie mogą wymieniać danych przez przypadkowe wywołania klas albo
kopiowanie logiki.

## Zasady

1. Repozytorium Git jest jedynym źródłem kodu. Local korzysta z niego przez
   `Junction`; nie utrzymujemy drugiej, ręcznie synchronizowanej kopii.
2. Każdy moduł ma jedną odpowiedzialność, własne API i jawne zależności.
3. Logika domenowa nie renderuje HTML i nie zależy od Elementora.
4. Warstwa widoku nie decyduje o dostępie ani nie zapisuje postępu.
5. Stan trwały ma jedno źródło prawdy. Liczniki i procenty są danymi
   pochodnymi, które można bezpiecznie przeliczyć.
6. Wszystkie operacje zmieniające stan są autoryzowane, walidowane,
   idempotentne i możliwe do zdiagnozowania.
7. Opublikowanych migracji nie edytujemy. Błędy naprawiamy kolejną migracją.
8. Integracje z dostawcami zewnętrznymi przechodzą przez adaptery.

## Warstwy i kierunek zależności

```text
Bootstrap WordPressa
        ↓
Rejestr modułów
        ↓
Application / przypadki użycia
        ↓
Domain / reguły biznesowe
        ↓
Kontrakty infrastruktury
        ↓
WordPress, WooCommerce, baza danych, dostawcy zewnętrzni
```

Kod domenowy nie może zależeć od kontrolera endpointu, shortcode'u,
Elementora ani konkretnego odtwarzacza wideo. Integracja może zależeć od
kontraktu domenowego, ale nie odwrotnie.

## Moduły

Każdy moduł implementuje `ModuleInterface` i jest rejestrowany w jednym
`ModuleRegistry`. Rejestr rozwiązuje jawne zależności, sprawdza dostępność oraz
nie uruchamia modułu, jeśli jego zależność jest wyłączona lub niedostępna.

Planowane moduły:

- `Core` — bootstrap, rejestr, migracje, feature flags i wspólne narzędzia,
- `Access` — granty i polityki dostępu,
- `Activity` — dziennik zdarzeń oraz diagnostyka,
- `Account` — endpointy i komponenty konta klienta,
- `Courses` — kursy, programy, lekcje, spotkania i postęp,
- `Notifications` — toasty i kanały komunikacji,
- `WooCommerce` — mapowanie produktów, zamówień i zwrotów.

## Dane i migracje

Treść redakcyjna, którą właścicielka ma edytować w WordPressie, może korzystać
z typów wpisów i metadanych. Dla AM Courses wybrano jednak dedykowane tabele
zarówno katalogu, jak i danych transakcyjnych, ponieważ wersjonowany program,
porządek lekcji i snapshoty ukończenia wymagają jawnych ograniczeń unikalności.
Panel WordPressa jest klientem `CourseAdminService` oraz kontraktów magazynów,
nie alternatywnym magazynem i nie miejscem na bezpośrednie zapytania SQL.

Każdy moduł otrzyma własny numer schematu i sekwencyjne migracje. Migracja:

- jest bezpieczna przy ponownym uruchomieniu,
- nie zakłada, że poprzednia próba zakończyła się sukcesem,
- zapisuje wersję dopiero po zweryfikowaniu oczekiwanego schematu,
- nie usuwa danych bez osobnego, zatwierdzonego etapu serwisowego.

## Identyfikatory

Publiczne zasoby domenowe otrzymują trwały identyfikator niezależny od
`post_id`, kolejności i adresu URL. Zmiana nazwy, przeniesienie lekcji albo
archiwizacja nie może zerwać dostępu ani historii postępu.

Każda operacja zmieniająca stan otrzymuje `request_id`. Identyfikator jest
przekazywany do zdarzeń, logów i komunikatu błędu, aby połączyć działania
użytkownika z zapisem serwera.

## Dostęp i bezpieczeństwo

`AM Access Core` jest jedynym miejscem rozstrzygającym dostęp do zasobu.
Endpointy, pliki i odtwarzacze nie sprawdzają bezpośrednio zakupu produktu.

Autoryzacja obejmuje:

- aktywny grant do konkretnego zasobu,
- możliwości WordPressa dla operacji administracyjnych,
- nonce dla żądań zmieniających stan,
- sprawdzenie właściciela danych,
- ponowną kontrolę dostępu przy pobieraniu chronionego pliku.

Role interfejsu nie zastępują capabilities. Planowane możliwości to m.in.
zarządzanie kursami, uczestnikami, spotkaniami oraz diagnostyką.

## Zdarzenia i zadania asynchroniczne

Zdarzenia domenowe opisują fakt, który już nastąpił, i mają stabilne,
wersjonowane nazwy. Nie zastępują aktualnego stanu w bazie.

Powiadomienia, przypomnienia i integracje będą korzystać z kontraktu kolejki.
Zadania muszą być idempotentne i mieć unikalny klucz. Dla operacji, których
nie wolno zgubić pomiędzy zapisem danych a wysłaniem wiadomości, przewidujemy
wzorzec outbox.

## Adaptery integracji

Moduł kursów nie może znać szczegółów Vimeo, YouTube, Zooma czy Telegrama.
Przewidziane kontrakty:

- `VideoProvider`,
- `MeetingProvider`,
- `CalendarProvider`,
- `NotificationChannel`,
- `FileStorage`.

Pierwsza implementacja może być prosta, ale wymiana dostawcy nie może wymagać
przepisania logiki kursów.

## API, hooki i kompatybilność

Publiczne klasy, hooki, endpointy REST i formaty zdarzeń są wersjonowane.
Zmiana niekompatybilna wymaga okresu przejściowego albo nowej wersji API.
Komponenty Elementora będą korzystać z publicznych usług i modeli widoku,
a nie z prywatnych tabel.

## Pamięć podręczna

Cache nie jest źródłem prawdy. Dane użytkownika muszą mieć klucze zawierające
identyfikator użytkownika, zasobu i wersję danych. Po zmianie dostępu lub
postępu odpowiednie klucze są unieważniane.

## Feature flags i tryb awaryjny

Nowe moduły można włączać etapami. Krytyczne automatyzacje otrzymają osobny
przełącznik awaryjny, który zatrzymuje dalsze zapisy lub wysyłkę bez usuwania
danych i bez wyłączania całej wtyczki.

Aktualnie moduły można wyłączyć wpisem w opcji `am_toolkit_feature_flags`,
filtrem `am_toolkit_feature_enabled` lub stałą w `wp-config.php`, np.
`AM_TOOLKIT_DISABLE_ACCOUNT`. Stała `AM_TOOLKIT_SAFE_MODE` pozostawia aktywny
tylko fundament `Core` i `Access`, dzięki czemu interfejsowe integracje można
zatrzymać bez usuwania danych dostępu.

Moduł `Courses` jest domyślnie wyłączony do czasu ukończenia kolejnych warstw
MVP. Jego migracje nie usuwają danych, a sama flaga steruje wyłącznie bootem.
Automatyczne granty mają dodatkową, domyślnie wyłączoną flagę
`courses-access-automation`. Integracja WooCommerce jest adapterem modułu
Courses; domena nie zna klas zamówień ani subskrypcji.

## Testy wymagane przed wydaniem

- testy jednostkowe reguł domenowych,
- testy integracyjne repozytoriów i migracji,
- testy krytycznych przepływów WordPress/WooCommerce,
- testy uprawnień i prób dostępu do cudzych danych,
- kontrola PHP w najniższej wspieranej oraz docelowej wersji,
- test ręczny na komputerze i telefonie dla elementów interfejsu.

## Świadomie odłożone

Nie wdrażamy pełnego event sourcingu, mikroserwisów ani własnego frameworka
kontenerów. Aktualny stan pozostaje źródłem prawdy, a dziennik zdarzeń służy
audytowi, diagnostyce i integracjom. Architektura ma pomagać, nie organizować
konkurs na największą liczbę katalogów.
