# Wydania AM Toolkit

## Źródła prawdy

- `main` zawiera bieżącą wersję rozwojową.
- Opublikowany tag wskazuje niezmienny, zweryfikowany commit wydania.
- GitHub Release zawiera instalacyjny ZIP zbudowany z tego commita oraz jego
  sumę SHA-256.
- Sam merge do `main` nie jest wydaniem ani dowodem wdrożenia na produkcji.

## Linie utrzymaniowe

Historyczne wydanie można poprawić wyłącznie na osobnej linii utrzymaniowej
utworzonej od ostatniego commita tej wersji. Nie wolno w tym celu cofać
`main` ani oznaczać jego nowszego kodu starszym numerem.

Dla 0.11.5 linią bazową jest commit `e11b086`. Naprawa metadanych wersji
powstaje na gałęzi 0.11.x i dopiero jej zweryfikowany commit może otrzymać tag
`v0.11.5`. Bieżący `main` rozwija 0.12.0 i nie może być źródłem paczki 0.11.5.

## Brakujące tagi 0.11.x

Tagi `v0.11.3` i `v0.11.4` są uzupełniane retrospektywnie na istniejących,
spójnych commitach wydaniowych odpowiednio `d5cbf5c` i `5b0628a`. Nie tworzymy
dla nich pozornych, retrospektywnych GitHub Releases. Wydanie 0.11.5 otrzymuje
pełny GitHub Release, ponieważ jest wymaganym i zweryfikowanym punktem rollbacku
przed wdrożeniem 0.12.0.

## Procedura wydania

1. Sprawdź, że deklaracje wersji w nagłówku, `AM_TOOLKIT_VERSION` oraz
   `Plugin::VERSION` są identyczne.
2. Uruchom `composer check` i audyt zależności.
3. Zbuduj paczkę według [instrukcji pakowania](../.build/PACKAGING.md).
4. Ponownie zweryfikuj gotowy ZIP i oblicz SHA-256.
5. Sprawdź instalację lub aktualizację na właściwym środowisku testowym.
6. Dopiero po akceptacji utwórz niezmienny tag i GitHub Release z paczką,
   sumą kontrolną, opisem zmian i planem rollbacku.
7. Status wdrożenia zapisuj wyłącznie na podstawie dowodu z właściwego
   środowiska; nie wyprowadzaj go z istnienia tagu ani Release.

Szczegółowy plan wdrożenia, aktywacji flag i rollbacku AM Courses 0.12.0
znajduje się w [runbooku wydania 0.12.0](RELEASE-0.12.0.md).

Tagów opublikowanych na GitHub nie przesuwamy. Jeśli błąd zostanie wykryty
przed publikacją, poprawiamy commit i ponawiamy weryfikację. Jeśli po
publikacji, przygotowujemy kolejne wydanie naprawcze.
