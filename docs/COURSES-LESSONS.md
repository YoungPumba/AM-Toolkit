# Lekcje, odtwarzacz i prywatne materiały AM Courses

## Zakres VIA-42

Widok lekcji jest dostępny pod adresem:

`/moje-konto/kursy/{course_uuid}/lekcja/{lesson_uuid}/`

Warstwa odczytu zwraca wyłącznie opublikowaną lekcję z aktualnej,
opublikowanej wersji programu. Przed odczytem lekcji i przy każdym żądaniu
filmu lub materiału ponownie sprawdzany jest aktywny dostęp uczestnika przez
AM Access Core. Identyfikatory magazynu nigdy nie są umieszczane w HTML ani w
publicznym adresie URL.

Widok zawiera:

- responsywny odtwarzacz MediaElement dostarczany przez WordPress,
- tytuł i opis lekcji oraz czytelny stan braku nagrania lub błędu,
- prywatne materiały do pobrania,
- nawigację do poprzedniej i następnej dostępnej lekcji,
- spis opublikowanego programu ze wskazaniem bieżącej lekcji.

Interfejs zachowuje widoczne stany fokusu, działa na wąskich ekranach i
respektuje `prefers-reduced-motion`. Skrypt `assets/js/course-player.js`
publikuje stabilne zdarzenia `play`, `pause`, `ended` i `error` jako
`amtoolkit:course-player`. Nie emituje surowego `timeupdate`, aby przyszły zapis
postępu nie wysyłał lawiny żądań.

Od VIA-44 odtwarzacz lokalnie scala faktycznie obejrzane przedziały i wysyła
idempotentny checkpoint co około 15 sekund oraz po pauzie, zakończeniu lub
opuszczeniu strony. Skok po osi czasu nie jest zaliczany. Szczegółowy kontrakt
opisuje [Postęp i ukończenie AM Courses](COURSES-PROGRESS.md).

## UX odtwarzacza i nawigacji — VIA-71

MediaElement pozostaje silnikiem i źródłem semantyki kontrolek. AM Toolkit
nakłada własny skin oraz dodaje dekoracyjne SVG do istniejących przycisków;
nie zastępuje ich checkboxami ani własnym, równoległym odtwarzaczem.

Skrypt odtwarzacza:

- pokazuje ośmiopunktowy loader wyłącznie dla rzeczywistych stanów ładowania,
  buforowania i przewijania,
- synchronizuje polskie etykiety oraz `aria-pressed` dla play/pause, dźwięku
  i fullscreen,
- w przeglądarkach udostępniających `screen.orientation.lock()` próbuje po
  wejściu w fullscreen przełączyć film do orientacji poziomej,
- ignoruje odrzucenie blokady orientacji i zawsze zachowuje użyteczny tryb
  pionowy; na iPhonie o orientacji może nadal decydować Safari i użytkownik,
- zwalnia blokadę orientacji po wyjściu z fullscreen.

Sticky spisu programu korzysta z obliczanej zmiennej
`--am-course-sticky-top`. Uwzględnia aktualną dolną krawędź widocznego headera
i bezpieczny odstęp, a przy niskim viewportcie otrzymuje własne przewijanie.
Poniżej breakpointu tabletowego sticky i ograniczenie wysokości są wyłączone.

Ikony akcji frontendowych są renderowane przez `CourseIcon`, używają
`currentColor`, nie zawierają powtarzalnych identyfikatorów SVG i pozostają
dekoracyjne. Dostępna nazwa akcji zawsze pochodzi z tekstu odnośnika lub
przycisku.

## Prywatny magazyn plików

Domyślny katalog magazynu to:

`dirname(ABSPATH)/am-toolkit-private`

Oznacza to katalog obok publicznego katalogu WordPressa, a nie wewnątrz
`wp-content/uploads`. Ścieżkę można zmienić stałą
`AM_TOOLKIT_PRIVATE_STORAGE_PATH` albo filtrem
`am_toolkit_private_storage_path`. Katalog musi być zapisywalny przez PHP i
pozostawać poza publicznym webrootem.

Właścicielka przesyła plik MP4 lub materiał z panelu kursów. W bazie zapisywany
jest nieprzewidywalny, wewnętrzny identyfikator, np.
`videos/{uuid}.mp4`. Nazwa i położenie źródłowego pliku nie są ujawniane.

Przed wdrożeniem produkcyjnym 0.12.0 trzeba potwierdzić z hostingiem:

- możliwość utworzenia prywatnego katalogu obok webrootu,
- limit rozmiaru pliku oraz wartości `upload_max_filesize` i `post_max_size`,
- limit czasu wysyłania i wykonania PHP,
- wystarczające miejsce na dysku oraz zasady kopii zapasowych.

## Chronione dostarczanie

Plik jest dostarczany przez akcję `admin-post.php`, która:

1. akceptuje tylko `GET` i `HEAD`,
2. wymaga zalogowanej sesji i nonce powiązanego z użytkownikiem, kursem,
   lekcją i zasobem,
3. ponownie sprawdza aktywny grant do kursu,
4. rozwiązuje identyfikator wyłącznie wewnątrz skonfigurowanego magazynu,
5. obsługuje pojedynczy nagłówek HTTP Range (`206`) oraz poprawną odpowiedź
   `416` dla błędnego zakresu,
6. przesyła plik porcjami i ustawia `Accept-Ranges`, `Content-Length`,
   `Content-Range`, prywatny cache przeglądarki oraz
   `X-Content-Type-Options: nosniff`.

Dla wideo otwarty zakres, np. `bytes=0-`, jest ograniczany do 128 MiB na jedną
odpowiedź. Ten rozmiar utrzymuje liczbę pełnych uruchomień WordPressa na niskim
poziomie, a jednocześnie ogranicza czas życia pojedynczego procesu PHP przy
bardzo dużym pliku. Jawne małe zakresy i zapytania o końcówkę pliku nie są
skracane, ponieważ przeglądarki wykorzystują je do odczytu metadanych MP4.
Przed wysłaniem danych kontroler zamyka ewentualną sesję PHP i wyłącza kompresję
wyjścia. Żądany zakres jest wysyłany porcjami z regularnym opróżnianiem bufora,
aby równoległe żądania nie blokowały się wzajemnie, a pierwsze dane trafiały do
przeglądarki bez oczekiwania na zgromadzenie całego zakresu. Odpowiedź może być
przechowana przez godzinę wyłącznie w prywatnym cache przeglądarki. `Vary:
Cookie` oddziela sesje logowania, a `ETag` i `Last-Modified` opisują wersję
pliku. Pobrane fragmenty nie trafiają do współdzielonych cache'ów, ale pozostają
dostępne przy ponownym przewijaniu w ramach tej samej sesji.

## Zalecany format nagrania

Instrukcja redakcyjna krok po kroku, obejmująca HandBrake, Fast Start,
bezpieczną podmianę i kontrolę istniejących plików, znajduje się w dokumencie
[Przygotowanie nagrań do AM Courses](COURSES-VIDEO-PREPARATION.md).

Materiał przeznaczony do odtwarzania w przeglądarce powinien być eksportowany
jako MP4 z obrazem H.264/AVC i dźwiękiem AAC, w rozdzielczości do 1920×1080,
25 lub 30 kl./s. Atom `moov` musi znajdować się przed danymi `mdat` (opcja
`faststart` / „web optimized”), aby przeglądarka nie pobierała końca dużego
pliku tylko po to, by poznać czas i indeks nagrania. Dla 1080p punktem wyjścia
jest bitrate obrazu ok. 4–6 Mb/s; należy go obniżyć, jeśli materiał pozostaje
czytelny, zamiast publikować źródło 4K bez korzyści dla kursantki.

Nowy upload jest sprawdzany przed przypisaniem do lekcji. Plik z atomem `moov`
za `mdat` zostaje odrzucony z instrukcją ponownego eksportu. Istniejące pliki w
tym układzie pozostają przypisane, ale panel lekcji pokazuje ostrzeżenie i prosi
o ich zastąpienie. Samo rozszerzenie `.mp4` nie jest dowodem, że nagranie nadaje
się do progresywnego odtwarzania — kontener też potrafi sabotować własny film.

Odtwarzacz używa `preload="auto"`. Na stronie pojedynczej lekcji przeglądarka
może dzięki temu zbudować bufor przy zapisanym punkcie wznowienia jeszcze przed
kliknięciem „Odtwórz”; ostateczny zakres pobierania nadal zależy od przeglądarki
i warunków sieciowych.

Żądanie osoby niezalogowanej, użytkownika bez dostępu lub nieistniejącego
zasobu kończy się odpowiedzią 404 bez potwierdzania, czy plik istnieje.

`CourseAssetStore` i `CourseVideoRenderer` są adapterami. Późniejsza migracja
do CDN lub zewnętrznego hostingu z podpisanymi adresami nie wymaga zmiany modelu
lekcji ani widoków; wymaga nowej implementacji adaptera i kontrolowanej migracji
referencji.

## Wycofanie i testy

Wyłączenie flagi `courses` usuwa endpointy i interfejs z ruchu bez kasowania
danych ani prywatnych plików.

Pełna kontrola repozytorium:

```powershell
composer check
```

Test integracyjny odczytu kursu i lekcji:

```powershell
php .build/test-course-hub-local.php <wp-load.php> <database-host:port>
```

Fixture przeglądarkowy może opcjonalnie skopiować lokalne nagranie MP4 do
prywatnego magazynu:

```powershell
php .build/course-hub-browser-fixture.php setup <wp-load.php> <database-host:port> <username> <password> <video.mp4>
```

Polecenie `cleanup` usuwa syntetycznego użytkownika, kursy i utworzone przez
fixture prywatne zasoby. Plik źródłowy pozostaje nietknięty.
