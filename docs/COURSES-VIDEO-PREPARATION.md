# Przygotowanie nagrań do AM Courses

Ten dokument opisuje redakcyjny sposób przygotowania nagrania przed wgraniem
go do AM Courses. Celem jest płynne odtwarzanie w przeglądarce, przewidywalny
rozmiar pliku i zgodność z komputerami, Androidem, Safari oraz iPhone'em.

## Zalecany format

Nagranie kursowe powinno być zapisane jako:

- kontener **MP4** (`.mp4`),
- obraz **H.264/AVC**,
- dźwięk **AAC stereo**, 128–160 kb/s,
- maksymalnie **1920×1080**,
- 25 albo 30 kl./s; 60 kl./s tylko wtedy, gdy materiał rzeczywiście tego
  wymaga,
- materiał **Web Optimized / Fast Start**, czyli z metadanymi MP4 przed danymi
  obrazu.

Nie należy zmieniać samego rozszerzenia pliku. Przemianowanie `film.mp4` na
`film.webm` nie konwertuje nagrania i może jedynie popsuć jego rozpoznawanie.

## Kiedy nagranie wymaga poprawy

Plik należy przygotować ponownie, gdy:

- panel AM Courses zgłasza brak Fast Start lub metadane `moov` za `mdat`,
- źródło ma rozdzielczość 4K bez wyraźnej potrzeby redakcyjnej,
- plik jest nieproporcjonalnie duży w stosunku do czasu nagrania,
- film długo się uruchamia, często buforuje albo źle wznawia odtwarzanie,
- używa kodeka, którego nie obsługuje docelowa przeglądarka.

Nie każde istniejące MP4 trzeba automatycznie kodować ponownie. Najpierw należy
sprawdzić jego parametry i zachowanie. Ponowne kodowanie zawsze kosztuje czas i
może obniżyć jakość, więc nie wykonujemy go dla sportu.

## Najprostsza procedura w HandBrake

1. Zachowaj oryginalny plik jako kopię źródłową.
2. Otwórz nagranie w [HandBrake](https://handbrake.fr/).
3. Wybierz **Presets → General → Fast 1080p30**. Jest to szeroko kompatybilny
   preset MP4/H.264/AAC rekomendowany jako bezpieczny punkt wyjścia w
   [oficjalnej dokumentacji HandBrake](https://handbrake.fr/docs/en/latest/technical/official-presets.html).
4. W zakładce **Summary** wybierz `MP4` i zaznacz **Web Optimized**.
5. W zakładce **Video** pozostaw `H.264 (x264)`, 30 kl./s albo
   `Same as source` oraz jakość około `RF 21–22`.
6. W zakładce **Audio** wybierz `AAC`, stereo i 128 albo 160 kb/s.
7. Zapisz wynik pod nową nazwą, np. `lekcja-01-web.mp4`. Nie nadpisuj źródła.
8. Uruchom **Start Encode**.

Opcja Web Optimized przenosi metadane potrzebne odtwarzaczowi na początek
pliku. HandBrake opisuje ją jako ustawienie przeznaczone głównie do
[strumieniowania MP4 w internecie](https://handbrake.fr/docs/en/1.7.0/technical/performance.html#other-factors).

## Wariant bez utraty jakości

Jeśli obraz jest już w 1080p, używa H.264/AAC i ma rozsądny bitrate, a jedynym
problemem jest położenie metadanych, plik można tylko przepakować:

```powershell
ffmpeg -i "film-zrodlowy.mp4" -map 0 -c copy -movflags +faststart "film-web.mp4"
```

Ta operacja nie koduje ponownie obrazu ani dźwięku. Nadal trzeba obejrzeć plik
wynikowy i sprawdzić przewijanie, ponieważ poprawna komenda nie naprawi
uszkodzonego źródła.

## Podmiana w kursie jest obecnie zablokowana

Aktualny model danych **nie izoluje nagrania w szkicu**. Wersja programu
przypina identyfikator mutowalnej lekcji, a nie historyczną referencję filmu.
Zapis lekcji może więc zmienić nagranie widoczne również w opublikowanych
wersjach. Po udanym wgraniu kod natychmiast usuwa poprzedni prywatny plik, bez
globalnego sprawdzenia użycia przez wszystkie wersje. Podgląd szkicu nie jest
tu siatką bezpieczeństwa — tylko wygląda, jakby nią był.

Do czasu wdrożenia wersjonowanych assetów, przypiętych rewizji i retencji:

1. Nie podmieniaj nagrania w kursie produkcyjnym według samej procedury z tego
   dokumentu i nie poprawiaj referencji bezpośrednio w bazie.
2. Pracuj wyłącznie na lokalnej lub stagingowej kopii strony oraz kopiach
   plików. Zachowaj oryginał i wynik konwersji pod osobnymi referencjami.
3. Lokalnie sprawdź początek, kilka miejsc w środku, koniec, przewijanie,
   pauzę, wznowienie oraz zapis postępu.
4. Wynik traktuj jako materiał do późniejszej migracji, nie jako zgodę na
   ręczną podmianę produkcyjną.
5. Nie usuwaj ani nie przenoś istniejącego pliku. Obecny panel nie potwierdza
   bezpiecznie braku użycia historycznego.

### Planowany model bezpiecznej publikacji — jeszcze niewdrożony

- Każdy oryginał będzie niemutowalnym prywatnym assetem. Remux lub transkodowanie
  utworzy nowy derivative; nigdy nie nadpisze źródła.
- Szkic przypnie konkretny asset i oczekiwaną rewizję. Zapis typu
  compare-and-swap zostanie odrzucony, jeśli ktoś wcześniej zmienił szkic.
- Publikacja utworzy niemutowalną rewizję lekcji i przypnie jej dokładny asset
  do wersji programu. Dotychczas opublikowana wersja pozostanie aktywna, dopóki
  komplet nowej wersji nie będzie gotowy.
- Trwałe, idempotentne zadanie zapisze wejście, profil, wynik, próby i stan.
  `ffprobe` zweryfikuje metadane, a wynik `.part` zostanie atomowo przeniesiony
  dopiero po kontroli MP4, Fast Start, czasu i sumy pliku.
- Ciężka konwersja wykona się w kontrolowanym procesie CLI albo zewnętrznym
  workerze po sprawdzeniu dostępności `ffmpeg`/`ffprobe`, limitów dysku i
  funkcji procesowych. Nie będzie wykonywana w żądaniu HTTP ani polegała
  wyłącznie na WP-Cronie.
- Archiwizacja jedynie ukryje asset przed nowym wyborem. Fizyczne usunięcie
  będzie możliwe dopiero po zerowym użyciu w szkicach, wszystkich opublikowanych
  i historycznych rewizjach oraz aktywnych zadaniach, z kopią lub kwarantanną.

## Kontrola istniejącej biblioteki

Istniejące nagrania należy zinwentaryzować przed masową konwersją. Dla każdego
pliku zapisujemy co najmniej:

- lekcję i kurs, które go używają,
- nazwę redakcyjną oraz wewnętrzny identyfikator,
- rozdzielczość, kodek, liczbę klatek i orientacyjny bitrate,
- rozmiar i czas trwania,
- wynik kontroli Fast Start,
- decyzję: bez zmian, samo przepakowanie albo ponowne kodowanie do 1080p.

Najpierw poprawiamy filmy używane w aktywnych kursach i faktycznie zgłaszane
przez uczestniczki. Oryginały zachowujemy do zakończenia testów oraz
potwierdzenia kopii zapasowej.

### Lokalny audyt techniczny

Przed planowaniem podmian można uruchomić bezpieczny raport na kopii strony w
Local:

```powershell
php .build/inspect-course-videos-local.php `
  "C:\Users\<użytkownik>\Local Sites\<strona>\app\public\wp-load.php" `
  "127.0.0.1:<port-bazy>"
```

Opcjonalne `--hash` oblicza strumieniowo sumy SHA-256 i wskazuje pliki o tej
samej zawartości:

```powershell
php .build/inspect-course-videos-local.php `
  "C:\Users\<użytkownik>\Local Sites\<strona>\app\public\wp-load.php" `
  "127.0.0.1:<port-bazy>" `
  --hash
```

Skrypt dopuszcza wyłącznie ścieżkę `Local Sites/.../app/public/wp-load.php`,
adres strony z końcówką `.local` lub loopback oraz bazę na loopbacku z jawnym
portem. Prywatny magazyn również musi znajdować się wewnątrz katalogu tej
lokalnej strony. Wynik JSON nie zawiera nazw kursów i lekcji, identyfikatorów
użytkowników, surowych referencji plików, ścieżek ani publicznych adresów URL.
Pokazuje zagregowane użycia w lekcjach i wersjach programu, brakujące,
nieczytelne lub niepoprawne referencje, powtórzone referencje, nieprzypisane
pliki, rozmiary i wynik Fast Start.

Raport używa `SHORTINIT`: nie uruchamia wtyczek, migracji ani haków aplikacji.
Sam wykonuje wyłącznie zapytania `SELECT` i odczyty systemu plików. Bootstrap
WordPressa nadal otwiera lokalne połączenie z bazą i może załadować standardowe
drop-iny bazy lub cache, więc audyt uruchamiamy na kopii lokalnej, nigdy na
produkcji. Niczego nie naprawia, nie przenosi i nie usuwa.

Użycie przez opublikowane wersje jest obecnie wyliczane przez połączenie
przypisania programu z bieżącym, mutowalnym rekordem lekcji. Schemat nie
przechowuje historycznej referencji nagrania w wersji programu, dlatego raport
nie odtworzy wcześniej zastąpionych ani usuniętych referencji.
