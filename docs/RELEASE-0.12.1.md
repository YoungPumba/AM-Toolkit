# Wydanie AM Toolkit 0.12.1

Status: release candidate VIA-106. Publikacja tagu, GitHub Release i wdrożenie
produkcyjne są osobnymi krokami wymagającymi osobnej weryfikacji.

## Cel i zakres

0.12.1 jest małym hotfixem po produkcyjnym smoke teście 0.12.0. Wydanie
zawiera poprawkę VIA-104, która zatrzymuje poziome przewijanie podsumowania
kursu na telefonach:

- kontenery spotkań Zoom i Telegram mogą zwężać się do szerokości rodzica,
- długie adresy oraz treści zaproszeń są zawijane wewnątrz karty,
- CTA nie poszerzają dokumentu,
- nie zastosowano globalnego `overflow-x: hidden`, które jedynie ukrywałoby
  wadliwy układ.

## Niezmienniki

Aktualizacja 0.12.1:

- nie dodaje ani nie uruchamia nowych migracji,
- zachowuje schematy Access 2, Courses 7 i capabilities 2,
- nie zmienia opcji `am_toolkit_feature_flags`,
- nie zmienia kursów, programów, grantów ani postępu,
- nie dotyka prywatnego katalogu `am-toolkit-private`, znajdującego się poza
  katalogiem wtyczki,
- zastępuje wyłącznie pliki kodu w `wp-content/plugins/am-toolkit`.

## Weryfikacja wydania

Przed publikacją wymagane są:

1. spójne deklaracje wersji 0.12.1 w nagłówku wtyczki,
   `AM_TOOLKIT_VERSION` i `Plugin::VERSION`,
2. `composer check`,
3. `composer audit --locked`,
4. budowa i walidacja `am-toolkit-v0.12.1.zip`,
5. obliczenie SHA-256 gotowej paczki,
6. zielone CI na PHP 8.0 i PHP 8.3.

Weryfikacja lokalnego release candidate z 17 sierpnia 2026:

- `composer check`: 108 testów, 473 asercje, analiza PHPStan bez błędów i
  poprawna składnia 283 plików PHP,
- `composer audit --locked`: brak znanych podatności,
- walidacja źródła: AM Toolkit 0.12.1, 240 plików PHP i komplet wymaganych
  plików,
- walidacja ZIP: 202 pliki, jeden katalog główny `am-toolkit/` i zgodna
  wersja 0.12.1.

Paczka `am-toolkit-v0.12.1.zip` ma SHA-256:

```text
AA34B48F145CCE1A78AC49ED0B967351E52A7C291342B6CFBA08817EFE03CDF2
```

## Wdrożenie produkcyjne

1. Utwórz świeżą kopię plików i bazy po ostatnich zmianach w rzeczywistym
   kursie. Backup sprzed dodania treści nie jest wystarczającym rollbackiem.
2. Zanotuj wersję 0.12.0 oraz bieżące wartości `am_toolkit_feature_flags`.
3. Wgraj zweryfikowany ZIP 0.12.1 i wybierz zastąpienie obecnej wersji bez
   wcześniejszej dezaktywacji wtyczki.
4. Potwierdź w WordPressie wersję 0.12.1.
5. Opróżnij cache LiteSpeed oraz odśwież stronę bez pamięci podręcznej.
6. Sprawdź panel kursów, konto uczestniczki, odtwarzanie i zapis postępu.
7. Na fizycznym telefonie otwórz kurs z pełnym zaproszeniem Zoom oraz kartą
   Telegram i potwierdź brak poziomego przewijania.
8. Ponownie sprawdź, że flagi oraz wersje schematów nie uległy zmianie.

## Rollback

Jeśli hotfix spowoduje regresję:

1. zastąp 0.12.1 zweryfikowaną paczką `am-toolkit-v0.12.0.zip`,
2. opróżnij LiteSpeed,
3. sprawdź kurs, odtwarzacz, grant i postęp na koncie testowym,
4. nie usuwaj tabel ani katalogu `am-toolkit-private`,
5. przywracaj bazę lub pliki danych wyłącznie wtedy, gdy kontrola wykaże ich
   rzeczywistą zmianę; sam rollback kodu tego nie wymaga.
