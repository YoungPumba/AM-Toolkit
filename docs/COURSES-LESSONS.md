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
   `Content-Range`, brak cache oraz `X-Content-Type-Options: nosniff`.

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
