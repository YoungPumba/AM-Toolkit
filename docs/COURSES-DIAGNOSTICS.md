# Diagnostyka AM Courses

Status: kontrakt wdrożony w VIA-46; diagnostyka pozostaje rozwijana razem z
modułem kursów.

## Cel

Błąd ma być możliwy do znalezienia i bezpiecznego naprawienia bez ręcznego
zgadywania w bazie danych. Diagnostyka nie jest dodatkiem „na później”; jest
częścią kontraktu każdego zapisu postępu, dostępu i spotkania.

## Dwa rodzaje zapisu

### Dziennik zdarzeń domenowych

Przechowuje istotne fakty biznesowe, np. ukończenie lekcji albo nadanie
dostępu. Jest czytelny dla panelu diagnostycznego i może zasilać przyszłe
automatyzacje.

### Log techniczny

Przechowuje wyjątki, błędy bazy, problemy dostawców i kontekst wykonania.
Korzysta z loggera WooCommerce, a gdy nie jest dostępny — z bezpiecznego
fallbacku WordPress/PHP. Nie należy mieszać pełnych stack trace'ów z tabelą
zdarzeń domenowych.

## Identyfikator żądania

Każda operacja zmieniająca stan otrzymuje `request_id`, np.
`AM-20260806-7F3C91A42D0B`. Ten sam identyfikator trafia do:

- odpowiedzi API lub bezpiecznego komunikatu błędu,
- zdarzenia domenowego,
- logu technicznego,
- zleconego zadania asynchronicznego,
- eksportu diagnostycznego i formularza pomocy.

Identyfikator nie zawiera adresu e-mail, loginu ani innych danych osobowych.

## Minimalny katalog zdarzeń kursu

- `course.started`,
- `course.completed`,
- `lesson.started`,
- `lesson.completed`,
- `lesson.completion_rejected`,
- `lesson.reopened`,
- `course.progress.recalculated`,
- `course.progress.recalculation_failed`,
- `meeting.updated`,
- `access.granted`,
- `access.revoked`,
- `access.expired`.

Każde zdarzenie ma stabilny typ, wersję formatu, unikalny klucz, czas UTC,
`request_id`, aktora, użytkownika, obiekt oraz ograniczony payload.

Zdarzenia spotkań zapisują tylko status, czas i flagi obecności prywatnych
odnośników. Wartości linków do spotkania, nagrania i Telegrama nie mogą znaleźć
się w payloadzie, logu technicznym ani eksporcie diagnostycznym.

## Minimalny panel diagnostyczny

Dla wybranego użytkownika i kursu właścicielka lub osoba z odpowiednią
capability widzi:

- aktywne granty, ich źródła i okresy ważności,
- bieżącą wersję programu kursu,
- liczbę wymaganych i ukończonych lekcji,
- zapisany agregat oraz wynik przeliczenia ze źródła prawdy,
- ostatnią otwartą i ostatnią ukończoną lekcję,
- ostatnie 20–50 zdarzeń,
- ostatni błąd zapisu i jego `request_id`,
- wersję AM Toolkit podczas ostatniej aktywności.

Panel domyślnie działa tylko do odczytu.

Wdrożony ekran znajduje się w **Kursy → Diagnostyka**. Odczyt wymaga
`view_am_toolkit_diagnostics`. Administrator otrzymuje osobne
`repair_am_toolkit_courses`; rola kierownika sklepu może diagnozować, ale nie
uruchamia przeliczenia. Dzięki temu możliwość zobaczenia problemu nie oznacza
automatycznie prawa do zmiany danych.

## Bezpieczne działania właścicielki

### Sprawdź integralność

Operacja tylko do odczytu. Porównuje granty, lekcje, ukończenia i agregat,
zwracając listę rozbieżności bez modyfikowania danych.

### Przelicz postęp

Operacja idempotentna. Ponownie ocenia każdą opublikowaną lekcję na podstawie
checkpointów nagrania, potwierdzeń i checklist, a następnie odbudowuje agregaty
lekcji i ukończenie kursu. Wymaga aktywnego dostępu uczestniczki, osobnej
capability, nonce oraz wpisania `PRZELICZ`. Wynik zapisuje zdarzenie
`course.progress.recalculated`.

Przed potwierdzeniem panel pokazuje liczbę lekcji, których źródła zostaną
ponownie ocenione. Awaria w połowie nie ukrywa błędu: zwraca `request_id`,
zapisuje bezpieczny log i pozostawia operację gotową do ponowienia. Ponowne
przeliczenie poprawnego stanu nie zmienia wyniku.

### Eksport diagnostyczny

Tworzy plik tekstowy lub JSON bez sekretów, surowych linków Zoom, nonce,
tokenów, haseł i zbędnych danych osobowych. Eksport zawiera wersje, stany,
identyfikatory techniczne i powiązane `request_id`.

Użytkownik jest oznaczony skrótem HMAC `user_ref`. Eksport celowo pomija adres
e-mail, login, identyfikator źródła zakupu, treści redakcyjne, payloady zdarzeń
i komunikaty mogące zawierać nazwy lekcji. Zdarzenia są ograniczone do typu,
obiektu, czasu i `request_id`.

## Kontrola schematu

Ekran sprawdza wersję migracji, obecność tabel kursów i dostępu oraz osierocone
lub sprzeczne relacje pomiędzy programem, lekcjami, agregatami, checklistami,
spotkaniami i grantami. Kontrola nie wykonuje napraw SQL. Taka naprawa wymaga
osobnego, przetestowanego scenariusza serwisowego — przycisk „napraw wszystko”
to nie diagnostyka, tylko loteria z ładniejszą etykietą.

Narzędzia zapisujące można natychmiast wyłączyć flagą
`courses-repair-tools` albo stałą `AM_TOOLKIT_DISABLE_COURSES_REPAIR_TOOLS`.
Diagnostyka tylko do odczytu pozostaje wtedy dostępna.

## Tryb serwisowy

Operacje destrukcyjne nie są dostępne w zwykłym panelu. Należą do nich:

- zbiorcza przebudowa agregatów,
- migracje i naprawy danych,
- bezpośrednia korekta ukończeń,
- masowe przyznawanie lub odbieranie dostępu.

Wymagają osobnej capability, jawnego potwierdzenia, pełnego audytu oraz — dla
zdalnej pomocy — dostępu ograniczonego czasowo. Każda naprawa zapisuje stan
przed i po operacji.

## Prywatność i retencja

- Logi nie przechowują haseł, tokenów, nonce ani pełnych adresów chronionych.
- Payload zdarzenia zawiera tylko dane potrzebne do audytu.
- Eksport diagnostyczny domyślnie pseudonimizuje użytkownika.
- Okres retencji logu technicznego jest krótszy niż historia zdarzeń
  biznesowych.
- Usuwanie lub anonimizacja konta uwzględnia tabele AM Toolkit.

## Obsługa incydentu

1. Zanotuj komunikat i `request_id`.
2. Sprawdź aktywny dostęp oraz wersję programu.
3. Uruchom kontrolę integralności bez zapisu.
4. Porównaj zdarzenie domenowe z logiem technicznym.
5. Jeśli źródło prawdy jest poprawne, wykonaj idempotentne przeliczenie.
6. Jeśli dane źródłowe są uszkodzone, przygotuj osobną naprawę i jej test.
7. Dopiero po weryfikacji produkcyjnej zamknij incydent.

Nigdy nie „naprawiamy procentu” bez ustalenia, które ukończenia doprowadziły
do rozbieżności. To tylko zamalowałoby kontrolkę na desce rozdzielczej.

## Diagnostyka odtwarzacza na telefonie

Tryb jest wbudowany w AM Toolkit i nie wymaga instalowania aplikacji na
telefonie. Działa wyłącznie dla zalogowanej uczestniczki, która ma dostęp do
danej lekcji, oraz tylko po jawnym dodaniu parametru do adresu:

```text
?am_course_diagnostics=1
```

Przykład:

```text
https://example.test/moje-konto/kursy/COURSE/lekcja/LESSON/?am_course_diagnostics=1
```

Pod odtwarzaczem pojawia się panel **Diagnostyka odtwarzacza**. Procedura:

1. Otwórz lekcję z parametrem diagnostycznym na telefonie.
2. Powtórz problem, np. odtwórz, zatrzymaj i ponownie wznów film.
3. Odczekaj kilka sekund po wystąpieniu zacięcia.
4. Wybierz **Pobierz raport diagnostyczny**.
5. Przekaż pobrany plik JSON do analizy.

Każde przeładowanie strony tworzy nową, losową sesję diagnostyczną. Dane
serwerowe są automatycznie usuwane po 30 minutach. Zapis Range działa tylko w
tym trybie, więc zwykłe odtwarzanie nie wykonuje dodatkowych zapisów do bazy.

Raport łączy chronologicznie dwa źródła:

- zdarzenia elementu `<video>` (`play`, `pause`, `playing`, `waiting`,
  `stalled`, `seeking`, `seeked`, `suspend`, `abort`, `error`) wraz ze stanem
  `readyState`, `networkState`, czasem UTC i zakresami bufora,
- początek i koniec żądań HTTP Range wraz ze statusem, zakresem, liczbą
  przesłanych bajtów, czasem odpowiedzi i informacją o przerwaniu połączenia;
  raport wskazuje też samą obecność oraz bezpieczną kategorię źródła nagłówka
  (`http_range`, `redirect_http_range`, `headers` albo `missing`), ale nigdy
  nie zapisuje jego surowej wartości.

Raport nie zawiera adresu pliku, nonce, cookies, hasła, adresu IP ani surowych
identyfikatorów użytkownika, kursu i lekcji. Identyfikatory są zastępowane
skrótami HMAC. User-Agent jest zachowany, ponieważ wersja iOS/Safari jest
niezbędna do odtworzenia błędu.

Jeżeli raport dużego pliku pokazuje powtarzające się pełne odpowiedzi `200`
zamiast odpowiedzi częściowych `206`, test należy przerwać: kolejne próby
zużywają transfer, ale nie dostarczają nowej informacji diagnostycznej.

### Porównanie standardowego i natywnego odtwarzacza

Funkcja jest przygotowywana po 0.12.4; same poniższe parametry **nie włączą
nowego odtwarzacza na niezmienionej instalacji 0.12.4**. Wdrożenie wymaga
osobnej akceptacji. Nie jest to jeszcze potwierdzona naprawa Safari.

- Wariant A, standardowy: `?am_course_diagnostics=1&am_course_player=mediaelement`.
- Wariant B, natywny: `?am_course_diagnostics=1&am_course_player=native`.

Sam `am_course_player=native`, bez diagnostyki, niczego nie przełącza.
Nieznana wartość wraca do MediaElement. Tryb nie jest dostępny w podglądzie
administratora; test wykonujemy jako zalogowany uczestnik z dostępem do
opublikowanej lekcji. Nie zmienia flag, programu, przypisania pliku ani
konfiguracji serwera. Zamknięcie karty i wejście przez zwykły adres przywraca
standardowy player; wybór nie jest zapisywany jako preferencja użytkownika.

Wariant B używa `<video controls playsinline preload="metadata">`, bez
inicjalizacji MediaElement, naszej nakładki ładowania i obsługi fullscreen.
**W obu wariantach pozostaje ten sam chroniony endpoint, sprawdzanie sesji,
dostępu i nonce, a także zwykły zapis postępu i przywracanie pozycji.** Testy
wykonuj na koncie testowym: oglądanie może zwiększać jego prawdziwy postęp.
Nie wystawiaj pliku MP4 publicznie i nie przesyłaj jego podpisanego adresu.

Procedura na fizycznym iPhonie:

1. Wybierz dokładnie tę samą lekcję, telefon, sieć i ustawienia CDN dla obu
   wariantów. Nie zmieniaj cache/CDN między A i B. Nie odtwarzaj jednocześnie
   w dwóch kartach. Zapisz stan CDN osobno — raport go nie potwierdza.
2. Otwórz świeżą kartę A i sprawdź etykietę „Wariant: standardowy”. Jeśli
   widzisz przywróconą pozycję, ręcznie wróć do początku. Nie czyść całego
   konta ani postępu. Obejrzyj 30 s, pauza 5 s, wznów na 15 s; przewiń w
   okolice 1:30 i odtwórz jeszcze 15–30 s. Fullscreen sprawdź na końcu.
3. Przy pierwszym zacięciu odczekaj najwyżej 10–15 s i pobierz raport.
   Nie powtarzaj kliknięć przez kilka minut: starsze zdarzenia wypadną z limitu.
   Zapisz też, czy obraz/dźwięk rzeczywiście poruszały się.
4. Zamknij A, otwórz świeżą kartę B. Sprawdź etykietę „Wariant: natywny”
   i wykonaj identyczne czynności, znów zaczynając od początku. Pobierz JSON.
5. Porównaj raporty i rzeczywisty obraz. Jeżeli wyniki się różnią, powtórz
   krótko w odwrotnej kolejności (B/A), aby ograniczyć wpływ rozgrzanego cache.
   Przerwij przy ponownych pełnych odpowiedziach lub wyraźnym obciążeniu.

Dodatkowe pola diagnostyczne (dane deklarowane przez klienta, nie dowód z serwera):

- `environment.player_mode`: `native` lub `mediaelement`; brak/nieznana
  wartość jest pustym tekstem, a nie domyślnie potwierdzonym wariantem.
- `environment.mediaelement_present`: obecność `.mejs-container` przy
  eksporcie. `true` w wariancie B oznacza, że izolacja playera nie zadziałała.
  `false` w A wymaga sprawdzenia, czy MediaElement w ogóle się zainicjalizował.
- `environment.client_events_dropped`: liczba zdarzeń usuniętych przez limit
  ostatnich 250 wpisów. Starsze raporty nie zawierają tego licznika.
- `client_events[].is_trusted`: `true`/`false` z właściwości zdarzenia lub
  `null` dla próbek stanu/braku danych. Nie ustala, czy użytkownik kliknął:
  zdarzenie przeglądarki może być następstwem wywołania API przez skrypt.
- `client_events[].seeking`: stan przewijania w momencie próbki.
- `diagnostics-export`: dodatkowa próbka stanu tuż przed pobraniem JSON.

Interpretacja: sukces B i awaria A wskazują na różnicę warstwy MediaElement/
naszych kontrolek, ale nie identyfikują automatycznie jednej wadliwej funkcji.
Awaria obu wariantów nie dowodzi winy hostingu: współdzielą MP4, transport
oraz zapis/wznawianie postępu. Wtedy sprawdzamy strukturę pliku, kompletność
odpowiedzi i wspólne zachowanie odtwarzania. `206`, `playing` ani brak
`error_code` osobno nie potwierdzają płynnego odtwarzania.

## Testy diagnostyki

- powtórzone żądanie zachowuje jeden efekt i rozpoznawalny klucz zdarzenia,
- równoległe żądania nie tworzą dwóch ukończeń,
- awaria zapisu zwraca `request_id` i trafia do logu technicznego,
- przeliczenie nie zmienia poprawnego wyniku,
- eksport nie zawiera sekretów ani danych spoza wybranego użytkownika,
- użytkownik bez capability nie odczyta ani nie uruchomi diagnostyki,
- wyłączenie funkcji flagą awaryjną nie usuwa istniejących danych.

Pełny zestaw testów jednostkowych i statycznych uruchamia `composer check`.
Kontrolę tylko do odczytu na lokalnym WordPressie wykonuje:

```powershell
php .build/test-course-diagnostics-local.php `
  "C:\sciezka\do\WordPressa\wp-load.php" `
  "127.0.0.1:PORT_BAZY"
```

Skrypt sprawdza schemat, wybiera ostatni aktywny grant kursu, odczytuje
diagnostykę oraz weryfikuje pseudonimizację eksportu. Nie uruchamia naprawy i
nie zmienia danych lokalnej uczestniczki.
