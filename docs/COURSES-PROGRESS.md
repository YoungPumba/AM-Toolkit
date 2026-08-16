# Postęp i ukończenie AM Courses

## Zakres VIA-44

Postęp uczestniczki ma trzy stany: `no_record`, `started` i `completed`.
Samo otwarcie lekcji nie tworzy postępu. Stan `started` powstaje dopiero po
zapisaniu obejrzanego fragmentu filmu albo potwierdzeniu wymaganego zadania.

Warunki ukończenia są edytowane przy lekcji:

- `video_percent` od `0` do `100` określa wymagane pokrycie filmu; `0` wyłącza
  ten warunek,
- `task_required` wymaga świadomego potwierdzenia wykonania zadania,
- lekcja bez obu warunków udostępnia jawny przycisk „Oznacz jako ukończoną”.

Treść zadania pozostaje częścią redagowanej przez właścicielkę lekcji. MVP nie
zbiera odpowiedzi, notatek ani innych treści użytkownika.

## Źródło prawdy

Przeglądarka zbiera wyłącznie faktycznie odtworzone przedziały czasu. Skok po
pasku nie jest zaliczany. Przedziały są wysyłane porcjami co około 15 sekund
oraz po pauzie, zakończeniu i opuszczeniu strony.

Serwer:

1. ponownie sprawdza aktywny dostęp do kursu,
2. ogranicza przedziały do autorytatywnego czasu trwania filmu,
3. zapisuje niezmienny checkpoint z unikalnym `request_id`,
4. scala wszystkie przedziały danego użytkownika, lekcji i wersji treści,
5. liczy pokrycie bez podwójnego zaliczania nakładających się fragmentów,
6. ocenia łącznie warunek filmu i zadania.

Checkpointy kilku kart lub urządzeń nie nadpisują się. Powtórzone żądanie z
tym samym `request_id` ma jeden efekt. Nie zapisujemy surowej telemetrii
`timeupdate`, adresu IP ani historii przewijania.

Ręczne potwierdzenie zadania i ręczne ukończenie lekcji także mają osobne,
niezmienne rekordy źródłowe. `CourseProgressService::rebuildLessonProgress()`
potrafi z nich odbudować agregat lekcji.

## Tabele i trwałość

Migracja Courses v4:

- rozszerza `amt_lesson_progress` i `amt_course_completions` o `request_id`,
- tworzy `amt_lesson_video_checkpoints`,
- tworzy `amt_lesson_requirement_completions`.

`amt_lesson_progress` jest bieżącym agregatem użytkownika i lekcji. Zmiana
`content_version` wymusza ponowne spełnienie wymagań tej lekcji. Stare źródła
pozostają audytowalne, ale nie są doliczane do nowej wersji treści.

Po ukończeniu wszystkich wymaganych lekcji zapisujemy jeden
`CourseCompletion` dla użytkownika, kursu i wersji programu. Rekord zawiera
niezmienną migawkę identyfikatorów wymaganych lekcji i jej hash. Późniejsza
rozbudowa programu nie modyfikuje ani nie usuwa historycznego ukończenia.

## Następne działanie i UI

`CourseNextActionService` jest wspólnym źródłem decyzji dla widoków. Wybiera:

1. najnowszą rozpoczętą i nieukończoną lekcję,
2. pierwszą nieukończoną lekcję wymaganą,
3. pierwszą nieukończoną lekcję opcjonalną.

Hub pokazuje procent i prowadzi przyciskiem „Kontynuuj” bezpośrednio do
wybranej lekcji. Widok kursu pokazuje liczbę ukończonych wymaganych lekcji,
pasek postępu, wspólną akcję i stany pozycji programu. Widok lekcji aktualizuje
pokrycie filmu oraz wymagania bez przeładowania strony.

Procent kursu jest stosunkiem ukończonych wymaganych lekcji do wszystkich
wymaganych lekcji aktualnego programu. Historyczny rekord ukończenia pozostaje
trwałym faktem biznesowym.

## Bezpieczeństwo, diagnostyka i rollback

Zapis korzysta z zalogowanego AJAX, nonce, publicznych UUID i ponownej polityki
dostępu. Klient nie przesyła gotowego procentu ani statusu ukończenia.
Ukończenie zadania, lekcji i kursu zapisuje idempotentne zdarzenie domenowe z
`request_id`.

Flaga `courses-progress` wyłącza endpoint zapisu oraz elementy postępu bez
usuwania tabel, checkpointów, agregatów ani historycznych ukończeń. Główna
flaga `courses` nadal wyłącza cały moduł.

## Kontrola jakości

```powershell
composer check
node --check assets/js/course-player.js
```

Testy jednostkowe obejmują scalanie przedziałów, duplikaty, kilka urządzeń,
łączne wymagania, trwałą migawkę ukończenia, odbudowę ręcznego źródła,
autoryzację oraz wybór następnego działania. QA na
`klaudia-socials-local` potwierdza zapis checkpointu przez AJAX, zachowanie
postępu po przeładowaniu oraz „Kontynuuj” w programie i hubie.
