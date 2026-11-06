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
`v0.11.5`. Bieżący `main` rozwija linię 0.12.x i nie może być źródłem paczki
0.11.5.

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

## Macierz zgodności i aktualizacje środowiska

Odczyt z 6 listopada 2026 jest punktem odniesienia, a nie potwierdzeniem
zgodności bieżących wersji produkcyjnych:

- produkcja: WordPress 7.1.4, PHP 8.3.36 i WooCommerce 11.2.1; rdzeń
  WordPressa jest aktualny, natomiast panel wskazuje 23 aktualizacje wtyczek
  oraz aktualizację motywu;
- środowisko lokalne: WordPress 7.0.2, PHP web 8.3.29, WooCommerce 11.0.0,
  Elementor i Elementor Pro 4.2.1, LiteSpeed Cache 7.8.1 oraz ShopEngine
  4.9.2 i ShopEngine Pro 2.7.5.

Lokalna macierz jest starsza od produkcyjnej, więc jej wynik nie certyfikuje
zgodności z WordPressem 7.1.4, PHP 8.3.36 ani WooCommerce 11.2.1. Zmianę
odtwarzacza należy zweryfikować i wdrażać osobno. Kolejne aktualizacje
prowadzimy etapami: najpierw WooCommerce wraz z płatnościami, następnie
Elementor, Elementor Pro i ShopEngine, a później osobne partie cache,
obsługi zgód i analityki. Motyw oraz pozostałe wtyczki wymagają własnej
inwentaryzacji i kontrolowanego etapu. Każdy etap wymaga testów regresji oraz
z góry określonego rollbacku. Przed jakąkolwiek zmianą produkcji obowiązują
trzy bramki: potwierdzona kopia, sprawdzona procedura odtworzenia i jawna zgoda
na wdrożenie.

Szczegółowy plan wdrożenia, aktywacji flag i rollbacku AM Courses 0.12.0
znajduje się w [runbooku wydania 0.12.0](RELEASE-0.12.0.md).
Procedurę małego hotfixu mobilnego opisuje
[runbook wydania 0.12.1](RELEASE-0.12.1.md).
Stabilizację chronionego odtwarzania oraz kontrolowane przejście produkcji
bezpośrednio z 0.12.0 do 0.12.2 opisuje
[runbook wydania 0.12.2](RELEASE-0.12.2.md).
Kontrolowane udostępnienie diagnostyki problemów odtwarzacza na iOS i Safari
opisuje [runbook wydania 0.12.3](RELEASE-0.12.3.md).
Naprawę przekazywania nagłówka Range między LiteSpeed i WordPressem oraz
kontrolowany test produkcyjny opisuje
[runbook wydania 0.12.4](RELEASE-0.12.4.md).
Diagnostyczne porównanie odtwarzaczy MediaElement i native na Safari opisuje
[runbook wydania 0.12.5](RELEASE-0.12.5.md).
Niewydany kandydat z natywnym domyślnym odtwarzaczem i rozszerzoną
diagnostyką ma osobny [runbook 0.12.6](RELEASE-0.12.6.md).

Tagów opublikowanych na GitHub nie przesuwamy. Jeśli błąd zostanie wykryty
przed publikacją, poprawiamy commit i ponawiamy weryfikację. Jeśli po
publikacji, przygotowujemy kolejne wydanie naprawcze.
