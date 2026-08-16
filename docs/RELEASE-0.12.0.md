# Wydanie AM Toolkit 0.12.0

Status: release candidate VIA-45 po lokalnym QA; publikacja GitHub i wdrożenie
produkcyjne są osobnymi krokami.

## Zweryfikowany zakres QA

16 sierpnia 2026 wykonano na gałęzi wydaniowej:

- `composer check`: 106 testów, 458 asercji, analiza PHPStan bez błędów i
  poprawna składnia 282 plików PHP,
- `composer audit --locked`: brak znanych podatności,
- walidację źródła, zgodności trzech deklaracji wersji i struktury ZIP,
- migrację oraz kontrolę schematu: Access 2, Courses 7, capabilities 2,
- integrację panelu, huba, Q&A i diagnostyki na rzeczywistej lokalnej bazie,
- scenariusz zakup → pełny zwrot → ponowne opłacenie z przywróceniem tego samego
  idempotentnego grantu,
- ręczne nadanie i odebranie, wygasły dostęp oraz odrzucenie obcego użytkownika,
- widoki kursu i lekcji na komputerze oraz przy szerokości 390 px bez poziomego
  przewijania dokumentu i bez błędów konsoli,
- dostępność kontrolek odtwarzacza, materiał, kontynuację, spotkanie, Zoom,
  Telegram, stan Q&A oraz odmowę wejścia do panelu bez capability,
- rzeczywisty rollback do opublikowanej paczki `v0.11.5`, a następnie ponowną
  aktualizację do 0.12.0 bez usuwania tabel i utraty kursu.

Referencyjna paczka rollbacku `am-toolkit-v0.11.5.zip` ma SHA-256:

```text
0A3DACB662DF62290256A18F78DB2D13C85EA490C0ED633766FF859076CCC2AE
```

Finalna lokalnie zweryfikowana paczka `am-toolkit-v0.12.0.zip` ma SHA-256:

```text
0780F54D9C2DDB001195BBD26B386B3AAE6CC58763B03E827D09A2E9E67E5217
```

Trzy syntetyczne eksporty diagnostyczne celowo obejmują stan poprawny, stan
z niespójną migawką ukończenia i brak aktywnego grantu. Ostrzeżenia tych danych
testowych nie są błędem schematu wydania.

## Bramki przed produkcją

Przed włączeniem Kursów na produkcji trzeba potwierdzić:

1. prywatny katalog nagrań poza publicznym webrootem oraz jego backup,
2. limity `upload_max_filesize`, `post_max_size`, czasu wykonania i wolnego dysku,
3. pełny backup plików i bazy oraz dostępność paczki rollbacku 0.11.5,
4. mapowania produktów, opublikowany program i osobne konto testowe klientki,
5. test pełnego zakupu, zwrotu i ponownego nadania na konfiguracji produkcyjnej,
6. test telefonu po wdrożeniu, w tym fullscreen i best-effort orientację poziomą.

Brak potwierdzenia tych punktów blokuje aktywację produkcyjną, ale nie wymaga
usuwania danych ani cofania samego GitHub Release.

## Kontrolowana aktywacja

Po instalacji migracje uruchamiają się niezależnie od flagi Kursów. Świeża
instalacja zachowuje `courses=false` i `courses-access-automation=false`.
Operator wykonuje kolejne kroki:

1. sprawdza opcje `am_toolkit_schema_access=2` i
   `am_toolkit_schema_courses=7`,
2. włącza tylko `courses`,
3. konfiguruje kursy, materiały, spotkania i mapowania produktów,
4. testuje ręczny dostęp na osobnym koncie,
5. dopiero wtedy włącza `courses-access-automation`,
6. uruchamia partiami migrację opłaconych zakupów historycznych i kontroluje
   wynik każdej partii.

Przykład jednorazowej zmiany flag przez WP-CLI:

```bash
wp eval '$flags=(array)get_option("am_toolkit_feature_flags",[]); $flags["courses"]=true; update_option("am_toolkit_feature_flags",$flags,false);'
wp eval '$flags=(array)get_option("am_toolkit_feature_flags",[]); $flags["courses-access-automation"]=true; update_option("am_toolkit_feature_flags",$flags,false);'
```

Migrację historyczną uruchamia się małymi partiami, aż wynik zgłosi
`completed=true`:

```bash
wp eval 'do_action("am_toolkit_courses_migrate_historical_purchases", 50);'
```

## Rollback

Rollback nie usuwa tabel, plików prywatnych, grantów ani historii:

1. wyłącz `courses-access-automation`, a następnie `courses`,
2. wyczyść cache strony,
3. przywróć zweryfikowaną paczkę `v0.11.5`,
4. sprawdź główny widok „Moje konto”, zamówienia i log błędów,
5. zachowaj tabele Courses do diagnozy i późniejszego ponownego wdrożenia.

Awaryjnie flagi można wyłączyć bez modyfikacji bazy stałymi
`AM_TOOLKIT_DISABLE_COURSES_ACCESS_AUTOMATION` oraz
`AM_TOOLKIT_DISABLE_COURSES`. Tryb `AM_TOOLKIT_SAFE_MODE` ma pierwszeństwo nad
opcjami i uruchamia tylko fundament dostępu.
