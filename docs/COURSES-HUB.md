# Hub i program AM Courses

Status: implementacja VIA-41, moduł pozostaje domyślnie wyłączony feature flagą.

VIA-44 rozszerza hub i program o procent ukończenia, stany lekcji oraz wspólny
przycisk „Kontynuuj”. Decyzję o następnej lekcji podejmuje
`CourseNextActionService`; widok nie odtwarza tej reguły samodzielnie.

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
- `[am_courses_dashboard]` renderuje skrócony blok „Twoje kursy” z maksymalnie
  trzema najważniejszymi kursami i odnośnikiem do pełnego huba,
- `[am_account_shortcut type="courses"]` renderuje działający kafelek,
- shortcode `[am_courses_hub]` pozwala osadzić hub w kontrolowanym układzie.

Sekcja dashboardu jest też podpięta do standardowego hooka
`woocommerce_account_dashboard` z priorytetem `5`. Na klaudiasocials.pl,
gdzie główny panel jest osobnym szablonem ShopEngine/Elementor, shortcode
`[am_courses_dashboard]` należy umieścić bezpośrednio przed nagłówkiem
**Szybki dostęp**. Dzięki temu klientka najpierw widzi najważniejszy kurs,
a dopiero potem ogólne skróty konta. Zabezpieczenie w rendererze nie pozwala
wyrenderować sekcji dwukrotnie w jednym żądaniu.

## Kontrakt UX/UI

Widoki uczestniczki AM Courses rozwijają język wizualny istniejącego modułu
„Moje konto”, zamiast wprowadzać osobny motyw. Obowiązują:

- Poppins dla tekstu i elementów sterujących,
- `"buffalo-regular"` / Buffalo dla tytułów ogólnych, takich jak „Moje kursy”
  i „Twoje kursy”; nazwy konkretnych kursów używają Poppins, ponieważ Buffalo
  nie zapewnia poprawnej obsługi polskich znaków,
- akcent `#F176A4` i ciemniejszy wariant `#D85F8D`,
- ciepłe tło `#F8F4F2`, białe powierzchnie i podstawowy promień `25px`,
- subtelne separatory między wprowadzeniem oraz grupami kursów według statusu,
- czytelny focus klawiatury, semantyczne nagłówki i brak martwych odnośników,
- siatka przechodząca z trzech kolumn do jednej bez poziomego przewijania.

Ten kontrakt obejmuje również kolejne widoki: lekcję, odtwarzacz, materiały,
spotkania i postęp. Panel administracyjny pozostaje zgodny z konwencjami
WordPressa — nie udaje strony klientki w zapleczu.

Po wyłączeniu modułu hooki nie są rejestrowane, dlatego Account nie pozostawia
martwych odnośników. Arkusz `assets/css/courses.css` ładuje się tylko dla
dashboardu konta, endpointu Courses lub świadomego użycia shortcode'u.

## Zakres kolejnych zadań

VIA-41 nie dodaje odtwarzacza, linków do niegotowych widoków lekcji,
chronionego pobierania plików ani zapisu postępu. Te elementy powstają w
VIA-42 i VIA-44. VIA-43 dodaje najbliższy termin w kafelku i programie,
czytelny brak terminu, historię statusów oraz CTA do spotkania, nagrania i
prywatnej grupy kursu. Dane te są dołączane do read modelu dopiero po
potwierdzeniu aktywnego grantu.

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
