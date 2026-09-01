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

## Bezpieczna podmiana w kursie

1. Nie usuwaj oryginalnego pliku przed sprawdzeniem nowej wersji.
2. Otwórz wynik lokalnie i sprawdź początek, kilka miejsc w środku oraz koniec.
3. Wgraj zoptymalizowaną wersję i przypisz ją do właściwej lekcji.
4. Zapisz szkic i sprawdź podgląd właścicielki.
5. Na koncie testowym sprawdź start, przewijanie, pauzę, wznowienie i zapis
   postępu.
6. Dopiero po potwierdzeniu opublikuj zmianę.
7. Oryginał usuń wyłącznie wtedy, gdy panel potwierdza brak użycia przez inne
   szkice, opublikowane wersje lub historię kursu.

Do czasu wdrożenia prywatnej biblioteki i bezpiecznego usuwania plików ostatni
krok wykonuje administrator techniczny. Nie należy usuwać rekordów ani plików
bezpośrednio w bazie danych na podstawie samej nazwy.

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
