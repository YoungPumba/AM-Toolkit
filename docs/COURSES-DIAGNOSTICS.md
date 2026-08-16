# Diagnostyka AM Courses

Status: wymagania obowiązujące od pierwszej wersji modułu kursów.

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
- `progress.recalculated`,
- `progress.repair_applied`,
- `meeting.updated`,
- `access.granted`,
- `access.revoked`,
- `access.expired`.

Każde zdarzenie ma stabilny typ, wersję formatu, unikalny klucz, czas UTC,
`request_id`, aktora, użytkownika, obiekt oraz ograniczony payload.

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

## Bezpieczne działania właścicielki

### Sprawdź integralność

Operacja tylko do odczytu. Porównuje granty, lekcje, ukończenia i agregat,
zwracając listę rozbieżności bez modyfikowania danych.

### Przelicz postęp

Operacja idempotentna. Odbudowuje agregat z ukończonych wymaganych lekcji i
zapisuje zdarzenie `progress.recalculated`.

### Eksport diagnostyczny

Tworzy plik tekstowy lub JSON bez sekretów, surowych linków Zoom, nonce,
tokenów, haseł i zbędnych danych osobowych. Eksport zawiera wersje, stany,
identyfikatory techniczne i powiązane `request_id`.

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

## Testy diagnostyki

- powtórzone żądanie zachowuje jeden efekt i rozpoznawalny klucz zdarzenia,
- równoległe żądania nie tworzą dwóch ukończeń,
- awaria zapisu zwraca `request_id` i trafia do logu technicznego,
- przeliczenie nie zmienia poprawnego wyniku,
- eksport nie zawiera sekretów ani danych spoza wybranego użytkownika,
- użytkownik bez capability nie odczyta ani nie uruchomi diagnostyki,
- wyłączenie funkcji flagą awaryjną nie usuwa istniejących danych.
