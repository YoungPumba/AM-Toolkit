# Konfiguracja środowiska deweloperskiego AM Toolkit na Windows

Ten dokument opisuje przygotowanie nowego komputera do rozwijania i testowania
AM Toolkit. Jest źródłem prawdy dla zespołu. Zgłoszenie w Linear powinno
odsyłać do tego pliku zamiast powielać instrukcję.

Codzienną kolejność uruchamiania programów i kontroli środowiska opisuje
`DAILY-DEVELOPMENT-WORKFLOW-WINDOWS.md`.

## Cel środowiska

Po zakończeniu konfiguracji deweloper powinien mieć:

- PHP uruchamiane z terminala,
- Composer z poprawną weryfikacją TLS,
- Git i Visual Studio Code,
- lokalny WordPress w aplikacji Local,
- WooCommerce,
- kod AM Toolkit połączony z WordPressem przez `Junction`,
- logowanie błędów WordPressa do pliku,
- działające kontrole składni i generator paczek ZIP.

## 1. Wymagane narzędzia

Zainstaluj:

1. Git for Windows.
2. Visual Studio Code.
3. Local.
4. PHP 8.3 x64 NTS dla Windows.
5. Composer 2.

PHP 8.3 jest wersją zalecaną do lokalnej pracy. Sama wtyczka wymaga obecnie
PHP 8.0 lub nowszego.

Przydatne rozszerzenia Visual Studio Code:

- PHP Intelephense,
- PHP Debug,
- phpcs,
- EditorConfig,
- GitLens — opcjonalnie,
- Error Lens — opcjonalnie.

## 2. Instalacja PHP

1. Rozpakuj PHP do stabilnego katalogu, na przykład:

   ```text
   C:\Users\<użytkownik>\Tools\php83
   ```

2. Skopiuj `php.ini-development` jako `php.ini`.
3. W `php.ini` włącz rozszerzenia:

   ```ini
   extension=curl
   extension=mbstring
   extension=mysqli
   extension=openssl
   extension=pdo_mysql
   extension=zip
   ```

4. Dodaj katalog PHP do zmiennej środowiskowej użytkownika `Path`.
5. Zamknij wszystkie otwarte terminale i uruchom nowy PowerShell.

Weryfikacja:

```powershell
php --version
php --ini
php -m | findstr /I "curl mbstring mysqli openssl pdo_mysql zip"
```

Każde z sześciu rozszerzeń powinno pojawić się na liście.

## 3. Certyfikaty CA i Composer

Pobierz aktualny pakiet certyfikatów CA z oficjalnej strony curl i zapisz go
jako:

```text
C:\Users\<użytkownik>\Tools\php83\cacert.pem
```

W `php.ini` ustaw:

```ini
curl.cainfo="C:\Users\<użytkownik>\Tools\php83\cacert.pem"
openssl.cafile="C:\Users\<użytkownik>\Tools\php83\cacert.pem"
```

Po ponownym uruchomieniu terminala sprawdź:

```powershell
php -i | findstr /I "curl.cainfo openssl.cafile"
php -r "echo file_get_contents('https://getcomposer.org/versions') !== false ? 'SSL OK' : 'SSL ERROR';"
```

### Norton lub inne oprogramowanie przechwytujące HTTPS

Jeżeli test nadal kończy się błędem `certificate verify failed`, program
antywirusowy może podpisywać ruch HTTPS własnym certyfikatem głównym.

Bezpieczna naprawa:

1. Otwórz `certmgr.msc`.
2. Przejdź do **Zaufane główne urzędy certyfikacji → Certyfikaty**.
3. Znajdź certyfikat programu zabezpieczającego używany do kontroli HTTPS.
4. Wyeksportuj go jako **Base-64 encoded X.509 (.CER)**.
5. Zachowaj kopię `cacert.pem`.
6. Dołącz wyeksportowany blok certyfikatu do końca `cacert.pem`.
7. Ponów test `SSL OK`.

Nie wyłączaj w Composerze TLS i nie ustawiaj `secure-http=false`. To ucisza
kontrolę bezpieczeństwa zamiast naprawiać zaufanie do certyfikatu.

Po uzyskaniu `SSL OK` uruchom instalator Composer i wskaż używany plik
`php.exe`. Następnie otwórz nowy terminal i wykonaj:

```powershell
composer --version
composer diagnose
```

Diagnostyka połączenia HTTPS i Packagist powinna zakończyć się wynikiem `OK`.

## 4. Pobranie projektu

Zalecany katalog dla repozytoriów:

```text
C:\Projects\am-toolkit
```

Pobierz repozytorium:

```powershell
git clone https://github.com/YoungPumba/AM-Toolkit.git C:\Projects\am-toolkit
cd C:\Projects\am-toolkit
```

Następnie z jego katalogu wykonaj:

```powershell
composer install
composer check
```

`composer check` sprawdza składnię wszystkich plików PHP poza `vendor` i
uruchamia kontraktowy test `AM Access Core`.

Kontrola standardów kodowania jest dostępna osobno:

```powershell
composer audit:style
```

Ta kontrola może zgłaszać istniejący dług stylistyczny starszych modułów. Nie
należy automatycznie uruchamiać `composer fix:style` na całym projekcie bez
przeglądu zmian.

## 5. Konfiguracja Visual Studio Code

Otwórz katalog główny repozytorium przez **File → Open Folder**. Projekt
zawiera `.vscode/settings.example.json` z bezpiecznym wzorem ustawień.

Skopiuj go do prywatnego pliku, który jest ignorowany przez Git:

```powershell
Copy-Item .vscode\settings.example.json .vscode\settings.json
```

W `.vscode/settings.json` ustaw ścieżkę do PHP zainstalowanego na swoim
komputerze:

```json
"php.validate.executablePath": "C:\\ścieżka\\do\\php.exe"
```

Po otwarciu terminala w VS Code sprawdź:

```powershell
php --version
composer --version
composer check
```

Jeśli Composer działa w zwykłym PowerShellu, ale nie w VS Code, zamknij
wszystkie okna VS Code i uruchom aplikację ponownie. Stary proces nie zna
jeszcze zmienionej zmiennej `Path`.

## 6. Utworzenie strony w Local

Utwórz stronę, używając konfiguracji możliwie zbliżonej do produkcji:

```text
PHP:       8.3
Serwer:    nginx
Baza:      MariaDB 10.6
Multisite: No
```

Po utworzeniu strony:

1. Uruchom ją w Local.
2. Użyj opcji **Trust**, aby zaufać lokalnemu certyfikatowi.
3. Otwieraj stronę pod adresem `https://<nazwa>.local`.
4. Zainstaluj i aktywuj WooCommerce.
5. Nie kopiuj danych klientów ani sekretów z produkcji.

Xdebug może pozostać wyłączony podczas zwykłej pracy. Włączaj go tylko na czas
debugowania krokowego.

## 7. Połączenie repozytorium z WordPressem

Nie kopiujemy kodu po każdej zmianie. Windows `Junction` udostępnia katalog
repozytorium bezpośrednio jako wtyczkę Local.

Przykład:

```powershell
$source = "C:\Projects\am-toolkit"
$link = "C:\Users\<użytkownik>\Local Sites\am-toolkit-dev\app\public\wp-content\plugins\am-toolkit"

if (-not (Test-Path -LiteralPath $source -PathType Container)) {
    throw "Nie istnieje katalog źródłowy: $source"
}

if (Test-Path -LiteralPath $link) {
    throw "Ścieżka docelowa jest już zajęta: $link"
}

New-Item -ItemType Junction -Path $link -Target $source
```

Weryfikacja:

```powershell
Get-Item $link | Select-Object FullName, LinkType, Target
```

Oczekiwany `LinkType` to `Junction`. Następnie aktywuj AM Toolkit w panelu
WordPressa.

Usunięcie zwykłego katalogu źródłowego zniszczyłoby kod projektu. Operacji na
połączeniach nie wykonuj metodą prób i błędów; najpierw sprawdź `LinkType` i
`Target`.

## 8. Debugowanie WordPressa

W lokalnym `wp-config.php` ustaw:

```php
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}

if ( ! defined( 'WP_DEBUG_LOG' ) ) {
	define( 'WP_DEBUG_LOG', true );
}

if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', false );
}

if ( ! defined( 'SCRIPT_DEBUG' ) ) {
	define( 'SCRIPT_DEBUG', true );
}
```

WordPress zapisuje błędy do:

```text
wp-content/debug.log
```

Nie włączaj `SAVEQUERIES` na stałe. Ta opcja zwiększa zużycie pamięci i jest
potrzebna tylko podczas konkretnego pomiaru zapytań.

## 9. Codzienny przebieg pracy

1. Pobierz aktualne zmiany z Git.
2. Uruchom stronę w Local.
3. Utwórz osobną gałąź dla zadania.
4. Wprowadź małą, logicznie zamkniętą zmianę.
5. Uruchom:

   ```powershell
   composer check
   composer audit
   ```

6. Sprawdź `wp-content/debug.log`.
7. Przetestuj desktop i telefon.
8. Sprawdź kluczowe scenariusze również jako gość i zwykły klient.
9. Przejrzyj różnice w Git przed commitem.

Nigdy nie commituj:

- haseł i kluczy API,
- `wp-config.php`,
- eksportu produkcyjnej bazy danych,
- katalogu `vendor`,
- paczek roboczych i logów.

## 10. Budowanie wydania

Z katalogu głównego projektu:

```powershell
powershell -ExecutionPolicy Bypass `
    -File .build/build-release.ps1 `
    -OutputDirectory .build-output
```

Generator pobiera wersję z nagłówka `am-toolkit.php`, waliduje źródła i tworzy:

```text
am-toolkit-vX.Y.Z.zip
└── am-toolkit/
    └── am-toolkit.php
```

Nazwa ZIP może zawierać wersję. Wewnętrzny katalog zawsze musi nazywać się
`am-toolkit`, inaczej WordPress zainstaluje równoległą kopię zamiast
zaktualizować wtyczkę.

Pełne reguły paczkowania opisuje `.build/PACKAGING.md`.

## 11. Lista kontrolna nowego dewelopera

- [ ] `php --version` wskazuje PHP 8.3.
- [ ] Wszystkie wymagane rozszerzenia PHP są aktywne.
- [ ] Test PHP HTTPS zwraca `SSL OK`.
- [ ] `composer diagnose` nie zgłasza problemu TLS.
- [ ] `composer install` kończy się poprawnie.
- [ ] `composer check` nie znajduje błędów składni.
- [ ] Local używa HTTPS i typu środowiska `local`.
- [ ] WooCommerce jest aktywny.
- [ ] AM Toolkit jest podłączony jako `Junction`.
- [ ] Konsola frontendu pokazuje bieżącą wersję AM Toolkit.
- [ ] `WP_DEBUG_LOG` jest włączony, a `WP_DEBUG_DISPLAY` wyłączony.
- [ ] Deweloper zna procedurę budowania i walidacji ZIP-a.

## 12. Najczęstsze problemy

### `composer` nie jest rozpoznawany

Uruchom nowy terminal lub zrestartuj VS Code po zmianie `Path`.

### `certificate verify failed`

Sprawdź `curl.cainfo`, `openssl.cafile` oraz certyfikat programu kontrolującego
HTTPS. Nie wyłączaj TLS.

### AM Toolkit nie pojawia się w WordPressie

Sprawdź, czy istnieje:

```text
wp-content/plugins/am-toolkit/am-toolkit.php
```

oraz czy `Get-Item` pokazuje poprawny cel połączenia.

### WordPress pokazuje dwie kopie AM Toolkit

Wtyczka została rozpakowana do katalogu zawierającego numer wersji. Usuń
wyłącznie nieaktywną, błędną kopię po potwierdzeniu jej ścieżki. Poprawna
instalacja zawsze używa `wp-content/plugins/am-toolkit`.

### Zmiany CSS lub JavaScript nie są widoczne

Sprawdź pamięć podręczną przeglądarki i wtyczki cache. W produkcji po zmianach
zasobów wykonaj pełne czyszczenie LiteSpeed Cache.
