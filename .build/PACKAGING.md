# Stały schemat paczek AM Toolkit

Politykę linii utrzymaniowych, tagów i GitHub Releases opisuje
[`docs/RELEASES.md`](../docs/RELEASES.md).

Za wzorzec referencyjny przyjmujemy działającą paczkę:

`am-toolkit-v0.9.0.zip`

Każde następne wydanie musi zachować ten sam schemat rozpoznawania przez
WordPress:

```text
am-toolkit-vX.Y.Z.zip
└── am-toolkit/
    ├── am-toolkit.php
    ├── CHANGELOG.md
    ├── README.md
    ├── ROADMAP.md
    ├── assets/
    ├── src/
    └── templates/
```

## Reguły niezmienne

1. Nazwa pliku ZIP zawiera numer wersji: `am-toolkit-vX.Y.Z.zip`.
2. Wewnątrz ZIP znajduje się dokładnie jeden katalog główny: `am-toolkit/`.
3. Główny plik wtyczki znajduje się zawsze pod ścieżką
   `am-toolkit/am-toolkit.php`.
4. `am-toolkit/am-toolkit.php` jest pierwszym wpisem w archiwum.
5. Archiwum zawiera wyłącznie wpisy plików, bez osobnych pustych wpisów
   katalogów.
6. Ścieżki wewnątrz ZIP używają ukośnika `/`.
7. Numer wersji w nazwie ZIP jest zgodny z polem `Version` w
   `am-toolkit.php`.
8. Katalogu `am-toolkit/` nie wolno zastępować nazwą zawierającą wersję.
9. Nie wolno pakować plików bezpośrednio w katalogu głównym ZIP.
10. Nie wolno tworzyć podwójnego zagnieżdżenia
    `am-toolkit/am-toolkit/am-toolkit.php`.

Kod, zasoby i nowe moduły mogą być dodawane albo zastępowane. Powyższy
schemat instalacyjny pozostaje niezmienny.

## Budowanie wydania

Z katalogu źródłowego wtyczki:

```powershell
powershell -ExecutionPolicy Bypass `
    -File .build/build-release.ps1 `
    -OutputDirectory ..\..\outputs
```

Jeśli paczka danej wersji już istnieje i świadomie ma zostać przebudowana:

```powershell
powershell -ExecutionPolicy Bypass `
    -File .build/build-release.ps1 `
    -OutputDirectory ..\..\outputs `
    -Force
```

Numer wersji jest pobierany automatycznie z `am-toolkit.php`. Generator
tworzy paczkę, sprawdza jej strukturę, a dopiero po udanym teście zapisuje
plik docelowy.

## Sprawdzanie istniejącej paczki

```powershell
powershell -ExecutionPolicy Bypass `
    -File .build/validate-release.ps1 `
    -ArchivePath ..\..\outputs\am-toolkit-v0.9.0.zip
```

Wydania należy przechowywać jako osobne, wersjonowane pliki ZIP. Nie
tworzymy równoległej paczki `am-toolkit.zip`, ponieważ łatwo pomylić ją z
inną wersją.
