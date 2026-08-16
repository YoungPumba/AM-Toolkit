# Spotkania i prywatne linki AM Courses

Status: implementacja VIA-43 dla MVP 0.12.0.

## Edycja

Właścicielka zarządza spotkaniami w panelu AM Courses. Każdy termin zawiera
tytuł, opis, początek, koniec, strefę prezentacji, platformę, miejsce, status
oraz opcjonalne linki do spotkania i nagrania. Na poziomie kursu może zapisać
również opcjonalny prywatny link do grupy Telegram.

MVP nie łączy się z Zoom API ani Telegram API. Linki są wprowadzane ręcznie i
muszą używać HTTPS.

## Czas i statusy

Formularz pracuje w `Europe/Warsaw`, a baza zapisuje jednoznaczny czas UTC.
Zmiana początku istniejącego zaplanowanego spotkania automatycznie nadaje stan
`rescheduled`. Pozostałe stany to `scheduled`, `cancelled` i `completed`.

Najbliższe spotkanie to pierwszy przyszły rekord w stanie `scheduled` lub
`rescheduled`. Brak takiego rekordu jest osobnym, czytelnym stanem interfejsu.

## Widok uczestnika

Najbliższy termin jest widoczny w kafelku kursu, programie kursu oraz w sekcji
„Wymaga Twojej uwagi” na pulpicie konta. Wpis w sekcji uwagi pokazuje tytuł
spotkania, nazwę kursu i czas w strefie prezentacji, a prowadzi do chronionego
widoku kursu — prywatny adres spotkania nie jest ujawniany na pulpicie.

Odnośniki do spotkania Zoom i grupy Telegram korzystają z ikon dostarczonych
dla projektu oraz kolorów identyfikacji obu usług. Dla innych platform
spotkań pozostaje neutralna ikona odnośnika zewnętrznego.

## Prywatność i audyt

Prywatne linki są odczytywane dopiero po serwerowym potwierdzeniu aktywnego
grantu do kursu. Read model dla osoby bez dostępu nie wykonuje zapytania o
spotkania. Widoki nie ujawniają wewnętrznych liczbowych identyfikatorów.

Każdy zapis spotkania tworzy niezmienną rewizję z aktorem i `request_id`.
Zdarzenie `meeting.updated` zawiera status, czas i flagi obecności linków, ale
nigdy same adresy. Podobnie `course.telegram.updated` zapisuje tylko informację,
czy link istnieje. Prywatne wartości nie mogą trafić do logów ani eksportu
diagnostycznego.

## Wycofanie

Flaga `courses-meetings` odłącza panel i read model spotkań bez usuwania tabel,
rewizji ani prywatnych ustawień.
