# Cykl życia dostępu AM Courses

Status: implementacja VIA-39.

## Źródło prawdy

Kurs jest zasobem `course` w `AM Access Core`. Zakup, subskrypcja, ręczne
przypisanie i demo tworzą osobne, idempotentne granty. Odebranie jednego źródła
nie wpływa na pozostałe aktywne granty użytkownika.

Mapowanie produktu na kurs znajduje się w tabeli
`amt_course_product_mappings`. Jest konfiguracją oferty, a nie dowodem dostępu.
Publiczne API mapowania:

```php
use AMToolkit\Modules\Courses\CourseMappings;

CourseMappings::mapProduct($product_id, $course_id);
CourseMappings::unmapProduct($product_id, $course_id);
```

Panel edycji mapowań powstanie osobno. API umożliwia korzystanie z kontraktu bez
bezpośrednich zapisów do tabeli.

## Zakup jednorazowy

Integracja reaguje na `woocommerce_payment_complete` i przejście zamówienia do
statusu zwracanego przez `wc_get_is_paid_statuses()`. Standardowo są to
`processing` i `completed`. Każdy przebieg używa źródła
`woocommerce_order:{order_id}`, dlatego ponowione callbacki nie tworzą duplikatu.

Jeżeli WooCommerce Subscriptions jest aktywne, zamówienia nadrzędne, odnowienia,
resubskrypcje i zmiany planu są pomijane przez ścieżkę zakupu stałego. Dostępem
zarządza wtedy źródło subskrypcji, a nie wieczysty grant zamówienia.

## Subskrypcje

Lokalne środowisko zweryfikowane dla VIA-39 zawiera WooCommerce 11.0.0, ale nie
zawiera providera subskrypcji. Adapter WooCommerce Subscriptions jest więc
warunkowy i rejestruje się wyłącznie, gdy istnieje `WC_Subscription`.

Adapter korzysta z oficjalnego hooka
[`woocommerce_subscription_status_updated`](https://woocommerce.com/document/subscriptions/develop/action-reference/)
i stosuje następującą politykę:

| Status | Działanie |
| --- | --- |
| `active` | nadaj lub przywróć grant |
| `pending-cancel` | zachowaj grant do końca opłaconego okresu |
| `pending`, `on-hold` | odbierz źródło subskrypcyjne |
| `cancelled`, `expired` | odbierz źródło subskrypcyjne |
| niestandardowy | bez automatycznej zmiany |

Zakończenie subskrypcji wyszukuje granty po parze `source_type/source_id`.
Nie polega na aktualnym mapowaniu produktu, które mogło zmienić się po zakupie.

## Ręczny grant i demo

Ręczne operacje przechodzą przez ten sam gateway `AM Access Core` co zakup.
`CourseAccessLifecycle::grantManual()` oraz `revokeManual()` używają trwałego ID
przypisania administratora jako `source_id`.

Demo ma osobne źródło `demo` i jawny zakres `lesson_ids` w metadanych grantu.
Stan ukończenia zakresu demo oraz CTA zakupu należą do warstwy postępu i widoku;
nie zmieniają pełnego grantu kursowego ani nie kasują historii.

## Migracja zakupów historycznych

`HistoricalPurchaseMigrator` pobiera opłacone zamówienia stronami, po ukończeniu
strony zapisuje numer następnej w opcji
`am_toolkit_courses_purchase_backfill`, a powtórzenie jest bezpieczne dzięki
idempotentnym grantom. Błąd w środku strony nie przesuwa checkpointu.

Jedną partię uruchamia jawna akcja:

```php
do_action('am_toolkit_courses_migrate_historical_purchases', 50);
```

Wynik trafia do `am_toolkit_courses_historical_migration_result`. Zamówienia
gościnne są pomijane, ponieważ dostęp do kursu wymaga konta użytkownika.

## Audyt i wycofanie

Wszystkie granty utworzone w jednym zdarzeniu otrzymują wspólny `request_id`.
Nadanie, przywrócenie i odebranie zapisują istniejące zdarzenia `AM Access Core`.

Automatyzacja jest domyślnie wyłączona flagą
`courses-access-automation`. Można ją włączyć przez opcję
`am_toolkit_feature_flags` lub filtr `am_toolkit_feature_enabled`. Stała
`AM_TOOLKIT_DISABLE_COURSES_ACCESS_AUTOMATION` zatrzymuje nowe automatyczne
granty i migrację bez usuwania istniejących danych.

Statusy opłacone zwracane przez WooCommerce nadają lub przywracają granty.
Pełny zwrot (`refunded`), anulowanie (`cancelled`) i nieudane zamówienie
(`failed`) cofają wyłącznie granty, których źródłem jest dane zamówienie.
Pozostałe niezależne źródła — na przykład ręczne przypisanie — pozostają
aktywne. Statusy przejściowe `pending` i `on-hold` nie zmieniają istniejącego
stanu. Częściowy zwrot nie ustawia całego zamówienia jako `refunded`, dlatego
nie odbiera automatycznie całego kursu i wymaga decyzji właścicielki.
