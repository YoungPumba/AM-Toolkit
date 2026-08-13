# Model domeny AM Courses

Status: fundament domeny i schematu zaimplementowany w ramach `VIA-29` dla
wydania `v0.12.0`. Panel, widoki, automatyczne granty i obsługa spotkań należą
do kolejnych zadań.

## Granica modułu

AM Courses jest modułem AM Toolkit. Zależy wyłącznie od `Core` i `Access`, jest
rejestrowany w centralnym `ModuleRegistry` i domyślnie pozostaje wyłączony flagą
`courses`. Można go włączyć opcją `am_toolkit_feature_flags`, filtrem
`am_toolkit_feature_enabled` albo wyłączyć stałą `AM_TOOLKIT_DISABLE_COURSES`.

Wyłączenie modułu zatrzymuje jego uruchamianie. Migracje pozostają bezpieczne do
ponownego wykonania, a wyłączenie lub dezaktywacja wtyczki nie usuwa tabel ani
danych.

## Decyzja o składowaniu danych

Treść redakcyjna Courses i dane transakcyjne używają dedykowanych tabel.
Nie korzystamy z `postmeta` jako modelu domenowego. Powodem jest konieczność
niezmiennego wersjonowania programu, jednoznacznego porządku, unikalnych
snapshotów i przewidywalnych zapytań integralności. Przyszły panel WordPressa
będzie klientem publicznych usług i repozytoriów, a nie właścicielem reguł.

Podział jest następujący:

- katalog redakcyjny: kursy, wersje programu, sekcje, lekcje, przypisania lekcji
  do programu i materiały;
- stan transakcyjny: postęp lekcji i ukończenia kursu;
- granty dostępu i zdarzenia audytowe: istniejące tabele `AM Access Core`.

Schemat nie deklaruje kluczy obcych. `dbDelta()` i cykl życia tabel WordPressa
nie zapewniają wystarczająco przewidywalnej obsługi constraintów FK na wszystkich
wspieranych instalacjach. Integralność relacji egzekwują repozytoria i usługi,
a baza wymusza kluczowe ograniczenia unikalności i indeksy wyszukiwania.

## Identyfikatory

Każdy publiczny zasób domenowy otrzymuje UUID zapisany jako `public_id`. UUID nie
zależy od tytułu, URL, kolejności ani techniki renderowania.

Tabele zachowują również liczbowy, niezmienny klucz `id`. Jest on używany do
relacji wewnętrznych oraz jako `resource_id` w istniejącym kontrakcie
`AM Access Core` z `resource_type = course`. Publiczny interfejs nie powinien
ujawniać tego klucza jako adresu zasobu.

## Encje i odpowiedzialności

### Course

Produkt edukacyjny: tytuł, opis, grafika, stan publikacji i wskaźnik bieżącej
wersji programu. Nie przechowuje grantu ani postępu użytkownika.

### CourseProgramVersion

Niemutowalny po publikacji snapshot uporządkowanego programu. Ma kolejny numer
w obrębie kursu, hash zawartości, datę publikacji oraz zbiór wymaganych lekcji.
Zmiana opublikowanego programu tworzy nową wersję; nie poprawia starego rekordu
w miejscu.

### CourseSection

Opcjonalna grupa lekcji w konkretnej wersji programu. Nazwa `CourseSection`
unika konfliktu z pojęciem modułu wtyczki. Lekcja może należeć do programu bez
sekcji.

### Lesson

Trwała treść etapu kursu: tytuł, opis, odniesienie do adaptera wideo, czas
trwania, wersja treści i wersjonowany zestaw wymagań ukończenia. Kolejność i
obowiązkowość nie należą do lekcji — są właściwościami przypisania do wersji
programu.

Wymagania ukończenia są nieprzezroczystym, wersjonowanym dokumentem JSON.
Pozwala to później opisać próg obejrzenia filmu lub wymagane zadania bez
sprzęgania domeny z odtwarzaczem i typem formularza.

### LessonMaterial

Chroniony materiał lekcji. Przechowuje nazwę, opis, kolejność oraz parę
`storage_provider` / `storage_reference`. Nie przechowuje publicznego URL jako
kontraktu domenowego.

### CourseMeeting

Kontrakt domenowy rozdzielający czas UTC, strefę prezentacji, platformę oraz
chronione odniesienia do spotkania i nagrania. Persystencja, providerzy Zoom,
Telegram i obsługa spotkań są świadomie poza VIA-29 i powstaną w VIA-43. Nie ma
tymczasowej tabeli spotkań, której kontrakt trzeba byłoby później łamać.

### LessonProgress

Bieżący stan jednej pary użytkownik/kurs/lekcja. Brak rekordu oznacza stan
nierozpoczęty; zapisane stany to `started` i `completed`. Ukończenie zawiera
źródło, wersję treści i czas UTC. Para `(user_id, course_id, lesson_id)` jest
unikalna.

### CourseCompletion

Niemutowalny fakt ukończenia określonej wersji programu. Przechowuje kanoniczny
snapshot identyfikatorów wymaganych lekcji i jego hash. Unikalna jest para
użytkownik/kurs/wersja programu. Późniejsze dodanie lekcji do nowej wersji nie
cofa wcześniejszego ukończenia.

### AccessGrant i ActivityEvent

Pozostają własnością `AM Access Core`. Courses nie duplikuje dostępu ani audytu.
`AccessCoreCourseAccessPolicy` sprawdza zasób typu `course`; kolejne przypadki
użycia zapisujące stan będą korzystały z istniejącego kontraktu zdarzeń i
`request_id`.

## Stany i archiwizacja

Kurs, program, sekcja, lekcja i materiał używają stanów `draft`, `published`
i `archived`. Archiwizacja zachowuje relacje i historię. Fizyczne usunięcie
opublikowanego zasobu nie jest standardową operacją domenową.

Opublikowana wersja programu musi mieć `published_at`. Szkic nie może udawać
opublikowanego snapshotu. Wersja zarchiwizowana zachowuje pierwotny czas
publikacji.

## Źródło prawdy postępu

Źródłem prawdy są rekordy ukończenia wymaganych lekcji wskazanych przez wersję
programu:

```text
ukończone wymagane lekcje / wszystkie wymagane lekcje wersji programu
```

Klient nie zapisuje procentu. `CompletionEvaluator` otrzymuje snapshot programu
i identyfikatory ukończonych lekcji. `CourseCompletion` zapisuje dokładny zbiór,
który doprowadził do ukończenia. Agregaty interfejsu będą danymi pochodnymi,
możliwymi do odbudowy.

## Tabele i migracje

Migracja Courses `1` tworzy katalog:

- `amt_courses`,
- `amt_course_program_versions`,
- `amt_course_sections`,
- `amt_lessons`,
- `amt_program_lessons`,
- `amt_lesson_materials`.

Migracja Courses `2` tworzy stan transakcyjny:

- `amt_lesson_progress`,
- `amt_course_completions`.

Migracja Courses `3` tworzy konfigurację integracji handlowej:

- `amt_course_product_mappings`.

Mapowanie jest relacją wiele-do-wielu i można je dezaktywować bez usuwania
historycznych grantów. Szczegółowy kontrakt cyklu dostępu opisuje
`docs/COURSES-ACCESS.md`.

Nazwy otrzymują prefix WordPressa. Każda migracja korzysta z `dbDelta()`, po
wykonaniu weryfikuje istnienie tabel i kluczowych indeksów, a wersję zapisuje
dopiero po pozytywnej weryfikacji. Opublikowane migracje nie zawierają `DROP`,
`TRUNCATE` ani `DELETE`.

## Publiczne kontrakty

Warstwa `Contracts` udostępnia repozytoria kursów, programów, lekcji, postępu
i ukończeń oraz polityki `CourseAccessPolicy` i `CompletionEvaluator`.
Kontrakty używają obiektów domenowych i nie zależą od HTML, Elementora,
WooCommerce ani konkretnego dostawcy wideo lub plików.

Implementacje repozytoriów, transakcje publikacji, panel oraz endpointy należą
do kolejnych zadań. Nie należy omijać kontraktów bezpośrednimi zapytaniami z
warstwy widoku.

## Reguły dostępu

1. Każdy przyszły widok kursu, lekcji, spotkania i pliku sprawdza aktywny grant.
2. Różne źródła dostępu są niezależne.
3. Wygaśnięcie dostępu nie usuwa postępu.
4. Ponowne nadanie dostępu przywraca widok zachowanego postępu.
5. Mapowanie produktu WooCommerce na kurs nie jest grantem.
6. Dostęp demo będzie zakresem grantu do lekcji programu, nie kopią kursu.

## Poza VIA-29

- panel administracyjny i UI klientki,
- implementacje repozytoriów i przypadków użycia publikacji,
- automatyczne granty z WooCommerce,
- śledzenie i naprawa postępu,
- spotkania, Zoom, Telegram i chronione dostarczanie materiałów,
- analityka zachowania.

Te elementy muszą używać opisanych identyfikatorów, tabel i kontraktów zamiast
tworzyć równoległe źródła prawdy.
