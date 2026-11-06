# Kandydat AM Toolkit 0.12.6

Status na 6 listopada 2026: lokalny, niewydany kandydat na gałęzi
`codex/native-course-player-default`. Tag, Release i wdrożenie są osobnymi
krokami po review i zielonym CI. Produkcja pozostaje na 0.12.5.
Ten dokument nie potwierdza naprawy Safari.

## Zakres

- Natywne kontrolki w zwykłej lekcji i podglądzie. MediaElement dostępny przez
  jawny test diagnostyczny lub awaryjną stałą operatora.
- Wspólne chronione źródło MP4, dostęp, postęp, wznowienie i komunikaty błędów.
- Diagnostyczny pomiar braku ruchu `currentTime`, powrót z BFCache, zamknięcie
  pomiaru po błędzie oraz ograniczony Resource Timing z kotwicą czasu.
- Brak nowych migracji, zmian grantów, plików kursowych, CDN i flag.
  Standardowa kontrola wersji przy uruchomieniu nadal działa. Test odtwarzania
  normalnie zapisuje postęp konta testowego.
- Audyt `.build/inspect-course-videos-local.php` służy tylko do odczytu kopii
  w Local i nie trafia do instalacyjnego ZIP-a. Biblioteka, bezpieczna podmiana
  i automatyczny konwerter nie są wdrożone; warunki opisuje
  [przygotowanie nagrań](COURSES-VIDEO-PREPARATION.md).

## Weryfikacja lokalna

- Wszystkie składniki `composer check` przeszły w podzielonych uruchomieniach:
  składnia, testy kontraktowe i grupy PHPStan. Sam wrapper nie zakończył się
  sukcesem: Courses wyczerpało limit 768M; osobna analiza Courses z limitem
  1536M oraz pozostałe składniki zakończyły się poprawnie.
- Końcowy PHPUnit: 153 testy, 617 asercji; JS: 9 testów i poprawna składnia.
  Po poprawkach diagnostyki ponowiono analizę zmienionych plików PHP.
- WordPress 7.0.2 / PHP web 8.3.29 / WooCommerce 11.0.0: zwykły widok natywny
  bez MediaElement i panelu diagnostycznego; odtwarzanie, przewijanie, pauza,
  wznowienie pozycji po odświeżeniu oraz widoczny zapis postępu działają.
  Podgląd ma natywne kontrolki i nie renderuje panelu postępu/diagnostyki.
  Jawne warianty native i MediaElement odtwarzają film i eksportują JSON.
  To test kodu przez junction do repo, nie instalacji ZIP-a ani iPhone'a.
- Końcowy lokalny JSON 0.12.6: tryb native, brak MediaElement, 56 zdarzeń
  klienta bez utraconych wpisów, brak błędów medium i `playback-stall-start`,
  kotwica czasu obecna, 3 wpisy Resource Timing i 6 zdarzeń serwera `206`.
  Konto testowe osiągnęło próg ukończenia lekcji; stan przetrwał odświeżenie.
- Lokalny audyt: jeden prywatny plik 7 919 637 B, poprawny Fast Start, brak
  brakujących plików, duplikatów, sierot i nieczytelnych referencji. Nie jest
  to inwentaryzacja biblioteki produkcyjnej.
- ZIP: 326 440 B, 208 plików, właściwy katalog i autoloader produkcyjny.
  Dwie niezależne budowy mają identyczny SHA-256:

```text
E006E190342F04C463448C81CFDD44C13CEC5A1A7A76414551BB22E7830EFFFD
```

Paczka lokalna: `.build-output/candidate-0.12.6/am-toolkit-v0.12.6.zip`.
Po scaleniu trzeba odtworzyć ją z dokładnego commita przeznaczonego na tag.

## Otwarte bramki

- Aktualny `composer audit --locked`: PASS, brak zgłoszonych podatności.
  Domyślna konfiguracja PHP zgłaszała błąd wystawcy TLS; audyt przeszedł po
  jednorazowym wskazaniu pakietu publicznych certyfikatów już zaufanych przez
  Windows. Nie zmieniono magazynu zaufania ani konfiguracji PHP i nie
  wyłączono weryfikacji TLS. Wymagane nadal zielone CI PHP 8.0/8.3, JS i audyt
  dla dokładnego commita kandydata.
- Osobny audyt stylu PHPCS nie przechodzi: reguły WordPress odrzucają format
  przyjęty przez istniejące klasy (także nową klasę trybu odtwarzacza).
  Nie jest częścią `composer check` ani obecnego CI; nie deklarujemy PASS.
- Pełny ekran w osadzonej przeglądarce nie został jednoznacznie potwierdzony;
  wymaga sprawdzenia razem z fizycznym iPhonem. Brak wykrytego stalla na
  komputerze nie dowodzi płynności Safari. Wcześniejszy raport C2 pokazał
  problem również z natywnym odtwarzaczem.
- Lokalna macierz jest starsza od produkcji. Aktualizacje WooCommerce,
  płatności, Elementora i pozostałych rozszerzeń są osobnym etapem według
  [macierzy zgodności](RELEASES.md#macierz-zgodności-i-aktualizacje-środowiska).
- Wcześniejsze osierocone dane testowe nie są naprawione przez tę zmianę.

## Przygotowanie, kontrolowane wdrożenie i akceptacja

1. Review i PR, zielone CI oraz audyt; publikacja niezmiennego tagu/Release
   dopiero po przyjęciu kandydata. Zweryfikować instalację ZIP-a na kopii.
2. Potwierdzić świeżą kopię bazy, plików strony i magazynu prywatnego oraz
   możliwość odtworzenia. Przygotować oryginalny ZIP 0.12.5 do rollbacku.
3. Uzyskać osobną zgodę właściciela na dokładne wdrożenie 0.12.6.
4. Wymienić sam kod wtyczki, wyczyścić cache i sprawdzić wersję, panel,
   chroniony dostęp oraz postęp. Nie łączyć z aktualizacjami innych wtyczek
   ani zmianą MP4/CDN.
5. Po osobno zaakceptowanym, kontrolowanym wdrożeniu wykonać krótki A/B na
   fizycznym iPhonie jako test akceptacyjny z gotowym rollbackiem, według
   [procedury diagnostycznej](COURSES-DIAGNOSTICS.md), w tej samej sieci i na
   tym samym pliku. Przy pierwszym zacięciu pobrać JSON, nie mnożyć pobrań.
6. Przy regresji przywrócić kod 0.12.5 i cache. Pełne odtworzenie bazy nie jest
   zwykłym rollbackiem kodu: cofa też nowsze zamówienia i postęp, wymaga
   osobnego uzgodnienia.
