# Wydanie AM Toolkit 0.12.4

Status: release candidate VIA-166. Publikacja tagu, GitHub Release i wdrożenie
produkcyjne są osobnymi krokami wymagającymi osobnej weryfikacji.

## Cel i zakres

Wersja 0.12.4 naprawia najbardziej prawdopodobną przyczynę lawiny pełnych
odpowiedzi HTTP `200` podczas odtwarzania chronionego nagrania na iOS. Raport
produkcyjny z 0.12.3 wykazał około 18,9 GB transferu dla pliku o rozmiarze
411 286 529 B, bez ani jednej odpowiedzi częściowej `206`.

Aktualizacja:

- odzyskuje nagłówek Range ze standardowej zmiennej CGI, wariantu po
  wewnętrznym przekierowaniu oraz listy nagłówków udostępnionej przez serwer,
- przekazuje odzyskany nagłówek do istniejącej walidacji pojedynczego zakresu,
- zmienia `preload` odtwarzacza z `auto` na `metadata`,
- zapisuje w diagnostyce wyłącznie obecność i bezpieczną kategorię źródła
  Range, nigdy jego surową wartość.

Poprawka jest potwierdzona testami kodu, ale jej skuteczność na Hostingerze
może zostać uznana dopiero po krótkim teście fizycznego iPhone'a. Sam status
`200` w poprzednim raporcie nie dowodzi, na której dokładnie warstwie nagłówek
został utracony.

## Niezmienniki

Aktualizacja 0.12.4:

- nie dodaje ani nie uruchamia migracji,
- zachowuje schematy Access 2, Courses 7 i capabilities 2,
- nie zmienia opcji `am_toolkit_feature_flags`,
- nie zmienia kursów, wersji programu, grantów ani postępu,
- nie przenosi i nie usuwa prywatnego katalogu `am-toolkit-private`,
- zastępuje wyłącznie pliki kodu w `wp-content/plugins/am-toolkit`.

## Weryfikacja release candidate

Przed publikacją wymagane są:

1. spójne deklaracje wersji 0.12.4 w nagłówku wtyczki,
   `AM_TOOLKIT_VERSION` i `Plugin::VERSION`,
2. `composer check`,
3. `composer audit --locked`,
4. budowa i walidacja `am-toolkit-v0.12.4.zip`,
5. dwie deterministyczne budowy z identycznym SHA-256,
6. aktualizacja lokalnego WordPressa i kontrola niezmienników,
7. zielone CI na PHP 8.0 i PHP 8.3.

Weryfikacja lokalnego release candidate z 2 września 2026:

- `composer check`: poprawna składnia 291 plików PHP, analiza PHPStan bez
  błędów oraz 122 testy i 531 asercji,
- walidacja źródła: AM Toolkit 0.12.4, 248 plików PHP i komplet wymaganych
  plików,
- walidacja ZIP: 207 plików, jeden katalog główny `am-toolkit/` i zgodna
  wersja 0.12.4,
- dwie niezależne budowy deterministyczne mają identyczny SHA-256,
- rozmiar finalnej paczki: 323468 B,
- lokalny WordPress połączony junctionem odczytuje wersję 0.12.4 oraz
  niezmienione schematy Access 2, Courses 7 i capabilities 2; flaga `courses`
  pozostała włączona, a baza zachowała 1 kurs, 2 wersje programu, 1 lekcję i
  1 grant,
- niezalogowany ekran „Moje konto” ładuje się bez błędu krytycznego.

Pełna lokalna kontrola spójności danych nie została zaliczona z powodu
istniejących osieroconych rekordów testowych: 91 checkpointów, 1 spotkania,
3 zadań i 3 wpisów postępu zadań. Hotfix nie zawiera migracji ani operacji na
tych danych; znalezisko jest śledzone osobno i nie wolno go ukrywać jako
pozytywnego wyniku QA.

Lokalny `composer audit --locked` nie mógł połączyć się z Packagist z powodu
błędu lokalnego wystawcy certyfikatu TLS. Nie wyłączono weryfikacji TLS. Audyt
oraz pełny workflow Quality potwierdziło GitHub Actions dla PR #30 na PHP 8.0
i PHP 8.3:

`https://github.com/YoungPumba/AM-Toolkit/actions/runs/33566315806`

Paczka `am-toolkit-v0.12.4.zip` ma SHA-256:

```text
AEBBE2627DAF85739652FE4A9799B788F0FE25B02E8E5017B171DECCB7C4B286
```

## Wdrożenie produkcyjne i test iPhone'a

Wdrożenie wymaga osobnego jawnego potwierdzenia właściciela bezpośrednio przed
zmianą produkcji.

1. Utwórz świeżą kopię bazy i plików po ostatnich zmianach w kursach.
2. Zanotuj wersję 0.12.3, wartości `am_toolkit_feature_flags` oraz wersje
   schematów.
3. Zachowaj zweryfikowaną paczkę 0.12.3 jako punkt rollbacku.
4. Wgraj zweryfikowany ZIP 0.12.4 i wybierz zastąpienie aktywnej wtyczki bez
   jej wcześniejszej dezaktywacji.
5. Potwierdź w WordPressie wersję 0.12.4.
6. Opróżnij LiteSpeed i wykonaj twarde odświeżenie.
7. Potwierdź, że flagi, schematy, kursy, granty, postęp i prywatne pliki nie
   uległy zmianie.
8. Na fizycznym iPhonie otwórz problematyczną lekcję z parametrem
   `?am_course_diagnostics=1`.
9. Odtwarzaj tylko do potwierdzenia startu, pauzy, wznowienia i przewinięcia.
   Nie powtarzaj długiego testu, jeżeli film ponownie się zatrzyma.
10. Pobierz raport JSON i sprawdź, czy żądania mają status `206`,
    `partial=true` oraz źródło inne niż `missing`.
11. Zapisz raport i obserwacje w VIA-105, bez publicznego udostępniania pliku.

Jeżeli nowy raport nadal pokazuje pełne odpowiedzi `200` oraz
`range_header_source=missing`, kolejnym krokiem jest konfiguracja Hostinger lub
LiteSpeed. Ponowne kodowanie filmu nie naprawi utraconego nagłówka HTTP.

## Rollback

Jeżeli 0.12.4 spowoduje regresję:

1. zastąp wtyczkę zweryfikowaną paczką `am-toolkit-v0.12.3.zip`,
2. opróżnij LiteSpeed,
3. sprawdź kurs, odtwarzacz, grant i postęp na koncie testowym,
4. nie usuwaj tabel, opcji ani katalogu `am-toolkit-private`,
5. przywracaj bazę lub pliki danych tylko wtedy, gdy kontrola wykaże ich
   rzeczywistą zmianę; sam rollback kodu tego nie wymaga.
