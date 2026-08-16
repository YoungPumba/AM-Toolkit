# AM Toolkit

Gałąź `main` przygotowuje wersję rozwojową 0.12.0 z modułem AM Courses.
Zweryfikowane wydania instalacyjne są publikowane jako tag i GitHub Release
zgodnie z [procedurą wydań](docs/RELEASES.md).

Wtyczka jest uruchamiana przez Composer PSR-4 i centralny rejestr modułów.
Jawne zależności, migracje per moduł, capabilities i przełączniki awaryjne
tworzą bezpieczny fundament pod AM Courses.

AM Toolkit to rozwijana modułowo wtyczka dla WordPressa i WooCommerce. Zastępuje standardowe elementy interfejsu sklepu własnymi, spójnymi komponentami.

## Dostępne moduły

- nowoczesne powiadomienia Toast dla WooCommerce,
- panel konfiguracji powiadomień w WordPressie,
- shortcode `[custom_cart]` z wartością i licznikiem koszyka,
- aktualizacja koszyka przez AJAX,
- integracja z optymalizacją LiteSpeed Cache,
- spójny wygląd podsumowania błędów walidacji podczas składania zamówienia,
- panel konfiguracji komunikatu checkoutu z podglądem zmian.
- responsywne kafelki szybkiego dostępu w panelu konta,
- ustawianie hasła z odnośnika rejestracyjnego i konfiguracja podstawowych danych konta.
- dedykowany widok „Moje zamówienia” niezależny od szablonów ShopEngine,
- dedykowane widoki szczegółów zamówienia, danych konta i adresów,
- shortcode `[am_account_menu]` z nawigacją zalogowanego klienta.
- `AM Access Core` z idempotentnymi grantami, okresem ważności i obsługą wielu źródeł dostępu,
- dziennik zdarzeń przygotowany dla postępu kursów, powiadomień i przyszłych automatyzacji.
- wyłączony domyślnie moduł `Courses` z wersjonowanym modelem programu,
  trwałymi UUID, migracjami katalogu i transakcyjnego stanu postępu.
- mapowanie produktów na kursy, idempotentny dostęp po płatności, opcjonalny
  adapter subskrypcji i wznawialna migracja zakupów historycznych.
- responsywny panel właścicielki do redakcji wersjonowanego programu kursu,
  materiałów, mapowań produktów, uczestników i ręcznego dostępu.
- chroniony hub klientki pod `/moje-konto/kursy/` z aktywnymi, ukończonymi
  i wygasłymi kursami, widokiem opublikowanego programu oraz podglądem
  „Twoje kursy” na głównym ekranie konta.
- redakcyjne Q&A kursów zarządzane przez właścicielkę, z opcjonalnym kontekstem
  lekcji i chronionym widokiem tylko do odczytu dla uczestniczek.

## Wymagania

- WordPress,
- PHP 8.0 lub nowszy,
- WooCommerce dla funkcji sklepowych.

## Instalacja

1. Pobierz najnowszą paczkę ZIP z sekcji **Releases**.
2. W WordPressie przejdź do **Wtyczki → Dodaj wtyczkę → Wyślij wtyczkę na serwer**.
3. Wgraj ZIP i aktywuj AM Toolkit.
4. Po aktualizacji wyczyść pamięć podręczną strony, zwłaszcza jeśli używasz LiteSpeed Cache.

## Konfiguracja

Ustawienia komunikatów są dostępne w kokpicie WordPressa w sekcji **AM Toolkit → Powiadomienia**.

## API dostępu

Moduły kursów i pozostałe chronione widoki powinny korzystać ze wspólnego API,
zamiast samodzielnie sprawdzać identyfikatory produktów WooCommerce:

```php
use AMToolkit\Modules\Access\Access;

$grant_id = Access::grant(
    $user_id,
    'course',
    $course_id,
    [
        'source_type' => 'order_item',
        'source_id'   => $order_item_id,
        'metadata'    => ['order_id' => $order_id],
    ]
);

if (Access::userHas($user_id, 'course', $course_id)) {
    // Renderuj chronioną zawartość kursu.
}

Access::revokeSource(
    $user_id,
    'course',
    $course_id,
    'order_item',
    $order_item_id
);
```

Ponowne nadanie dostępu z identycznego źródła zwraca ten sam grant, a wcześniej
cofnięty grant zostaje ponownie aktywowany. Dwa różne
źródła są zapisywane osobno, dlatego odebranie jednego z nich nie usuwa dostępu,
jeśli nadal istnieje inny aktywny grant.

## Dokumentacja techniczna

- [Architektura AM Toolkit](docs/ARCHITECTURE.md)
- [Model domeny AM Courses](docs/COURSES-DOMAIN.md)
- [Cykl życia dostępu AM Courses](docs/COURSES-ACCESS.md)
- [Panel administracyjny AM Courses](docs/COURSES-ADMIN.md)
- [Diagnostyka AM Courses](docs/COURSES-DIAGNOSTICS.md)
- [Hub kursów AM Courses](docs/COURSES-HUB.md)
- [Lekcje, odtwarzacz i prywatne materiały AM Courses](docs/COURSES-LESSONS.md)
- [Postęp i ukończenie AM Courses](docs/COURSES-PROGRESS.md)
- [Konfiguracja środowiska Windows](docs/DEVELOPMENT-SETUP-WINDOWS.md)
- [Codzienny workflow lokalny](docs/DAILY-DEVELOPMENT-WORKFLOW-WINDOWS.md)

## Historia zmian

Pełna lista zmian znajduje się w pliku [CHANGELOG.md](CHANGELOG.md).

Plan kolejnych etapów znajduje się w pliku [ROADMAP.md](ROADMAP.md).

## Rozwój lokalny

Kompletna instrukcja przygotowania środowiska na Windows znajduje się w
[dokumentacji deweloperskiej repozytorium](https://github.com/YoungPumba/AM-Toolkit/blob/main/docs/DEVELOPMENT-SETUP-WINDOWS.md).

Kolejność codziennego uruchamiania Local, VS Code, testów i logów opisuje
[runbook pracy lokalnej](https://github.com/YoungPumba/AM-Toolkit/blob/main/docs/DAILY-DEVELOPMENT-WORKFLOW-WINDOWS.md).

Przed rozpoczęciem pracy zainstaluj zależności i uruchom pełną kontrolę
projektu:

```powershell
composer install
composer check
```

Kontrola obejmuje składnię PHP oraz kontraktowy test `AM Access Core`.

## Licencja

GPL-2.0-or-later.
