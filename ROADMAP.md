# Roadmap AM Toolkit

## Zasady modułu „Moje konto”

- zachowujemy stylistykę Klaudia Socials: Poppins dla interfejsu, Buffalo dla głównych tytułów, akcent `#F176A4`, ciepłe beże i białe powierzchnie,
- podstawowy promień dużych kart i paneli wynosi `25px`,
- każdy etap kończymy testem na komputerze i telefonie,
- każdy etap sprawdzamy dla użytkownika niezalogowanego, zalogowanego bez zakupów oraz zalogowanego z zakupami,
- najpierw kończymy i zatwierdzamy aktualny widok, dopiero później przechodzimy do następnego modułu,
- funkcje planowane, lecz jeszcze niedostępne, oznaczamy etykietą „W budowie”,
- nowe wersje pakujemy wyłącznie według schematu opisanego w `.build/PACKAGING.md`.

## Powitanie panelu konta

- [x] animacja wyświetlana raz dziennie na głównym widoku konta,
- [x] pełnoekranowe przyciemnienie i pas w kolorze marki,
- [x] obsługa ograniczenia animacji ustawionego w systemie użytkownika,
- [ ] ustawienia wyglądu i częstotliwości w panelu AM Toolkit.

## Aktualny etap — Panel konta

- [x] responsywne kafelki szybkiego dostępu,
- [x] poziome przewijanie kafelków na telefonach,
- [x] dynamiczne powitanie i dane profilu,
- [x] podsumowanie „Wszystkie moje produkty”,
- [x] podgląd ostatnio nabytych produktów,
- [x] pełny widok wszystkich nabytych produktów,
- [x] endpoint `/moje-konto/moje-produkty/` z kursami, konsultacjami i plikami klienta,
- [x] ręczne przyznawanie i odbieranie produktów klientom przez administratora,
- [x] dynamiczna sekcja „Wymaga Twojej uwagi”,
- [x] dynamiczna sekcja „Ostatnie zamówienie”,
- [x] odnośniki i liczniki szybkiego dostępu.

## Audyt konta klienta — 27 lipca 2026

### Elementy działające poprawnie

- [x] panel główny poprawnie pobiera dane klienta, produkty, zamówienie i liczniki,
- [x] widok `/moje-konto/moje-produkty/` działa na komputerze i telefonie bez poziomego przewijania całej strony,
- [x] karuzela szybkiego dostępu przewija się poziomo na telefonie bez rozszerzania dokumentu,
- [x] chronione odnośniki do pobrania plików są generowane poprawnie,
- [x] widok „Moje produkty” ma prawidłową hierarchię nagłówków, opisy alternatywne obrazów i etykiety regionów.

### Problemy wymagające naprawy

- [x] przywrócić zawartość podstawowych endpointów WooCommerce: `orders`, `view-order`, `edit-account` i `edit-address`,
- [ ] zweryfikować formularz logowania WooCommerce — standardowy formularz WordPress zalogował konto, natomiast formularz na stronie konta wymaga ponownego testu,
- [x] poprawić klasyfikację produktów przypisanych do podkategorii,
- [x] zaokrąglić avatar użytkownika niezależnie od obrazu zwracanego przez WordPress,
- [ ] dodać dostępny opis do ikony konta w nagłówku, np. `aria-label="Moje konto"`.

## v0.9.2 — dopracowanie widoku „Moje produkty”

- [x] mocniej oddzielić kategorie „Konsultacje”, „Kursy”, „Pliki do pobrania” i „Pozostałe produkty”,
- [x] dodać pod nagłówkiem kategorii krótką różową linię oraz delikatną linię na pozostałej szerokości,
- [x] zmniejszyć wysokość zdjęć z około `228px` do `190px` na komputerze,
- [x] zmniejszyć wysokość zdjęć z około `199px` do `170px` na telefonie,
- [x] zmniejszyć pionowe odstępy wewnątrz kart bez pogarszania czytelności,
- [x] zachować trzy kolumny na dużym ekranie, dwie na tablecie i jedną na telefonie,
- [x] zachować obecny wygląd i działanie przycisków pobierania,
- [x] obsłużyć przypisanie produktu do kategorii nadrzędnej lub jej podkategorii,
- [ ] sprawdzić długie nazwy produktów i plików,
- [ ] sprawdzić kategorie puste, pojedynczą kartę oraz wiele kart,
- [ ] przetestować linki produktu i chronione pobieranie plików,
- [ ] wykonać test wizualny przy szerokościach `360px`, `768px`, `1024px` i co najmniej `1440px`.

## v0.9.3 — dedykowane obrazy i kosmetyka panelu

- [x] dodać w edycji produktu WooCommerce pole „Obraz w panelu «Moje produkty»” z wyborem z Biblioteki mediów,
- [x] pod polem wyświetlić informację o zalecanym formacie: proporcje około `1.9:1`, rekomendowane `1200 × 630 px` lub `1600 × 840 px`, najlepiej WebP,
- [x] zastosować kolejność awaryjną: dedykowany obraz panelu → główna miniatura produktu → obraz zastępczy WooCommerce,
- [x] używać dedykowanego obrazu wyłącznie w panelu konta, bez zmiany strony produktu i katalogu sklepu,
- [x] usunąć różowy fragment separatora kategorii i pozostawić delikatną jasnoszarą linię,
- [x] usunąć koła, obramowania i cienie ze strzałek kafelków szybkiego dostępu,
- [x] pozostawić samą różową strzałkę z czytelnym stanem `hover` i `focus-visible`,
- [ ] przetestować obrazy ustawione, brak obrazu dodatkowego oraz całkowity brak miniatury,
- [ ] sprawdzić szybki dostęp na komputerze i telefonie.

## v0.10.0 — podstawowe endpointy konta

- [x] wyświetlić listę zamówień na `/moje-konto/orders/`,
- [x] dodać filtrowanie, sortowanie, statusy, pobieranie plików i paginację listy zamówień,
- [x] przygotować responsywny układ tabeli na komputerze i kart na telefonie,
- [x] wyświetlić szczegóły zamówienia na `/moje-konto/view-order/{id}/`,
- [x] zabezpieczyć szczegóły zamówienia kontrolą zalogowanego właściciela,
- [x] wyświetlić produkty, kwoty, płatność, adresy, pliki cyfrowe i notatkę klienta,
- [x] wyświetlić formularz danych konta na `/moje-konto/edit-account/`,
- [x] umożliwić zmianę i usuwanie własnego avatara z poziomu danych konta,
- [x] wyświetlić formularz adresów na `/moje-konto/edit-address/`,
- [ ] przygotować spójne puste widoki i komunikaty błędów,
- [ ] sprawdzić wszystkie odnośniki prowadzące z panelu głównego,
- [ ] przetestować endpointy na komputerze i telefonie.

## v0.11.0 — nawigacja konta

- [x] kliknięcie ikony niezalogowanego użytkownika prowadzi do logowania i rejestracji,
- [x] kliknięcie ikony zalogowanego użytkownika otwiera menu konta,
- [x] dodać w menu wyraźnie oddzieloną pozycję „Kontakt i pomoc” prowadzącą do `/moje-konto/#pomoc-konto`,
- [x] utrzymać pozycję pomocy widoczną niezależnie od aktualnie otwartego endpointu konta,
- [x] uwzględnić odsunięcie kotwicy od przyklejonego nagłówka, aby formularz nie został po przewinięciu zasłonięty,
- [x] jednakowe zachowanie na komputerze i telefonie,
- [x] obsługa klawiatury, zamykania poza panelem i klawiszem Escape,
- [x] dostępna nazwa przycisku i poprawne atrybuty `aria-expanded` oraz `aria-controls`.

## Odzyskiwanie i ustawianie hasła

- [x] uzupełnić pusty widok otwierany z odnośnika „Ustaw nowe hasło” w wiadomości rejestracyjnej,
- [x] wyświetlić formularz nowego hasła dla poprawnego klucza WooCommerce,
- [x] obsłużyć nieprawidłowy, wykorzystany lub wygasły odnośnik,
- [x] dodać komunikat po poprawnym zapisaniu hasła i przejście do konfiguracji konta,
- [x] formularz imienia, nazwiska, nazwy wyświetlanej i opcjonalnego telefonu,
- [x] dopasować wygląd formularza oraz komunikatów do panelu konta,
- [ ] przetestować cały proces w trybie niezalogowanym na komputerze i telefonie.

## v0.11.2 — fundament dostępu AM Toolkit

- [x] dodać wspólne `AM Access Core` używane docelowo przez kursy, konsultacje i chronione materiały,
- [x] przechowywać każde źródło dostępu jako osobny, idempotentny grant zamiast uzależniać widoki od konkretnego produktu WooCommerce,
- [x] obsłużyć źródła takie jak zakup, ręczne nadanie, migracja, pakiet i przyszła subskrypcja,
- [x] zapisywać datę nadania, okres ważności, status, źródło i bezpieczne metadane dostępu,
- [x] udostępnić jedno API do sprawdzania, nadawania i odbierania dostępu,
- [x] dodać dziennik istotnych zdarzeń oraz hooki pozwalające później podłączać powiadomienia, analitykę i sekcję „Wymaga Twojej uwagi”,
- [x] wykonać bezpieczną, wersjonowaną migrację tabel przy aktywacji i aktualizacji wtyczki,
- [ ] przetestować idempotentne nadanie, wielokrotne źródła dostępu, wygaśnięcie i odebranie grantu.

## v0.12.0 — Kursy, dostęp i postęp

### Fundament AM Courses

- [ ] korzystać wyłącznie ze wspólnego `AM Access Core` przy ochronie kursów i lekcji,
- [ ] przyjąć rozszerzalny model `Kurs → opcjonalny Moduł → Lekcja`, zachowując możliwość prostego kursu bez ręcznego tworzenia modułów,
- [ ] nadać kursom, modułom i lekcjom trwałe identyfikatory niezależne od ich kolejności,
- [ ] wersjonować program i zapisywać zestaw wymaganych lekcji przy ukończeniu, aby późniejsza rozbudowa kursu nie cofała absolwentom statusu 100%,
- [ ] rejestrować ustandaryzowane zdarzenia kursu, postępu, lekcji, materiałów i spotkań,
- [ ] wyznaczać jedno „następne najlepsze działanie” używane wspólnie w hubie kursów, panelu konta i sekcji „Wymaga Twojej uwagi”,
- [ ] przygotować role i możliwości uczestnika, właścicielki oraz przyszłego mentora lub moderatora bez udostępniania niegotowych paneli,
- [ ] udostępnić hooki i stabilne API dla przyszłych notatek, zadań, dyskusji kontekstowych, ogłoszeń i powiadomień.

### Model danych i panel właścicielki

- [ ] zbudować niezależny moduł AM Courses bez wymaganej integracji z zewnętrznym LMS,
- [ ] rozdzielić encje „Kurs”, „Lekcja”, „Spotkanie” i „Dostęp uczestnika”,
- [ ] umożliwić tworzenie kursu z tytułem, opisem, grafiką, uporządkowaną listą lekcji, materiałami dodatkowymi i prywatnym odnośnikiem do grupy Telegram,
- [ ] umożliwić dodawanie, usuwanie i zmianę kolejności lekcji bez edycji plików wtyczki,
- [ ] dla każdej lekcji zapisywać film, czas trwania, opis etapu oraz informację, czy jest wymagana do ukończenia kursu,
- [ ] umożliwić przypisanie do lekcji wielu plików do pobrania z własną nazwą, opisem i kolejnością,
- [ ] wybrać i udokumentować sposób hostowania filmów oraz ochrony dostępu przed rozpoczęciem implementacji odtwarzacza,
- [ ] zachować prosty model: jeden kurs, wspólna treść i spotkania oraz wielu niezależnie przypisanych uczestników,
- [ ] określić zasadę rozbudowy programu tak, aby dodanie nowej lekcji nie odbierało statusu ukończenia osobom, które wcześniej ukończyły kurs,
- [ ] dodać właścicielce prosty panel zarządzania kursem, lekcjami, uczestnikami, spotkaniami i stanem publikacji,
- [ ] zapisywać wszystkie daty z jednoznaczną strefą czasową i prezentować je klientowi w strefie `Europe/Warsaw`.

### Spotkania przypisane do kursu

- [ ] pozwolić dodać do kursu dowolną liczbę spotkań,
- [ ] dla spotkania zapisywać tytuł, datę, godzinę rozpoczęcia i zakończenia, miejsce lub platformę, link Zoom, opis oraz opcjonalny link do nagrania,
- [ ] umożliwić właścicielce edycję daty, miejsca i linków bez ingerencji w kod,
- [ ] pokazywać uczestnikowi najbliższe spotkanie na stronie kursu oraz na panelu głównym konta,
- [ ] chronić link Zoom i nagranie przed użytkownikami bez aktywnego dostępu do danego kursu,
- [ ] przygotować wiadomości przypominające o spotkaniu i plik kalendarza `.ics`,
- [ ] obsłużyć stan bez zaplanowanego spotkania oraz zmianę lub odwołanie terminu.

### Dostęp po zakupie i dostęp ręczny

- [ ] mapować jeden lub więcej produktów WooCommerce na konkretny kurs,
- [ ] przyznawać dostęp idempotentnie dopiero po opłaceniu zamówienia,
- [ ] wykorzystać istniejące ręczne przypisania produktów, ale zapisywać osobny dostęp do kursu ze źródłem, datą nadania, statusem i opcjonalnym terminem wygaśnięcia,
- [ ] umożliwić właścicielce ręczne przyznanie i odebranie dostępu do wybranego kursu,
- [ ] przygotować bezpieczne przypisanie dostępu osobom, które kupiły kurs przed uruchomieniem modułu,
- [ ] zdefiniować i obsłużyć zasady odebrania dostępu po anulowaniu, zwrocie lub chargebacku,
- [ ] zabezpieczyć wszystkie widoki lekcji tak, aby użytkownik widział wyłącznie własne aktywne dostępy.

### Hub kursów i lekcje

- [ ] dodać endpoint `/moje-konto/kursy/` z listą aktywnych, ukończonych i wygasłych kursów,
- [ ] dodać kafelek „Kursy” do szybkiego dostępu i pozycję do menu konta, gdy endpoint będzie gotowy,
- [ ] dodać chroniony widok pojedynczego kursu z programem, postępem, najbliższym spotkaniem i przyciskiem „Kontynuuj”,
- [ ] pokazywać w widoku kursu przycisk prowadzący do grupy Telegram wyłącznie uczestnikom z aktywnym dostępem,
- [ ] dodać chroniony widok lekcji z odtwarzaczem, opisem etapu, plikami do pobrania, nawigacją „Poprzednia/Następna” i spisem programu,
- [ ] udostępniać pliki lekcji przez kontrolowany mechanizm pobierania sprawdzający aktywny dostęp, zamiast ujawniać publiczny adres pliku,
- [ ] zapamiętywać ostatnio otwartą lekcję oraz opcjonalnie pozycję odtwarzania filmu,
- [ ] przygotować czytelne puste widoki dla braku kursów, braku lekcji, niedostępnej lekcji i wygasłego dostępu,
- [ ] zapewnić responsywny i dostępny odtwarzacz, napisy lub transkrypcję oraz obsługę klawiatury.

### Postęp kursu

- [ ] liczyć postęp jako stosunek ukończonych wymaganych lekcji do wszystkich wymaganych lekcji kursu,
- [ ] nie uznawać samego otwarcia strony lekcji za jej obejrzenie,
- [ ] dla wspieranego odtwarzacza automatycznie oznaczać lekcję po osiągnięciu ustalonego progu, np. `90%`, a w pozostałych przypadkach udostępnić przycisk „Oznacz jako ukończoną”,
- [ ] przechowywać postęp osobno dla każdego uczestnika i każdego kursu,
- [ ] obsłużyć stany: nierozpoczęty, w trakcie, ukończony oraz wygasły,
- [ ] po ukończeniu ostatniej wymaganej lekcji trwale zapisać ukończenie kursu,
- [ ] pokazywać procent, liczbę ukończonych lekcji, ostatnią lekcję i przycisk „Kontynuuj” w hubie oraz panelu głównym,
- [ ] dodać do „Wymaga Twojej uwagi” wyłącznie rzeczywiste zdarzenia, np. zbliżające się spotkanie lub wymagany etap, bez reklamowych przypomnień.

### Testy akceptacyjne

- [ ] przetestować brak dostępu, zakup nowy, zakup historyczny, ręczne przyznanie, odebranie, zwrot i wygaśnięcie,
- [ ] przetestować wielu użytkowników przypisanych do tego samego kursu oraz jednego użytkownika przypisanego do kilku kursów,
- [ ] przetestować zmianę terminu, linku Zoom i linku Telegram oraz brak możliwości ich odczytania przez osobę bez dostępu,
- [ ] przetestować postęp, wznowienie nauki i ukończenie na komputerze oraz telefonie,
- [ ] przetestować dodanie nowej lekcji bez odebrania zapisanego statusu ukończenia dotychczasowym absolwentom.

## v0.13.0 — Konsultacje i terminy

- [ ] kafelek pozostaje oznaczony jako „W budowie” do czasu ukończenia modułu,
- [ ] zbudować niezależny moduł AM Reservations bez wymaganej integracji z Amelia,
- [ ] dodać w edycji produktu WooCommerce jawne oznaczenie „Produkt konsultacyjny”, liczbę przyznawanych spotkań, czas trwania, bufor i termin ważności,
- [ ] utworzyć jednoznaczny rejestr uprawnień: źródło zakupu, liczba przyznanych, zarezerwowanych i pozostałych spotkań, termin ważności oraz identyfikatory rezerwacji,
- [ ] dodać ustawienia tygodniowej dostępności, przerw, urlopów, minimalnego wyprzedzenia i okresu rezerwacji naprzód,
- [ ] przed zakupem pokazywać rzeczywisty, tylko informacyjny podgląd dostępnych dni i godzin,
- [ ] przy kliknięciu „Dodaj do koszyka” otwierać na komputerze modal, a na telefonie dostępny panel dolny z kalendarzem i potwierdzeniem zakupu,
- [ ] wyświetlać informację, że podgląd nie blokuje terminu, a właściwa rezerwacja będzie dostępna po opłaceniu zakupu w „Moje konto → Konsultacje”,
- [ ] wyświetlać najbliższy wolny termin przy przycisku produktu i blokować zakup, jeśli w skonfigurowanym okresie nie ma żadnego terminu,
- [ ] przyznawać uprawnienia dopiero po opłaceniu zamówienia i nie naliczać ich ponownie przy kolejnych zmianach statusu,
- [ ] obsłużyć ręczne przyznanie konsultacji, anulowanie zamówienia, zwrot oraz ponowne udostępnienie terminu po dozwolonym odwołaniu,
- [ ] dodać endpoint `/moje-konto/konsultacje/`,
- [ ] wyświetlić listę kupionych konsultacji i status ich wykorzystania,
- [ ] wyświetlić termin najbliższego spotkania oraz historię konsultacji,
- [ ] udostępnić rezerwację pozostałego spotkania, zmianę terminu i dozwolone anulowanie,
- [ ] zabezpieczyć zapis transakcją i unikalną blokadą, aby ten sam termin nie został przyznany dwóm klientom,
- [ ] wysyłać potwierdzenia i przypomnienia e-mail oraz udostępnić plik kalendarza `.ics`,
- [ ] dodać do sekcji „Wymaga Twojej uwagi” zadanie „Zarezerwuj termin konsultacji” dla każdego niewykorzystanego uprawnienia,
- [ ] usunąć zadanie po poprawnym powiązaniu terminu z uprawnieniem,
- [ ] zapewnić, że jedno uprawnienie nie pozwala utworzyć wielu aktywnych rezerwacji,
- [ ] przetestować zakup pojedynczej konsultacji, pakietu, ręczne przyznanie, zwrot, anulowanie i zmianę terminu.

## v0.14.0 — Dokumenty i obsługa zamówień

- [ ] dokumenty zakupu i faktury,
- [ ] centrum pomocy,
- [ ] zwroty i reklamacje,
- [ ] czytelne statusy zgłoszeń.

## v0.15.0 — Korzyści klienta

- [ ] kupony przypisane do użytkownika,
- [ ] indywidualne oferty,
- [ ] rekomendacje na podstawie posiadanych produktów,
- [ ] wygasanie i warunki wykorzystania korzyści.

## v1.0.0 — stabilny moduł konta

- [ ] kompletne testy wszystkich endpointów konta,
- [ ] pełna obsługa klawiatury i czytników ekranu,
- [ ] spójne stany ładowania, puste widoki, błędy i komunikaty,
- [ ] brak poziomego przewijania całej strony na obsługiwanych urządzeniach,
- [ ] panel ustawień najważniejszych elementów w AM Toolkit,
- [ ] dokumentacja administratora strony.

## v1.1.0 — widżety AM Toolkit dla Elementora

- [ ] dodać w Elementorze osobną kategorię widżetów „AM Toolkit”,
- [ ] umożliwić budowanie panelu konta z pojedynczych widżetów bez edycji plików wtyczki,
- [ ] dodać widżet „Podsumowanie moich produktów” z własnym tytułem, opisem i odnośnikiem,
- [ ] dodać repeater pozycji podsumowania, pozwalający tworzyć elementy takie jak „Kursy”, „Konsultacje”, „Pliki do pobrania” i „Książki”,
- [ ] wybierać kategorię WooCommerce z listy zamiast wpisywania jej identyfikatora albo klasy CSS,
- [ ] automatycznie zliczać zakupione i ręcznie przyznane produkty z wybranej kategorii oraz jej podkategorii,
- [ ] umożliwić zmianę etykiety pozycji niezależnie od nazwy kategorii WooCommerce,
- [ ] dodać widżet „Wymaga Twojej uwagi” z automatycznymi regułami i edytowalnymi komunikatami,
- [ ] dodać widżet „Ostatnie zamówienie” z konfigurowalnymi etykietami, przyciskiem i zakresem wyświetlanych danych,
- [ ] dodać pojedynczy widżet szybkiego dostępu z wyborem typu danych, ikony, odnośnika i stanu „W budowie”,
- [ ] dodać widżet ostatnio nabytych produktów oraz opcjonalny limit wyświetlanych pozycji,
- [ ] zapewnić automatyczne odnośniki do endpointów konta z możliwością świadomego ustawienia własnego adresu,
- [ ] udostępnić kontrolki treści: tytuły, opisy, etykiety przycisków, teksty pustych stanów i widoczność poszczególnych elementów,
- [ ] udostępnić kontrolki wyglądu: typografia, kolory, obramowanie, promień, odstępy, ikony i ustawienia responsywne,
- [ ] wyświetlać rzeczywiste lub bezpieczne przykładowe dane w podglądzie Elementora,
- [ ] zachować wspólną warstwę danych w PHP, aby wszystkie widżety korzystały z tych samych liczników, zamówień i uprawnień,
- [ ] pozostawić dotychczasowe shortcode’y jako warstwę zgodności wstecznej,
- [ ] zadbać o dostępność klawiatury, poprawne nagłówki i atrybuty ARIA,
- [ ] przygotować możliwość eksportowania i ponownego użycia gotowego układu panelu.
