# Hub i program AM Courses

Status: implementacja VIA-41, moduł pozostaje domyślnie wyłączony feature flagą.

## Zakres

Po włączeniu modułu `courses` WooCommerce rejestruje endpoint
`/moje-konto/kursy/`. Hub pokazuje wyłącznie kursy wynikające z własnych
aktywnych lub historycznych grantów zalogowanej klientki. Obsługiwane są stany:

- **aktywny** — istnieje co najmniej jeden ważny grant i można otworzyć program,
- **ukończony** — istnieje trwały rekord ukończenia; program można otworzyć
  tylko wtedy, gdy nadal istnieje aktywny grant,
- **zaplanowany** — aktywny grant ma przyszłą datę rozpoczęcia,
- **wygasły** — kurs pozostaje w historii, ale jego opis i program są ukryte.

Publiczny adres kursu używa trwałego UUID:

```text
/moje-konto/kursy/{course-public-id}/
```

## Granica bezpieczeństwa

Lista huba korzysta z read modelu grantów AM Access Core, ponieważ musi pokazać
również bezpieczną historię wygasłych dostępów. Przed odczytem opisu i programu
`CourseCatalogService` ponownie sprawdza aktywny dostęp przez
`CourseAccessPolicy`. Dopiero po pozytywnej autoryzacji magazyn pobiera
opublikowaną wersję programu, opublikowane sekcje i opublikowane lekcje.

Nieistniejący UUID, niepoprawny UUID, cudzy kurs i kurs po wygaśnięciu zwracają
ten sam publiczny stan. Widok nie ujawnia technicznego błędu bazy ani
identyfikatorów wewnętrznych. Drafty, odnośniki do filmów i materiały nie są
częścią read modelu VIA-41.

## Integracja z Account

Gdy moduł Courses jest aktywny:

- WooCommerce otrzymuje pozycję menu **Kursy**,
- shortcode `[am_account_menu]` otrzymuje tę samą pozycję przez publiczny filtr,
- `[am_account_shortcut type="courses"]` renderuje działający kafelek,
- shortcode `[am_courses_hub]` pozwala osadzić hub w kontrolowanym układzie.

Po wyłączeniu modułu hooki nie są rejestrowane, dlatego Account nie pozostawia
martwych odnośników. Arkusz `assets/css/courses.css` ładuje się tylko dla
endpointu lub świadomego użycia shortcode'u.

## Zakres kolejnych zadań

VIA-41 nie dodaje odtwarzacza, linków do niegotowych widoków lekcji,
chronionego pobierania plików ani zapisu postępu. Te elementy powstają w
VIA-42 i VIA-44. Najbliższe spotkanie oraz prywatne linki powstaną w VIA-43.

## Testy

Pełna kontrola kodu:

```powershell
composer check
```

Test integracyjny na lokalnym WordPressie:

```powershell
php .build/test-course-hub-local.php `
  "C:\sciezka\do\WordPressa\wp-load.php" `
  "127.0.0.1:PORT_BAZY"
```

Test tworzy syntetyczne kursy i granty w transakcji, sprawdza aktywny i wygasły
dostęp, filtrowanie draftu oraz próbę odczytu przez inną osobę, a następnie
wykonuje `ROLLBACK`. Skrypt `course-hub-browser-fixture.php` służy wyłącznie do
kontrolowanego QA widoków i zawsze wymaga późniejszego trybu `cleanup`.
