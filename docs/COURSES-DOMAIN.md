# Model domeny AM Courses

Status: specyfikacja przed implementacją `v0.12.0`.

## Zakres pierwszej wersji

AM Courses udostępnia kursy klientom posiadającym aktywny grant w
`AM Access Core`. Właścicielka zarządza programem, lekcjami, materiałami,
spotkaniami, uczestnikami oraz publikacją bez edycji plików wtyczki.

Pierwsza wersja obsługuje jeden wspólny program kursu dla wszystkich
uczestników. Model nie wprowadza grup ani sezonów, ale nie może blokować ich
dodania w przyszłości.

## Encje

### Course

Opisuje kurs jako produkt edukacyjny: tytuł, opis, grafika, stan publikacji,
prywatny link do Telegrama i bieżąca wersja programu.

### CourseProgramVersion

Niemutowalny zapis zestawu wymaganych lekcji obowiązującego w określonym
momencie. Pozwala rozbudować kurs bez odebrania statusu ukończenia osobom,
które wcześniej osiągnęły 100%.

### Module

Opcjonalna grupa lekcji. Prosty kurs może mieć lekcje bez ręcznego tworzenia
modułów.

### Lesson

Etap kursu z trwałym identyfikatorem, tytułem, opisem, filmem, czasem trwania,
kolejnością, stanem publikacji i informacją, czy jest wymagany.

### LessonMaterial

Chroniony plik przypisany do lekcji. Ma nazwę, opis, kolejność i odwołanie do
magazynu plików. Publiczny adres pliku nie jest prezentowany użytkownikowi.

### CourseMeeting

Spotkanie związane z kursem: tytuł, początek i koniec, strefa czasowa, miejsce
lub platforma, opis, link do spotkania i opcjonalne nagranie. Wszystkie daty są
zapisywane jednoznacznie i wyświetlane w `Europe/Warsaw`.

### AccessGrant

Istniejący grant `AM Access Core`. Źródłem może być opłacona pozycja
zamówienia, ręczne nadanie, migracja, pakiet albo przyszła subskrypcja.

### LessonProgress

Serwerowy stan postępu użytkownika w lekcji. Minimalne dane:

- `user_id`, `course_id`, `lesson_id`,
- `status`,
- `completed_at`,
- `completion_source`,
- `content_version`,
- `created_at`, `updated_at`.

Para `(user_id, course_id, lesson_id)` jest unikalna.

### CourseCompletion

Zapis ukończenia kursu wraz z wersją programu i zestawem wymaganych lekcji,
który został spełniony. Nie jest obliczany wyłącznie na podstawie obecnej
liczby lekcji.

### ActivityEvent

Niemutowalny wpis audytowy opisujący istotną zmianę. Nie zastępuje tabeli
aktualnego postępu.

## Stany

Kurs i lekcja używają jawnych stanów, np. `draft`, `published`, `archived`.
Archiwizacja zachowuje historię oraz odwołania. Fizyczne usunięcie opublikowanej
treści nie jest standardową operacją panelu.

Postęp lekcji rozpoczyna się jako brak rekordu, następnie może przejść do
`started` i `completed`. Cofnięcie ukończenia jest osobną, audytowaną operacją,
a nie bezpośrednią edycją procentu.

## Źródło prawdy postępu

Źródłem prawdy są rekordy ukończenia wymaganych lekcji. Procent kursu jest
wartością pochodną:

```text
ukończone wymagane lekcje / wszystkie wymagane lekcje wersji programu
```

Zapisany agregat służy wydajności, ale zawsze można go odbudować. Klient nie
przesyła procentu; wysyła intencję ukończenia konkretnej lekcji.

## Reguły dostępu

1. Każdy widok kursu, lekcji, spotkania i pliku sprawdza aktywny grant.
2. Różne źródła dostępu są niezależne. Zwrot jednego zakupu nie usuwa ręcznego
   grantu ani innego aktywnego zakupu.
3. Wygaśnięcie dostępu nie usuwa postępu.
4. Ponowne nadanie dostępu przywraca widok zachowanego postępu.
5. Mapowanie produktu WooCommerce na kurs nie jest samo w sobie grantem.
6. Dostęp z zamówienia powstaje dopiero po spełnieniu uzgodnionego stanu
   płatności i jest idempotentny względem pozycji zamówienia.

## Następne najlepsze działanie

Jedna usługa wyznacza następny krok użytkownika. Może nim być:

- rozpoczęcie kursu,
- kontynuacja ostatniej lekcji,
- otwarcie następnej nieukończonej lekcji,
- pobranie materiału,
- dołączenie do najbliższego spotkania,
- uzupełnienie wymaganych danych konta.

Hub kursów, panel główny i sekcja „Wymaga Twojej uwagi” korzystają z tej samej
usługi, aby nie prezentować sprzecznych komunikatów.

## Widoki pierwszej wersji

- `/moje-konto/kursy/` — kursy aktywne, ukończone i wygasłe,
- widok kursu — program, postęp, spotkanie, Telegram i „Kontynuuj”,
- widok lekcji — film, opis, materiały i nawigacja,
- panel właścicielki — program, publikacja, mapowanie produktów, uczestnicy i
  spotkania,
- minimalna diagnostyka uczestnika i kursu.

## Sytuacje brzegowe

Implementacja musi obsłużyć:

- podwójne kliknięcie „Ukończ lekcję”,
- równoległe żądania z dwóch urządzeń,
- ponowione callbacki WooCommerce,
- dodanie, zmianę kolejności i archiwizację lekcji,
- brak filmu albo materiału,
- brak zaplanowanego spotkania,
- zmianę lub odwołanie spotkania,
- wygasły i ponownie nadany dostęp,
- użytkownika z kilkoma źródłami dostępu,
- wcześniejszych klientów migrowanych ręcznie,
- niedostępnego dostawcę wideo lub powiadomień.

## Poza MVP

Notatki, zadania, dyskusje przy lekcji, grupy, sezony, certyfikaty, punkty,
automatyczne śledzenie pozycji filmu i zaawansowana analityka pozostają poza
MVP. Kontrakty i identyfikatory nie mogą jednak zamknąć drogi do tych funkcji.

