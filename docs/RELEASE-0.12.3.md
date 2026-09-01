# Wydanie AM Toolkit 0.12.3

Status: release candidate VIA-165. Publikacja tagu, GitHub Release i wdrożenie
produkcyjne są osobnymi krokami wymagającymi osobnej weryfikacji.

## Cel i zakres

Wersja 0.12.3 udostępnia wbudowaną, uruchamianą parametrem diagnostykę
chronionego odtwarzacza AM Courses. Jej celem jest zebranie miarodajnego
raportu z fizycznego iPhone'a w chwili wystąpienia problemu pauzy i wznowienia.

Raport łączy zdarzenia elementu `video` z początkiem i końcem chronionych
żądań HTTP Range. Nie zapisuje cookies, nonce, adresu nagrania, adresu IP,
adresu e-mail ani surowych identyfikatorów użytkownika, kursu i lekcji.

To wydanie nie deklaruje naprawy przyczyny zacinania w Safari. Dostarcza dane
potrzebne do odróżnienia problemu przeglądarki, kodowania MP4, połączenia i
obsługi Range na serwerze przed wdrożeniem kolejnej poprawki w VIA-105.

## Niezmienniki

Aktualizacja 0.12.3:

- nie dodaje ani nie uruchamia migracji,
- zachowuje schematy Access 2, Courses 7 i capabilities 2,
- nie zmienia opcji `am_toolkit_feature_flags`,
- nie zmienia kursów, wersji programu, grantów ani postępu,
- nie przenosi i nie usuwa prywatnego katalogu `am-toolkit-private`,
- zastępuje wyłącznie pliki kodu w `wp-content/plugins/am-toolkit`.

## Weryfikacja release candidate

Przed publikacją wymagane są:

1. spójne deklaracje wersji 0.12.3 w nagłówku wtyczki,
   `AM_TOOLKIT_VERSION` i `Plugin::VERSION`,
2. `composer check`,
3. `composer audit --locked`,
4. budowa i walidacja `am-toolkit-v0.12.3.zip`,
5. dwie deterministyczne budowy z identycznym SHA-256,
6. aktualizacja lokalnego WordPressa i kontrola niezmienników,
7. test wygenerowania oraz pobrania raportu diagnostycznego,
8. zielone CI na PHP 8.0 i PHP 8.3.

Lokalny test funkcjonalny diagnostyki został wykonany w Chrome 152 na Windows
10 dla rzeczywistego chronionego MP4. Raport zawierał 21 zdarzeń klienta oraz
jedną kompletną parę start/koniec żądania Range: HTTP 206, 7 919 637 B w 37 ms,
bez przerwania. Pauza i ponowne wznowienie zadziałały. Kontrola prywatności nie
wykazała adresu nagrania, cookies, nonce ani adresu e-mail. Ten wynik potwierdza
działanie narzędzia, ale nie odtwarza błędu z iPhone'a.

Weryfikacja lokalnego release candidate z 1 września 2026:

- `composer check`: poprawna składnia 289 plików PHP, analiza PHPStan bez
  błędów oraz 117 testów i 512 asercji,
- walidacja źródła: AM Toolkit 0.12.3, 246 plików PHP i komplet wymaganych
  plików,
- walidacja ZIP: 206 plików, jeden katalog główny `am-toolkit/` i zgodna
  wersja 0.12.3,
- dwie niezależne budowy deterministyczne mają identyczny SHA-256,
- rozmiar finalnej paczki: 322257 B,
- lokalny WordPress połączony junctionem odczytuje wersję 0.12.3 oraz
  niezmienione schematy Access 2, Courses 7 i capabilities 2; flaga `courses`
  pozostała włączona, a dane testowe kursu, lekcji i grantu są obecne.

Lokalny `composer audit --locked` nie mógł połączyć się z Packagist z powodu
błędu lokalnego wystawcy certyfikatu TLS. Nie wyłączono weryfikacji TLS.
GitHub Actions dla PR #28 wykonało pełny workflow Quality, w tym audyt, z
wynikiem pozytywnym na PHP 8.0 i PHP 8.3:

`https://github.com/YoungPumba/AM-Toolkit/actions/runs/33560685305`

Paczka `am-toolkit-v0.12.3.zip` ma SHA-256:

```text
EA3A8E7A4C5B1CF57F8E24695BD2ED849EFF6BC16FEF3177EB320F3847116BC7
```

## Wdrożenie produkcyjne i test iPhone'a

Wdrożenie wymaga osobnego jawnego potwierdzenia właściciela bezpośrednio przed
zmianą produkcji.

1. Utwórz świeżą kopię bazy i plików po ostatnich zmianach w kursach.
2. Zanotuj wersję 0.12.2, wartości `am_toolkit_feature_flags` oraz wersje
   schematów.
3. Zachowaj zweryfikowaną paczkę 0.12.2 jako punkt rollbacku.
4. Wgraj zweryfikowany ZIP 0.12.3 i wybierz zastąpienie aktywnej wtyczki bez
   jej wcześniejszej dezaktywacji.
5. Potwierdź w WordPressie wersję 0.12.3.
6. Opróżnij LiteSpeed i wykonaj twarde odświeżenie.
7. Potwierdź, że flagi, schematy, kursy, granty, postęp i prywatne pliki nie
   uległy zmianie.
8. Na fizycznym iPhonie otwórz problematyczną lekcję, dopisując do jej adresu
   `?am_course_diagnostics=1`.
9. Odtwórz scenariusz powodujący błąd: start, pauza, ponowne wznowienie i
   oczekiwanie na wynik albo trwałe ładowanie.
10. Pobierz raport JSON z panelu diagnostycznego bez odświeżania strony.
11. Zapisz raport i obserwacje w VIA-105. Nie publikuj raportu w miejscu
    dostępnym publicznie.

## Rollback

Jeżeli 0.12.3 spowoduje regresję:

1. zastąp wtyczkę zweryfikowaną paczką `am-toolkit-v0.12.2.zip`,
2. opróżnij LiteSpeed,
3. sprawdź kurs, odtwarzacz, grant i postęp na koncie testowym,
4. nie usuwaj tabel, opcji ani katalogu `am-toolkit-private`,
5. przywracaj bazę lub pliki danych tylko wtedy, gdy kontrola wykaże ich
   rzeczywistą zmianę; sam rollback kodu tego nie wymaga.
