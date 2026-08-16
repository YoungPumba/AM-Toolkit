# Postęp i ukończenie AM Courses

## Zakres VIA-44 i VIA-74

Postęp uczestniczki ma trzy stany: `no_record`, `started` i `completed`.
Samo otwarcie lekcji nie tworzy postępu. Stan `started` powstaje dopiero po
zapisaniu obejrzanego fragmentu filmu albo potwierdzeniu wymaganego zadania.

Warunki ukończenia są edytowane przy lekcji:

- `video_percent` od `0` do `100` określa wymagane pokrycie filmu; `0` wyłącza
  ten warunek,
- `task_required` wymaga świadomego potwierdzenia wykonania zadania,
- lekcja bez obu warunków udostępnia jawny przycisk „Oznacz jako ukończoną”.

W VIA-74 pojedyncze potwierdzenie zostało rozszerzone o uporządkowaną checklistę.
Właścicielka może przy lekcji dodać, edytować, publikować i archiwizować dowolną
liczbę krótkich czynności. Każda pozycja ma stabilny UUID oraz status „wymagana”
albo „opcjonalna”. Uczestniczka wyłącznie zaznacza wykonanie; MVP nie zbiera
plików, odpowiedzi tekstowych, notatek ani innych treści użytkownika.

Opublikowana checklista ma pierwszeństwo przed starszym przełącznikiem
`task_required`. Gdy lekcja nie ma pozycji checklisty, dotychczasowy tryb
pojedynczego potwierdzenia nadal działa, co zachowuje zgodność istniejących
kursów.

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
6. ocenia łącznie warunek filmu i wszystkie wymagane pozycje checklisty.

Checkpointy kilku kart lub urządzeń nie nadpisują się. Powtórzone żądanie z
tym samym `request_id` ma jeden efekt. Nie zapisujemy surowej telemetrii
`timeupdate`, adresu IP ani historii przewijania.

Stan każdej pozycji checklisty jest zapisywany osobno dla użytkownika i może
zostać zaznaczony lub odznaczony do czasu ukończenia lekcji. Ukończona lekcja
pozostaje trwałym faktem: jej checklista jest blokowana i późniejsza edycja
programu nie cofa zaliczenia.

Ręczne potwierdzenie starszego zadania i ręczne ukończenie lekcji także mają osobne,
niezmienne rekordy źródłowe. `CourseProgressService::rebuildLessonProgress()`
potrafi odbudować agregat lekcji również ze stanu checklisty.

## Tabele i trwałość

Migracja Courses v4:

- rozszerza `amt_lesson_progress` i `amt_course_completions` o `request_id`,
- tworzy `amt_lesson_video_checkpoints`,
- tworzy `amt_lesson_requirement_completions`.

Migracja Courses v7 dodaje:

- `amt_lesson_tasks` — stabilne definicje, kolejność, status i wymagalność,
- `amt_lesson_task_progress` — bieżący stan pozycji per użytkownik i lekcja.

Zmiana tytułu lub kolejności zachowuje stan tej samej pozycji. Archiwizacja i
utworzenie nowej pozycji nie przenosi zaznaczenia, ponieważ postęp odwołuje się
do technicznego identyfikatora, a nie tekstu ani miejsca na liście.

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

Procent widoczny w odznace lekcji dotyczy wyłącznie wymagań bieżącej lekcji.
Każdy wymagany warunek ma równy udział: film wnosi postęp proporcjonalnie do
osiągnięcia wymaganego progu, a każda wymagana pozycja checklisty wartość `0%`
albo `100%`. Pozycje opcjonalne są widoczne i zachowują stan, ale nie zmieniają
procentu ani automatycznego ukończenia. Lekcja bez
automatycznych wymagań pokazuje `0%` do ręcznego ukończenia. Ukończona lekcja
zawsze ma `100%`. Ukończenie innych lekcji nie zmienia tej wartości.

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
flaga `courses` nadal wyłącza cały moduł. Osobna flaga `courses-tasks` ukrywa
panel i checklistę oraz odłącza jej zapis bez usuwania definicji i zaznaczeń.

## Kontrola jakości

```powershell
composer check
node --check assets/js/course-player.js
```

Testy jednostkowe obejmują scalanie przedziałów, duplikaty, kilka urządzeń,
łączne wymagania, zaznaczenie i cofnięcie zadania, brak dostępu, zmiany
konfiguracji bez przenoszenia postępu, trwałą migawkę ukończenia, odbudowę
źródeł, autoryzację oraz wybór następnego działania. QA na
`klaudia-socials-local` potwierdza zapis checkpointu przez AJAX, zachowanie
postępu i checklisty po przeładowaniu oraz „Kontynuuj” w programie i hubie.
