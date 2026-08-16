# Panel administracyjny AM Courses

Status: implementacja VIA-30, moduł nadal domyślnie wyłączony feature flagą.

## Zakres panelu

Po włączeniu modułu `courses` WordPress udostępnia właścicielce pozycję
**Kursy**. Panel pozwala:

- tworzyć, edytować, publikować i archiwizować kursy,
- budować uporządkowany program z opcjonalnymi sekcjami i lekcjami,
- zapisywać ustawienia filmu, czas trwania i wymagania ukończenia lekcji,
- przypisywać wiele materiałów do lekcji,
- mapować wiele produktów WooCommerce na jeden kurs,
- ręcznie nadawać i odbierać dostęp przez `AM Access Core`,
- przeglądać uczestników oraz historię zdarzeń dostępu.
- tworzyć, porządkować, publikować i archiwizować redakcyjne pytania i
  odpowiedzi dla całego kursu albo wskazanej lekcji.

Spotkania, prywatny odnośnik do Telegrama, odtwarzacz, postęp uczestnika oraz
widoki konta klienta nie należą do VIA-30, ale pozostają wymaganiami AM Courses
MVP 0.12.0 realizowanymi odpowiednio w VIA-41–VIA-44. Pełne integracje API z
Zoomem i Telegramem oraz komponenty Elementora nie są wymagane w 0.12.0.

## Włączenie modułu

Moduł pozostaje domyślnie wyłączony. Na kontrolowanym środowisku można go
włączyć w opcji `am_toolkit_feature_flags`:

```php
update_option(
    'am_toolkit_feature_flags',
    ['courses' => true]
);
```

Tryb `AM_TOOLKIT_SAFE_MODE` i stała `AM_TOOLKIT_DISABLE_COURSES` mają
pierwszeństwo przed opcją. Automatyzacja dostępu po zakupie jest osobną flagą
`courses-access-automation` i nie jest wymagana do ręcznej pracy w panelu.

## Publikacja i historia

Zwykły zapis zmienia dane wersji roboczej. Wybranie operacji **Opublikuj**:

1. zamyka aktualną wersję roboczą jako niezmienny snapshot,
2. ustawia ją jako bieżący opublikowany program kursu,
3. tworzy z niej kolejną wersję roboczą do przyszłych zmian.

Ponowny zwykły zapis opublikowanego kursu nie publikuje wersji jeszcze raz.
Archiwizacja zmienia stan rekordu i datę archiwizacji; nie usuwa kursu, lekcji,
materiałów, grantów ani historii zdarzeń.

## Granice odpowiedzialności

Widok nie wykonuje bezpośrednich zapytań SQL. `CourseAdminService` waliduje
przypadki użycia, `CourseAdminStore` odpowiada za katalog i wersje programu,
a `ProductCourseMappingStore` za konfigurację oferty. Mapowanie produktu nie
jest grantem. Wszystkie ręczne granty i ich zdarzenia zapisuje `AM Access Core`.

Operacje zmieniające stan wymagają capability, żądania `POST` i nonce. Panel
kursów wymaga `manage_am_toolkit_courses`; nadawanie lub odbieranie dostępu
dodatkowo wymaga `manage_am_toolkit_access`.

## Redakcyjne Q&A

Sekcja Q&A jest przeznaczona wyłącznie dla właścicielki. Pytanie i odpowiedź są
wymagane, wpis może wskazywać lekcję, a pole pozycji wyznacza stabilną kolejność.
Szkic i archiwum pozostają widoczne w panelu, lecz nigdy nie trafiają do widoku
uczestniczki. Archiwizacja zachowuje dane i zapisuje zdarzenie audytowe bez
kopiowania treści redakcyjnej do dziennika. Awaryjne wyłączenie flagi
`courses-qa` ukrywa obsługę Q&A bez naruszania zapisanych rekordów.

## Testy

Pełna kontrola kodu:

```powershell
composer check
```

Lokalny test integracyjny na jednorazowych danych QA:

```powershell
php .build/test-course-admin-local.php `
  "C:\sciezka\do\WordPressa\wp-load.php" `
  "127.0.0.1:PORT_BAZY"
```

Skrypt sprawdza CRUD, zmianę kolejności sekcji, publikację i klon wersji
roboczej, mapowania, ręczny dostęp, historię oraz archiwizację. W bloku
`finally` usuwa wyłącznie rekordy utworzone przez bieżący przebieg.

Test Q&A na syntetycznych danych:

```powershell
php .build/test-course-qa-local.php `
  "C:\sciezka\do\WordPressa\wp-load.php" `
  "127.0.0.1:PORT_BAZY"
```

Sprawdza zapis stanów redakcyjnych, archiwizację, filtrowanie widoku
uczestniczki i kontekst opublikowanej lekcji, po czym wykonuje `ROLLBACK`.
