# Wydanie AM Toolkit 0.12.2

Status: release candidate VIA-159. Publikacja tagu, GitHub Release i wdrożenie
produkcyjne są osobnymi krokami wymagającymi osobnej weryfikacji.

## Cel i zakres

Produkcja działa na 0.12.0. Opublikowane 0.12.1 nie zostało wdrożone, dlatego
0.12.2 jest jednym kontrolowanym przejściem zawierającym:

- poprawkę mobilnego overflow kart Zoom i Telegram z 0.12.1,
- stabilizację obsługi chronionych żądań HTTP Range,
- wcześniejsze zwalnianie sesji PHP i porcjowe przesyłanie danych,
- prywatny cache przeglądarki oddzielony sesją użytkownika,
- `ETag`, `Last-Modified` i `preload="auto"` dla sprawniejszego wznowienia,
- sprawdzanie układu MP4 i wymaganie Fast Start dla nowych nagrań,
- ostrzeżenia o istniejących plikach wymagających optymalizacji,
- redakcyjną instrukcję przygotowania nagrań MP4.

## Niezmienniki

Aktualizacja 0.12.2:

- nie dodaje ani nie uruchamia migracji,
- zachowuje schematy Access 2, Courses 7 i capabilities 2,
- nie zmienia opcji `am_toolkit_feature_flags`,
- nie zmienia kursów, wersji programu, grantów ani postępu,
- nie przenosi i nie usuwa prywatnego katalogu `am-toolkit-private`,
- zastępuje wyłącznie pliki kodu w `wp-content/plugins/am-toolkit`.

## Weryfikacja release candidate

Przed publikacją wymagane są:

1. spójne deklaracje wersji 0.12.2 w nagłówku wtyczki,
   `AM_TOOLKIT_VERSION` i `Plugin::VERSION`,
2. `composer check`,
3. `composer audit --locked`,
4. budowa i walidacja `am-toolkit-v0.12.2.zip`,
5. obliczenie SHA-256 gotowej paczki,
6. aktualizacja lokalnego WordPressa i test rzeczywistego MP4,
7. zielone CI na PHP 8.0 i PHP 8.3.

Weryfikacja lokalnego release candidate z 1 września 2026:

- `composer check`: poprawna składnia 286 plików PHP, analiza PHPStan bez
  błędów oraz 114 testów i 493 asercje,
- walidacja źródła: AM Toolkit 0.12.2, 243 pliki PHP i komplet wymaganych
  plików,
- walidacja ZIP: 204 pliki, jeden katalog główny `am-toolkit/` i zgodna
  wersja 0.12.2,
- dwie niezależne budowy deterministyczne mają identyczny SHA-256,
- rozmiar paczki: 315501 B,
- lokalny WordPress połączony junctionem odczytuje wersję 0.12.2 oraz
  niezmienione schematy Access 2, Courses 7 i capabilities 2,
- test 40 sekund ciągłego odtwarzania i wznowienia rzeczywistego MP4 wykonany
  dla identycznego kodu VIA-105 zakończył się bez postoju.

Lokalny `composer audit --locked` nie mógł połączyć się z Packagist z powodu
błędu lokalnego wystawcy certyfikatu TLS. Nie wyłączono weryfikacji TLS.
GitHub Actions dla PR #25 wykonało pełny workflow Quality, w tym
`composer audit --locked`, z wynikiem pozytywnym na PHP 8.0 i PHP 8.3:

`https://github.com/YoungPumba/AM-Toolkit/actions/runs/33542488114`

Paczka `am-toolkit-v0.12.2.zip` ma SHA-256:

```text
4133C349765A09892BD1ACEDF1B94D885509165CE9F23DB05102CAA35107BC27
```

## Wdrożenie produkcyjne

Wdrożenie wymaga osobnego jawnego potwierdzenia właściciela bezpośrednio przed
zmianą produkcji.

1. Utwórz świeżą kopię bazy i plików po ostatnich zmianach w kursach.
2. Zanotuj wersję 0.12.0, wartości `am_toolkit_feature_flags` oraz wersje
   schematów.
3. Zachowaj zweryfikowaną paczkę 0.12.0 jako punkt rollbacku.
4. Wgraj zweryfikowany ZIP 0.12.2 i wybierz zastąpienie aktywnej wtyczki bez
   jej wcześniejszej dezaktywacji.
5. Potwierdź w WordPressie wersję 0.12.2.
6. Opróżnij LiteSpeed i wykonaj twarde odświeżenie.
7. Potwierdź, że flagi, schematy, kursy i prywatne pliki nie uległy zmianie.
8. Sprawdź panel kursów, konto uczestniczki, dostęp i zapis postępu.
9. Na fizycznym telefonie sprawdź brak poziomego przewijania spotkań.
10. Dla problematycznego nagrania sprawdź start, przewijanie, wznowienie oraz
    co najmniej kilka minut ciągłego odtwarzania.
11. Zapisz wynik w VIA-159 i VIA-76.

## Interpretacja ostrzeżeń MP4

Ostrzeżenie przy istniejącym nagraniu nie oznacza utraty pliku ani blokady
lekcji. Informuje, że metadane MP4 znajdują się za danymi obrazu i plik może
powodować dodatkowe żądania zakresowe. Procedura poprawy znajduje się w
[`COURSES-VIDEO-PREPARATION.md`](COURSES-VIDEO-PREPARATION.md).

Nie należy masowo kodować wszystkich plików bez wcześniejszej inwentaryzacji.
Audyt 54 nagrań jest prowadzony w VIA-158, a prywatna biblioteka i bezpieczne
zarządzanie plikami w VIA-101.

## Rollback

Jeżeli 0.12.2 spowoduje regresję:

1. zastąp wtyczkę zweryfikowaną paczką `am-toolkit-v0.12.0.zip`,
2. opróżnij LiteSpeed,
3. sprawdź kurs, odtwarzacz, grant i postęp na koncie testowym,
4. nie usuwaj tabel, opcji ani katalogu `am-toolkit-private`,
5. przywracaj bazę lub pliki danych tylko wtedy, gdy kontrola wykaże ich
   rzeczywistą zmianę; sam rollback kodu tego nie wymaga.
