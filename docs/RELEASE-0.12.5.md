# Wydanie AM Toolkit 0.12.5

Status: release candidate. Merge PR, publikacja tagu/GitHub Release i
wdrożenie produkcyjne są osobnymi krokami. Ten dokument nie potwierdza
wdrożenia ani naprawy Safari.

## Cel i zakres

Wersja 0.12.5 pozwala porównać MediaElement z natywnym odtwarzaczem
przeglądarki na tej samej lekcji, bez zmiany odtwarzacza dla pozostałych
uczestników. Wariant natywny wymaga obu parametrów:
`am_course_diagnostics=1&am_course_player=native`.

Raport diagnostyczny wskazuje wariant odtwarzacza, obecność MediaElement,
liczbę utraconych starszych zdarzeń oraz stan przewijania i pochodzenie
zdarzeń przeglądarki. Limity raportów pozostają zachowane.

Testy produkcji 0.12.4 wykazały różnicę w przekazywaniu HEAD/Range przez CDN,
ale wyłączenie CDN nie usunęło zawieszania Safari. Kolejny test rozdziela
wpływ warstwy kontrolek od pozostałych elementów odtwarzania. Natywny wariant
nadal korzysta ze wspólnego zapisu postępu, wznawiania oraz chronionego
endpointu. Awaria obu wariantów nie dowodzi więc sama w sobie winy hostingu.

Uwaga do interpretacji wcześniejszych raportów: serwerowe `bytes_sent`
oznacza bajty wypisane przez PHP, nie zmierzony transfer do telefonu ani
podstawę rozliczenia hostingu. Samo `206` lub zdarzenie `playing` również
nie potwierdza płynnego odtwarzania.

## Niezmienniki

- Nie dodano nowych migracji; schematy Access 2, Courses 7 i capabilities 2
  pozostają bez zmian. Standardowa kontrola migracji przy starcie nadal działa.
- Nie zmieniono opcji `am_toolkit_feature_flags` ani konfiguracji CDN.
- Aktualizacja nie przepisuje kursów, grantów ani dotychczasowego postępu.
  Odtwarzanie podczas testu na koncie uczestnika normalnie zapisuje postęp.
- Chroniony adres nagrania i weryfikacja dostępu pozostają takie same;
  parametr diagnostyczny nie przyznaje dostępu do kursu.
- Nie zmieniono limitu przesyłania filmów, kodowania MP4 ani ich położenia.
- Paczka zastępuje kod w `wp-content/plugins/am-toolkit`; nie zawiera
  prywatnych filmów, bazy ani katalogu `am-toolkit-private`.

## Kontrola release candidate

Przed publikacją wymagane są: spójna wersja, `composer check`, audyt
zależności, testy JavaScript, walidacja ZIP, dwie budowy z identycznym
SHA-256, lokalny test WordPressa i zielone CI PHP 8.0/8.3 oraz JavaScript.

Weryfikacja lokalna z 2 września 2026:

- `composer check`: 292 pliki PHP bez błędów składni, komplet analiz PHPStan
  bez błędów, 134 testy PHPUnit i 563 asercje.
- JavaScript: poprawna składnia oraz 4 zaliczone testy zachowania odtwarzacza.
- Walidacja źródła: spójna wersja 0.12.5, 249 plików PHP i wymagany bootstrap.
- Dwie niezależne budowy: identyczny SHA-256, po 207 plików, jeden katalog
  główny `am-toolkit/`, poprawny autoloader produkcyjny i główny plik jako
  pierwszy wpis ZIP. Rozmiar paczki: 323295 B.
- Porównanie zawartości ZIP z 0.12.4: dokładnie 10 zmienionych plików,
  żadnych dodanych ani usuniętych; zmiany tylko w wersji, README, changelogu,
  CSS/JS odtwarzacza i czterech klasach renderowania/diagnostyki.
- Lokalny WordPress korzystający z junctionu do repozytorium: zwykły adres
  inicjalizuje MediaElement bez panelu diagnostycznego; wariant natywny ma
  własne kontrolki i nie inicjalizuje MediaElement. W obu przypadkach
  potwierdzono odtwarzanie, przywrócenie pozycji i pauzę bez błędu elementu
  video. To test kodu w lokalnym WordPressie, nie instalacji ZIP ani Safari.

Lokalny `composer audit --locked` jest zablokowany błędem wystawcy certyfikatu
TLS, również poza piaskownicą. Nie wyłączono weryfikacji TLS i nie uznano
lokalnego cache za aktualny audyt. Audyt musi przejść w workflow Quality
na PR wydaniowym przed zgodą na merge/publikację.

Nie powtarzano pełnej kontroli spójności lokalnej bazy. Wcześniej znalezione
osierocone dane testowe, opisane w runbooku 0.12.4, nie są przez to wydanie
naprawiane ani uznawane za poprawne. Produkcja i fizyczny iPhone pozostają
do sprawdzenia po osobno zaakceptowanym wdrożeniu.

Paczka `am-toolkit-v0.12.5.zip` ma SHA-256:

```text
502C95A7EF06E6ADA3D1A4908E02937694FA7E06013B59581EF6518EFDF960B6
```

Po scaleniu należy odtworzyć paczkę z commita przeznaczonego na tag i
potwierdzić tę sumę przed publikacją; sam lokalny ZIP nie jest Release.

## Wdrożenie i krótki test iPhone'a

Wymagana jest osobna, jawna zgoda właściciela bezpośrednio przed wdrożeniem.

1. Przygotuj świeżą kopię plików i bazy po ostatnich zmianach kursów oraz
   zweryfikowany ZIP 0.12.4 do rollbacku. Zanotuj flagi i wersje schematów.
2. Sprawdź SHA-256 opublikowanej paczki 0.12.5. W WordPressie zastąp obecną
   wtyczkę ZIP-em bez usuwania wtyczki i bez zmiany katalogu prywatnych nagrań.
3. Potwierdź wersję 0.12.5, opróżnij cache LiteSpeed i odśwież stronę.
   Nie zmieniaj jednocześnie ustawień CDN ani samego filmu.
4. Sprawdź panel administratora, kurs i normalne odtwarzanie na komputerze.
   Porównaj flagi, schematy i dostęp z zapisanym stanem sprzed aktualizacji.
5. Na fizycznym iPhonie, na tym samym koncie, telefonie i połączeniu otwórz
   ustaloną problematyczną lekcję w wariancie A:
   `?am_course_diagnostics=1&am_course_player=mediaelement`.
6. Sprawdź etykietę wariantu w panelu diagnostycznym. Krótko przetestuj start,
   pauzę, wznowienie i przewinięcie; zapisz moment problemu i pobierz JSON.
   Po zawieszeniu nie powtarzaj długich prób; zatrzymaj film lub zamknij kartę.
7. Otwórz tę samą lekcję w wariancie B:
   `?am_course_diagnostics=1&am_course_player=native`.
   Powtórz te same czynności w tym samym fragmencie filmu. Uwzględnij, że
   zapisany postęp może wznowić film w późniejszym miejscu. Pobierz drugi JSON.
8. Porównaj objawy i oba raporty, w tym `player_mode`, obecność MediaElement,
   utracone zdarzenia, bufor, przewijanie oraz odpowiedzi serwera. Raporty
   przekazuj prywatnie. Bez raportów i obserwacji nie zamykaj problemu Safari.
9. Usuń parametry testowe z adresu: zwykłe wejście nadal używa MediaElement.

Pełna procedura i ograniczenia są w
[dokumentacji diagnostyki](COURSES-DIAGNOSTICS.md#porównanie-standardowego-i-natywnego-odtwarzacza).
Test przeglądarki desktopowej nie zastępuje powyższego testu fizycznego iPhone'a.

## Rollback

W razie regresji zastąp kod zweryfikowanym `am-toolkit-v0.12.4.zip`, opróżnij
cache i sprawdź kurs, nagranie, dostęp i postęp na koncie testowym. Parametry
wariantu natywnego nie działają w 0.12.4. Nie usuwaj tabel, opcji ani
prywatnych nagrań. Przywrócenie całej bazy wymaga osobnej decyzji i oceny
zmian od kopii — mogłoby usunąć nowe zamówienia lub postęp uczestników.
