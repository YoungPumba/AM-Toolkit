# Codzienne uruchamianie środowiska AM Toolkit na Windows

Ten runbook opisuje zwykłą sesję pracy po jednorazowym skonfigurowaniu
komputera. Instalację PHP, Composera, Local i certyfikatów opisuje
`DEVELOPMENT-SETUP-WINDOWS.md`.

## Oczekiwany efekt

Środowisko jest gotowe do pracy, gdy jednocześnie:

- strona `AM Toolkit Dev` ma w Local zielony status,
- `https://am-toolkit-dev.local` otwiera się bez błędu certyfikatu,
- WooCommerce i AM Toolkit są aktywne,
- konsola przeglądarki pokazuje bieżącą wersję AM Toolkit,
- `composer check` nie znajduje błędów składni,
- `wp-content/debug.log` nie zawiera nowego błędu krytycznego.

Nie uruchamiamy osobno nginx, PHP ani MariaDB. Zarządza nimi Local. Composer
również nie jest usługą działającą w tle — uruchamia się tylko na czas
wykonania konkretnego polecenia.

## 1. Uruchomienie sesji

### Krok 1: uruchom Local

1. Otwórz aplikację **Local**.
2. Wybierz stronę **AM Toolkit Dev**.
3. Kliknij **Start site**, jeśli witryna jest zatrzymana.
4. Poczekaj na zieloną kropkę i przycisk **Stop site**.

Po restarcie komputera nie twórz ponownie połączenia `Junction`. Jest trwałe i
powinno nadal wskazywać repozytorium.

### Krok 2: otwórz właściwy katalog w VS Code

W VS Code otwórz katalog repozytorium AM Toolkit, a nie katalog
`wp-content/plugins/am-toolkit` widoczny przez Local.

Docelowy układ zespołowy:

```text
C:\Projects\am-toolkit
```

Obecna instalacja może korzystać z innej ścieżki. Źródłem prawdy jest katalog,
na który wskazuje `Target` połączenia:

```powershell
$link = "C:\Users\<użytkownik>\Local Sites\am-toolkit-dev\app\public\wp-content\plugins\am-toolkit"
Get-Item $link | Select-Object FullName, LinkType, Target
```

Oczekiwany `LinkType`:

```text
Junction
```

### Krok 3: otwórz terminal projektu

W VS Code wybierz **Terminal → New Terminal**. Prompt powinien kończyć się
nazwą katalogu repozytorium.

Sprawdź narzędzia:

```powershell
php --version
composer --version
git --version
```

Nie uruchamiaj tych poleceń z `C:\Windows\System32`. Polecenia globalne będą
działać, ale komendy projektu muszą być wykonywane z katalogu AM Toolkit.

### Krok 4: zsynchronizuj zależności

Po pierwszym pobraniu projektu oraz po każdej zmianie `composer.lock` wykonaj:

```powershell
composer install
```

Nie ma potrzeby uruchamiać `composer install` przy każdym otwarciu projektu,
jeśli `composer.lock` się nie zmienił i katalog `vendor` istnieje.

### Krok 5: sprawdź kod przed pracą

```powershell
composer check
```

Oczekiwany rezultat zawiera:

```text
No syntax error found
```

Jeżeli kontrola nie przechodzi przed rozpoczęciem zadania, nie dokładaj na
ślepo kolejnych zmian. Najpierw ustal, czy pobrana gałąź jest kompletna.

### Krok 6: otwórz WordPress

W Local kliknij:

- **Open site** — frontend,
- **WP Admin** — panel WordPressa.

Adresy domyślne:

```text
https://am-toolkit-dev.local
https://am-toolkit-dev.local/wp-admin/
```

W **Wtyczki → Zainstalowane wtyczki** sprawdź, czy aktywne są:

- WooCommerce,
- AM Toolkit.

### Krok 7: potwierdź wersję AM Toolkit

Na frontendzie otwórz narzędzia deweloperskie przeglądarki i konsolę. Powinien
pojawić się wpis podobny do:

```text
[AM Toolkit] v0.11.2 initialized.
```

Możesz również wpisać:

```javascript
AMToolkit.version
```

Wersja musi być zgodna z `Version` w `am-toolkit.php`. Numer `0.11.2` jest
przykładem bieżącym w chwili tworzenia dokumentu i będzie się zmieniał.

## 2. Praca nad zmianą

### Utwórz gałąź

Po podłączeniu katalogu do Git rozpocznij zadanie na osobnej gałęzi:

```powershell
git switch main
git pull --ff-only
git switch -c feature/krotka-nazwa-zadania
```

Nie rozwijaj nowych funkcji bezpośrednio na `main`.

### Edytuj tylko źródło

Zmiany zapisuj w repozytorium. Junction udostępni je WordPressowi natychmiast.
Nie kopiuj ręcznie plików do katalogu Local i nie instaluj roboczych ZIP-ów.

Po zmianie:

- PHP — zwykle wystarczy odświeżyć stronę,
- CSS/JavaScript — użyj `Ctrl + F5` albo włącz **Disable cache** w DevTools,
- hooki aktywacyjne lub struktura bazy — może być potrzebna ponowna aktywacja
  wtyczki albo przewidziana migracja.

Nie zwiększaj numeru wersji tylko po to, aby odświeżyć cache lokalnie. Numer
wersji zmieniamy świadomie przy wydaniu.

### Sprawdzaj log WordPressa

Log znajduje się w lokalnej witrynie:

```text
C:\Users\<użytkownik>\Local Sites\am-toolkit-dev\app\public\wp-content\debug.log
```

Podgląd ostatnich wpisów:

```powershell
Get-Content "C:\Users\<użytkownik>\Local Sites\am-toolkit-dev\app\public\wp-content\debug.log" -Tail 100
```

Śledzenie nowych wpisów na żywo:

```powershell
Get-Content "C:\Users\<użytkownik>\Local Sites\am-toolkit-dev\app\public\wp-content\debug.log" -Wait
```

Plik może jeszcze nie istnieć, jeśli WordPress nie zapisał żadnego komunikatu.

### Testuj właściwe role

Co najmniej sprawdź:

1. niezalogowanego użytkownika w oknie incognito,
2. zwykłego klienta,
3. administratora,
4. desktop,
5. telefon lub tryb responsywny,
6. scenariusz bez danych oraz z przykładowymi danymi.

Do testów używaj wyłącznie kont i zamówień lokalnych. Nie kopiuj danych klientów
z produkcji.

## 3. Kontrole przed commitem

Uruchom:

```powershell
composer check
composer audit
git status
git diff
```

Dodatkowa kontrola standardów WordPressa:

```powershell
composer audit:style
```

`audit:style` może nadal raportować odziedziczony dług starszych modułów.
Naprawiaj kod w zakresie zadania i nie uruchamiaj automatycznej przebudowy
całego projektu bez przeglądu różnic.

Przed commitem potwierdź:

- brak błędu krytycznego w `debug.log`,
- brak nowych błędów konsoli związanych z AM Toolkit,
- poprawne kodowanie polskich znaków,
- brak haseł, tokenów, certyfikatów i danych klientów,
- brak `vendor`, ZIP-ów i plików środowiska lokalnego w zmianach Git.

## 4. Zakończenie sesji

1. Zapisz pliki.
2. Uruchom `composer check`.
3. Sprawdź `git status`.
4. Zacommituj ukończoną zmianę albo pozostaw ją świadomie na własnej gałęzi.
5. Zatrzymaj proces śledzenia logu kombinacją `Ctrl + C`, jeśli był uruchomiony.
6. W Local kliknij **Stop site**.
7. Zamknij Local, jeśli nie będzie już używany.

Zatrzymanie Local nie usuwa bazy, strony ani połączenia Junction. Przy następnej
sesji wystarczy ponownie uruchomić witrynę.

## 5. Czego nie uruchamiać

- Nie uruchamiaj `php -S` — serwer zapewnia Local.
- Nie uruchamiaj osobnego MySQL lub MariaDB dla tej witryny.
- Nie używaj przycisków Local **Pull** lub **Push** do wdrażania AM Toolkit bez
  osobno zatwierdzonej procedury.
- Nie instaluj roboczej paczki ZIP na produkcji.
- Nie usuwaj AM Toolkit z panelu wtyczek, gdy katalog jest Junctionem.
- Nie wykonuj `git push --force` na `main`.

## 6. Szybka diagnostyka

### Strona się nie otwiera

1. Sprawdź, czy Local pokazuje zielony status.
2. Kliknij **Stop site**, a następnie **Start site**.
3. Użyj pełnego adresu `https://am-toolkit-dev.local`.
4. Sprawdź zakładkę **Tools** i logi nginx/PHP w Local.

### Przeglądarka pokazuje problem z certyfikatem

1. W Local użyj **Trust**.
2. Zamknij wszystkie okna przeglądarki.
3. Uruchom przeglądarkę ponownie.
4. Sprawdź, czy adres zaczyna się od `https://`.

### AM Toolkit nie pojawia się na liście wtyczek

Sprawdź połączenie:

```powershell
$link = "C:\Users\<użytkownik>\Local Sites\am-toolkit-dev\app\public\wp-content\plugins\am-toolkit"
Get-Item $link | Select-Object FullName, LinkType, Target
Test-Path "$link\am-toolkit.php"
```

Oczekiwane wyniki to `Junction` oraz `True`.

### WordPress zgłasza błąd krytyczny po zmianie

1. Otwórz **Site shell** w Local.
2. Dezaktywuj wtyczkę bez jej usuwania:

   ```bash
   wp plugin deactivate am-toolkit
   ```

3. Sprawdź `wp-content/debug.log`.
4. Popraw błąd w repozytorium.
5. Uruchom `composer check`.
6. Aktywuj wtyczkę ponownie:

   ```bash
   wp plugin activate am-toolkit
   ```

### Zmiany CSS lub JavaScript nie są widoczne

1. Wykonaj `Ctrl + F5`.
2. W DevTools zaznacz **Disable cache**.
3. Sprawdź w zakładce Network, czy przeglądarka pobiera plik z katalogu
   `am-toolkit`.
4. Potwierdź, że edytujesz katalog wskazany jako `Target` Junctiona.

### Composer nie działa w terminalu VS Code

Zamknij wszystkie okna VS Code i uruchom program ponownie. Jeżeli problem
pozostaje, sprawdź:

```powershell
where.exe php
where.exe composer
```

## 7. Gotowość do pracy — skrócona lista

- [ ] Local działa i strona ma zielony status.
- [ ] HTTPS działa bez ostrzeżenia.
- [ ] Otwarty jest katalog repozytorium, nie kopia w `plugins`.
- [ ] `composer check` przechodzi.
- [ ] WooCommerce i AM Toolkit są aktywne.
- [ ] Konsola pokazuje bieżącą wersję AM Toolkit.
- [ ] `debug.log` nie zawiera nowego błędu krytycznego.
- [ ] Praca odbywa się na osobnej gałęzi Git.

Po spełnieniu tej listy środowisko jest gotowe do rozwijania AM Toolkit.
