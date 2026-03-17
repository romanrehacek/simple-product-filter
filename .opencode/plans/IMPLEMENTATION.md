# WC Simple Filter — Implementačný plán

## Prehľad

Plugin zobrazuje konfigurovateľné produktové filtre na stránke obchodu (shop/archive).
Vkladá sa cez shortcode `[wc_simple_filter]` alebo PHP funkciu `wc_simple_filter()`.
Plugin je **read-only** voči WooCommerce produktom.

Vývoj je rozdelený na **2 fázy**:
1. **Fáza 1 – Admin UI** (táto fáza)
2. **Fáza 2 – Frontend** (po dokončení Fázy 1)

---

## Fáza 1 – Admin UI

### 1.1 Základná štruktúra pluginu

Súbory ktoré treba vytvoriť:

```
wc-simple-filter/
├── wc-simple-filter.php          # Plugin header + bootstrap
├── uninstall.php                 # Cleanup on uninstall
├── includes/
│   ├── class-plugin.php          # Hlavný orchestrátor / hook loader
│   ├── admin/
│   │   ├── class-admin.php       # Integrácia WC settings tab
│   │   ├── class-filters-tab.php # Záložka: repeater filtrov
│   │   ├── class-settings-tab.php# Záložka: všeobecné nastavenia
│   │   ├── class-help-tab.php    # Záložka: nápoveda / docs
│   │   └── class-filter-edit.php # Stránka editácie jedného filtra
│   ├── class-filter-manager.php  # CRUD pre konfiguráciu filtrov (DB)
│   ├── class-index-manager.php   # Build/query value index tabuľky
│   └── class-ajax-handler.php    # AJAX endpointy (save, reindex)
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js
├── templates/
│   └── admin/
│       ├── filters-tab.php
│       ├── settings-tab.php
│       ├── help-tab.php
│       └── filter-edit.php
└── languages/
```

---

### 1.2 Databáza

#### Tabuľka `{prefix}wc_sf_filters`

Každý filter = jeden riadok.

| Stĺpec       | Typ            | Popis                                              |
|--------------|----------------|----------------------------------------------------|
| id           | INT UNSIGNED   | PK, AUTO_INCREMENT                                 |
| sort_order   | INT UNSIGNED   | Poradie vo výpise (drag & drop)                    |
| filter_type  | VARCHAR(50)    | `brand`, `status`, `sale`, `price`, `attribute_*`, `meta_*` |
| filter_style | VARCHAR(20)    | `checkbox`, `radio`, `dropdown`, `multi_dropdown`, `slider` |
| label        | VARCHAR(255)   | Zobrazovaný názov filtra                           |
| show_label   | TINYINT(1)     | 1 = zobraziť názov nad filtrom                     |
| config       | LONGTEXT       | JSON s type-specific nastaveniami (viď nižšie)     |
| created_at   | DATETIME       | Timestamp vytvorenia                               |
| updated_at   | DATETIME       | Timestamp poslednej úpravy                         |

**Štruktúra `config` JSON** (príklady podľa typu):

```json
// Checkbox / radio / dropdown – výber hodnôt
{
  "include_values": ["red", "blue"],   // prázdne = všetky
  "exclude_values": [],
  "sort_by": "name",                   // "name" | "count" | "custom"
  "sort_dir": "asc",                   // "asc" | "desc"
  "logic": "or",                       // "and" | "or"
  "hide_empty": true
}

// Slider (price, attribute číselná)
{
  "min": 0,
  "max": 10000,
  "step": 10,
  "logic": "between"
}

// Radio s manuálnymi rozsahmi (napr. cena)
{
  "ranges": [
    {"label": "1 € – 50 €", "min": 1, "max": 50},
    {"label": "51 € – 100 €", "min": 51, "max": 100}
  ],
  "logic": "or"
}
```

#### Tabuľka `{prefix}wc_sf_index`

Rýchly lookup: koľko produktov má danú hodnotu (pre hide-empty).

| Stĺpec       | Typ            | Popis                                              |
|--------------|----------------|----------------------------------------------------|
| id           | INT UNSIGNED   | PK, AUTO_INCREMENT                                 |
| filter_type  | VARCHAR(50)    | Typ filtra (`brand`, `attribute_pa_color`, atď.)   |
| value        | VARCHAR(255)   | Hodnota (term slug, meta value)                    |
| product_count| INT UNSIGNED   | Počet viditeľných produktov s touto hodnotou       |
| updated_at   | DATETIME       | Kedy bol index naposledy prepočítaný               |

Index sa prebuduje:
- Manuálne tlačidlom v admin UI
- Automaticky pri uložení/aktualizácii WooCommerce produktu (`save_post`, `woocommerce_update_product`)

---

### 1.3 Typy filtrov

| filter_type       | Popis                                      | Povolené štýly                              | Fixný štýl? |
|-------------------|--------------------------------------------|---------------------------------------------|-------------|
| `brand`           | Značka (taxonomy `product_brand` alebo PA) | checkbox, radio, dropdown, multi_dropdown   | nie         |
| `status`          | Stav skladu (instock, outofstock, onbackorder) | checkbox                               | **áno**     |
| `sale`            | Zľava (je/nie je v akcii)                 | checkbox                                    | **áno**     |
| `price`           | Cena produktu                              | slider, radio (rozsahy)                     | nie         |
| `attribute_{slug}`| WC product attribute (PA taxonomy)        | checkbox, radio, dropdown, multi_dropdown, slider (ak číselný) | nie |
| `meta_{key}`      | Custom field (post meta)                  | checkbox, radio, dropdown, multi_dropdown, slider (ak číselný) | nie |

---

### 1.4 Admin UI – WC Settings záložka

Plugin pridá záložku do **WooCommerce > Nastavenia** (hook `woocommerce_settings_tabs_array`).

#### Podzáložky (sub-tabs, navigácia cez GET parameter `wc_sf_tab`):

1. **Filtre** (`?wc_sf_tab=filters`) – hlavná správa filtrov
2. **Nastavenia** (`?wc_sf_tab=settings`) – všeobecné nastavenia pluginu
3. **Nápoveda** (`?wc_sf_tab=help`) – dokumentácia pre vývojára

---

### 1.5 Záložka „Filtre" (repeater)

Zobrazuje zoznam nakonfigurovaných filtrov s možnosťou:

- **Pridať nový filter** (tlačidlo „+ Pridať filter")
- **Drag & drop** zoradenie (jQuery UI Sortable, ukladá `sort_order`)
- Každý riadok repeaterà zobrazuje:
  - Handle na drag & drop
  - Ikona typu filtra
  - Názov filtra (`label`)
  - Typ filtra (`filter_type` – čitateľný popis)
  - Štýl filtra (`filter_style`) – výber cez `<select>` priamo v riadku
    - Niektoré typy majú fixný štýl (status, sale) – `<select>` disabled
  - **Tlačidlo ozubeného kolieska** → otvára stránku editácie daného filtra
  - **Tlačidlo zmazania** (s potvrdením)

#### Inline pridanie filtra:

Po kliknutí na „+ Pridať filter" sa zobrazí modal alebo inline row kde si zvolím:
1. **Typ filtra** (výber z rozbaľovacieho zoznamu – dynamicky načítaný: brand, status, sale, price, + všetky WC atribúty, + custom fieldy)
2. **Štýl filtra** (vypĺňa sa podľa zvoleného typu – niektoré sú fixné)
3. Uloženie → AJAX → nový riadok sa objaví v repeateri

---

### 1.6 Stránka editácie filtra (`class-filter-edit.php`)

Samostatná admin stránka dostupná cez URL:
`/wp-admin/admin.php?page=wc-settings&tab=wc_sf&wc_sf_tab=filters&action=edit&filter_id={id}`

#### Spoločné nastavenia (všetky typy):

| Pole               | Popis                                          |
|--------------------|------------------------------------------------|
| Názov filtra       | Text input, `label`                            |
| Zobraziť názov     | Checkbox, `show_label`                         |
| Štýl zobrazenia    | Select (povolené štýly pre daný typ), `filter_style` |
| Zoradenie hodnôt   | Select: Podľa názvu A→Z, Z→A, Podľa počtu ↑↓, Vlastné | 
| AND / OR logika    | Radio: filtruj produkty ktoré majú VŠETKY / ASPOŇ JEDNU zvolených hodnôt |
| Skryť prázdne      | Checkbox – nezobrazovať hodnoty bez produktov  |

#### Nastavenia špecifické podľa štýlu/typu:

**Pre `checkbox` / `radio` / `dropdown` / `multi_dropdown` s hodnotami:**
- Výber hodnôt na zobrazenie:
  - Zoznam všetkých dostupných hodnôt (z WC / post meta) s checkboxmi
  - Možnosť „Zobraziť všetky" vs. manuálny výber
  - Drag & drop poradie hodnôt (pri custom sort)

**Pre `slider` (číselné hodnoty – cena, číselné atribúty/meta):**
- Min hodnota (predvolená)
- Max hodnota (predvolená)
- Krok (step)

**Pre `radio` s cenovými/číselnými rozsahmi:**
- Repeater rozsahov:
  - Každý rozsah: `od` (number), `do` (number), `label` (text, auto-generovaný ale editovateľný)
  - Pridaj / ober riadok
  - Posledný rozsah môže mať „∞" ako max

**Pre `status` (fixný typ):**
- Výber stavov na zobrazenie: instock, outofstock, onbackorder (checkboxy)
- Vlastné labely pre každý stav

---

### 1.7 Záložka „Nastavenia"

Všeobecné nastavenia pluginu uložené v `wp_options` ako `wc_sf_settings`.

| Nastavenie               | Typ       | Default  | Popis                                                        |
|--------------------------|-----------|----------|--------------------------------------------------------------|
| Filtrovanie              | Radio     | `ajax`   | `ajax` = okamžite bez reloadu, `submit` = tlačidlo, `reload` = refresh stránky |
| Zobraziť tlačidlo Filter | Checkbox  | `false`  | Zobraziť submit tlačidlo (relevantné pre `submit` режим)      |
| Text tlačidla Filter     | Text      | „Filter" | Label pre submit tlačidlo                                    |
| Zobraziť tlačidlo Reset  | Checkbox  | `true`   | Zobraziť „Zrušiť filtre" tlačidlo                           |
| Text tlačidla Reset      | Text      | „Zrušiť filtre" | Label pre reset tlačidlo                             |
| Skryť prázdne hodnoty    | Checkbox  | `true`   | Globálna predvolená hodnota (prepisuje sa per-filter)        |
| Zmazať dáta pri odinštalácii | Checkbox | `false` | Či sa DB tabuľky zmažú pri odinštalácii pluginu            |

---

### 1.8 Záložka „Nápoveda"

Statická informatívna stránka s:

- Popis shortcodu: `[wc_simple_filter]` – parametre, príklady
- PHP kód na vloženie do šablóny: `<?php wc_simple_filter(); ?>`
- Opis parametrov shortcodu (napr. `id` pre konkrétny filter, `category` pre obmedzenie na kategóriu)
- Odkaz na dokumentáciu / support

---

### 1.9 AJAX endpointy

| Action                      | Handler metóda          | Popis                                 |
|-----------------------------|-------------------------|---------------------------------------|
| `wc_sf_save_filter`         | `save_filter()`         | Uloženie / aktualizácia filtra        |
| `wc_sf_delete_filter`       | `delete_filter()`       | Zmazanie filtra                       |
| `wc_sf_reorder_filters`     | `reorder_filters()`     | Uloženie nového poradia (drag & drop) |
| `wc_sf_reindex`             | `reindex()`             | Prebudovanie index tabuľky            |
| `wc_sf_get_type_values`     | `get_type_values()`     | Načítanie dostupných hodnôt pre typ   |

Všetky AJAX akcie:
- Overujú nonce (`wc_sf_admin_nonce`)
- Overujú kapacitu `manage_woocommerce`
- Vracajú `wp_send_json_success()` / `wp_send_json_error()`

---

### 1.10 JavaScript (admin.js)

Funkcie:

1. **Repeater** – pridávanie/mazanie riadkov filtrov
2. **Sortable** – jQuery UI drag & drop, po pusti pošle AJAX `wc_sf_reorder_filters`
3. **Inline select** – dynamická zmena dostupných štýlov po zmene typu filtra
4. **Filter edit page** – dynamické zobrazovanie/skrývanie sekcií podľa zvoleného štýlu
5. **Rozsahy (ranges repeater)** – pridávanie/mazanie riadkov cenovych rozsahov
6. **Hodnoty (values picker)** – načítanie dostupných hodnôt cez AJAX, checkboxy
7. **Reindex** – tlačidlo spustí AJAX, zobrazí progress/výsledok

---

### 1.11 Bezpečnostné požiadavky

- Každý PHP súbor začína: `if ( ! defined( 'ABSPATH' ) ) { exit; }`
- Nonce na každom formulári a AJAX požiadavke
- Kapacitná kontrola (`manage_woocommerce`) pred každou akciou
- Sanitizácia vstupu: `sanitize_text_field()`, `absint()`, `wp_unslash()`
- Escapovanie výstupu: `esc_html()`, `esc_attr()`, `wp_json_encode()`
- DB queries cez `$wpdb->prepare()`

---

### 1.12 Aktivácia / deaktivácia / odinštalácia

**Aktivácia** (`register_activation_hook`):
- Vytvorenie DB tabuliek (`dbDelta()`)
- Uloženie verzie schémy do options (`wc_sf_db_version`)
- Uloženie default nastavení ak ešte neexistujú

**Deaktivácia** (`register_deactivation_hook`):
- Žiadne dátové zmeny (tabuľky zostávajú)

**Odinštalácia** (`uninstall.php`):
- Skontroluje `WP_UNINSTALL_PLUGIN`
- Ak `wc_sf_settings['delete_on_uninstall'] === true`:
  - Zmaže tabuľky `wc_sf_filters` a `wc_sf_index`
  - Zmaže options: `wc_sf_settings`, `wc_sf_db_version`

---

## Fáza 2 – Frontend (plánovaná)

> Táto fáza sa začne po dokončení a otestovaní Fázy 1.

- Shortcode `[wc_simple_filter]` a PHP funkcia `wc_simple_filter()`
- Renderovanie filtrov na shop/archive stránke
- AJAX filtrovanie produktov (bez reload)
- Submit/reload režim
- CSS štýlovanie
- Integrácia s WooCommerce query (WP_Query / `wc_get_products`)
- Podpora pre URL state (query parametry v URL pre zdieľanie)
- Reset filtrov

---

## Poradie implementácie (Fáza 1)

1. [ ] Hlavný plugin súbor + bootstrap (`wc-simple-filter.php`)
2. [ ] `class-plugin.php` – hook loader
3. [ ] Aktivácia + DB tabuľky (`class-filter-manager.php` – `install()`)
4. [ ] `uninstall.php`
5. [ ] WC settings tab integrácia (`class-admin.php`)
6. [ ] Záložka Filtre – repeater UI (`class-filters-tab.php` + template)
7. [ ] AJAX – save/delete/reorder filtrov (`class-ajax-handler.php`)
8. [ ] Stránka editácie filtra (`class-filter-edit.php` + template)
9. [ ] `class-index-manager.php` – build/query index
10. [ ] Záložka Nastavenia (`class-settings-tab.php` + template)
11. [ ] Záložka Nápoveda (`class-help-tab.php` + template)
12. [ ] `admin.css` + `admin.js` – interaktivita
13. [ ] Testovanie + commit

---

## Git workflow

- Každý väčší celok (napr. "DB + aktivácia", "repeater UI", "filter edit page") = 1 commit
- Commit až po manuálnej kontrole funkčnosti
- Konvenčné commit správy: `feat: ...`, `fix: ...`, `refactor: ...`
