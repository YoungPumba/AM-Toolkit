# Panel administracyjny AM Courses

Status: panel bazowy VIA-30 rozszerzony przez VIA-43, VIA-46, VIA-70, VIA-74 i VIA-75;
moduł nadal domyślnie wyłączony feature flagą.

## Zakres panelu

Po włączeniu modułu `courses` WordPress udostępnia właścicielce pozycję
**Kursy**. Panel pozwala:

- tworzyć, edytować, publikować i archiwizować kursy,
- budować uporządkowany program z opcjonalnymi sekcjami i lekcjami,
- zapisywać ustawienia filmu, czas trwania i wymagania ukończenia lekcji,
- przypisywać wiele materiałów do lekcji,
- mapować wiele produktów WooCommerce na jeden kurs,
- ręcznie nadawać i odbierać dostęp przez `AM Access Core`,
- przeglądać uczestników oraz historię zdarzeń dostępu,
- tworzyć, porządkować, publikować i archiwizować redakcyjne pytania i
  odpowiedzi dla całego kursu albo wskazanej lekcji,
- budować checklisty prostych zadań do samodzielnego oznaczenia,
- dodawać spotkania, odnośniki Zoom i prywatną grupę Telegram,
- sprawdzać gotowość konfiguracji i korzystać z siedmiostopniowej instrukcji,
- otwierać chroniony podgląd bieżącego szkicu w UI uczestniczki,
- odróżniać archiwizację od trwałego usunięcia niewykorzystanego szkicu.
- diagnozować dostęp, postęp i spójność danych bez ręcznego odczytu bazy,
- pobierać pseudonimizowany eksport i — wyłącznie jako administrator —
  potwierdzać idempotentne przeliczenie postępu ze źródeł.

Pełne integracje API z Zoomem i Telegramem oraz komponenty Elementora nie są
wymagane w 0.12.0. Panel zapisuje terminy i chronione odnośniki, ale nie tworzy
spotkań u zewnętrznych dostawców.

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

## Zalecana kolejność konfiguracji

Panel prowadzi właścicielkę przez następujące kroki:

1. nazwa, opis i grafika kursu,
2. sekcje programu,
3. lekcje, nagrania i wymagania ukończenia,
4. checklisty zadań, materiały i Q&A,
5. spotkania, Zoom i Telegram,
6. produkty WooCommerce nadające dostęp,
7. podgląd, publikacja i test na osobnym koncie uczestniczki.

Kontrola gotowości jest wskazówką, nie automatycznym substytutem testu. Brak
produktu może być celowy dla kursu nadawanego ręcznie, dlatego panel ostrzega,
ale nie blokuje publikacji samym brakiem mapowania.

## Podgląd szkicu

Akcja **Podgląd jako uczestniczka** otwiera ten sam komponent huba, programu i
lekcji, którego używa klientka. Różnica jest wyłącznie w chronionym źródle
danych: tryb podglądu czyta bieżącą wersję roboczą.

Podgląd:

- wymaga zalogowania, capability `manage_am_toolkit_courses` i nonce powiązanego
  z identyfikatorem kursu,
- nie przyznaje dostępu i nie dodaje kursu do huba klientek,
- nie udostępnia konfiguracji endpointu postępu, więc odtwarzanie i checkboxy
  nie zapisują ukończeń,
- chroni MP4 oraz materiały osobnym nonce zasobu i ponowną kontrolą capability,
- wyświetla stały baner z powrotem do edycji.

Sama znajomość adresu podglądu nie wystarcza do otwarcia szkicu.

## Archiwizacja i trwałe usuwanie

**Archiwizuj** jest operacją domyślną dla treści opublikowanej, zmienionej lub
posiadającej historię. Ukrywa ją z bieżącego interfejsu i zachowuje dane.

**Usuń trwale** jest wąskim narzędziem do poprawiania świeżej pomyłki. Panel
pokazuje je przy szkicu, ale ostateczną decyzję podejmuje serwer w chwili
żądania. Trwałe usunięcie jest odrzucane, gdy element:

- był zmieniany lub publikowany,
- należy do opublikowanego programu,
- ma materiały, zadania albo inne zależności,
- ma grant, postęp lub ukończenie uczestniczki.

Warunek `created_at = updated_at` jest celowo konserwatywny. Jeżeli szkic był
już poprawiany, należy go zarchiwizować. Dla spotkań, które od początku tworzą
historię rewizji, dostępna jest wyłącznie archiwizacja. Prywatny plik świeżego
szkicu materiału albo nagrania jest usuwany z magazynu dopiero po udanym
usunięciu rekordu.

## Granice odpowiedzialności

Widok nie wykonuje bezpośrednich zapytań SQL. `CourseAdminService` waliduje
przypadki użycia, `CourseAdminStore` odpowiada za katalog i wersje programu,
a `ProductCourseMappingStore` za konfigurację oferty. Mapowanie produktu nie
jest grantem. Wszystkie ręczne granty i ich zdarzenia zapisuje `AM Access Core`.

Operacje zmieniające stan wymagają capability, żądania `POST` i nonce. Panel
kursów wymaga `manage_am_toolkit_courses`; nadawanie lub odbieranie dostępu
dodatkowo wymaga `manage_am_toolkit_access`.

Diagnostyka ma osobne uprawnienia. `view_am_toolkit_diagnostics` pozwala
wykonać kontrolę tylko do odczytu i pobrać bezpieczny JSON.
`repair_am_toolkit_courses` pozwala uruchomić potwierdzone przeliczenie; nie
jest nadawane kierownikowi sklepu. Flagą `courses-repair-tools` można wyłączyć
sam zapis bez wyłączania odczytu. Szczegółowy kontrakt opisuje
[`COURSES-DIAGNOSTICS.md`](COURSES-DIAGNOSTICS.md).

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
