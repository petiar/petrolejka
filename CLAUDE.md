# Petrolejka – web OZ Petrolejka (petrolejka.sk)

Komunitný web občianskeho združenia Petrolejka z Liptovského Petra. Je to nezávislá alternatíva k oficiálnej stránke obce
(časopis, podujatia, dianie v obci, pamäť obce, členstvo). Web **nesmie pôsobiť ako oficiálna stránka obce**: nepoužívať
erb a v päte nechať disclaimer. Ciele vychádzajú zo stanov (čl. II): podujatia, internetový časopis, informovanie
o dianí, história a tradície, dobrovoľníctvo.

Komunikácia s používateľom je po slovensky a texty na webe sú po slovensky.

## Stack

- Drupal 11.4 (`drupal/recommended-project`), docroot `web/`, profil `standard` v slovenčine.
- Lokálne **ddev**: `https://petrolejka.ddev.site:33001`, PHP 8.4, MariaDB 11.8. Príkazy `ddev drush …`, `ddev composer …`.
  Produkcia beží na PHP 8.5.
- Contrib moduly: pathauto, token, admin_toolbar, metatag, simple_sitemap (base URL `https://petrolejka.sk`), webform.
- Konfigurácia je v `config/sync` (`$settings['config_sync_directory'] = '../config/sync'`).
- Drupal 11.3+ profil standard **nevytvára** typy obsahu ani pole `body`. Všetko vytvorili skripty v `scripts/`.

## Obsahový model

Typy obsahu (node):
- `article` (Článok): `field_perex`, `field_image`, `field_rubrika` → tax. `rubrika`, `field_autor` → osoba (viac hodnôt),
  `field_cislo` → cislo, `field_podujatie` → podujatie, `field_galeria`.
- `cislo` (Číslo časopisu): `field_poradie`, `field_image` (obálka), body = úvodník.
- `podujatie`: `field_datum` (daterange, ukladá sa v UTC), `field_miesto` → tax. `miesto`, `field_typ_podujatia` →
  tax. `typ_podujatia`, `field_image`, `field_vstupne`, `field_registracia` (link), `field_galeria`.
- `sprava` (Správa z obce): body, `field_zdroj` (link).
- `osoba`: `field_rola`, `field_image`.
- `page`: `field_image`, `field_prilohy` (dokumenty).

Slovníky: `rubrika` (Z obce, Pamäť obce, Rozhovor, Príroda, Kultúra), `typ_podujatia`, `miesto`.
View mode `lead` (hlavný článok na titulke) existuje len pre `article`.

Views: `casopis` (/casopis), `cisla` (/casopis/cisla), `dianie` (/dianie), `podujatia` (/podujatia = nadchádzajúce,
/podujatia/archiv = minulé, filtruje sa podľa `field_datum_end_value` voči `now`).

Pathauto: `casopis/[title]`, `casopis/cislo-[poradie]`, `podujatia/[title]`, `dianie/[title]`, `ludia/[title]`,
stránky `[title]`, rubriky `casopis/rubrika/[name]`.

Webform `prihlaska` (/form/prihlaska) slúži ako prihláška za člena. Stanovy povoľujú podať žiadosť elektronicky.

Menu `main`: Časopis, Dianie v obci, Podujatia, Pamäť obce (term page), Zapoj sa, O nás. Menu `footer` je v päte.
Statický odkaz „Domov“ (`standard.front_page`) je vypnutý.

## Podcast

- Typ obsahu `epizoda` (Epizóda podcastu): `field_cislo_epizody`, `field_perex` (krátky popis do aplikácií),
  `field_audio` (mp3/m4a, max 256 MB, `public://podcast/RRRR`), body (poznámky), `field_image` (nepovinný štvorcový
  obrázok), `field_trvanie` (sekundy, **vypĺňa sa automaticky** v `petrolejka_core_node_presave()` cez getID3
  `james-heinrich/getid3`). Alias `podcast/[title]`.
- `/podcast`: view `podcast` (kópia `dianie`). Box s obalom a odkazmi na odber pridáva
  `petrolejka_core_views_pre_render()` (šablóna `petrolejka-podcast-subscribe`).
- **Feed** `/podcast/feed.xml` (`PodcastFeedController`): RSS 2.0 + iTunes/podcast/atom/content namespaces.
  `guid` je UUID uzla (nemeniť), `enclosure` má veľkosť a MIME súboru. Epizódy bez audia sa preskakujú. Odkaz
  `rel=alternate` je v hlavičke každej stránky.
- **Nastavenia podcastu** `/admin/config/media/podcast` (oprávnenie `administer petrolejka podcast`): názov, popis,
  autor, e-mail vlastníka, kategória Apple, explicit, obal, URL na Apple Podcasts a Spotify. Ukladajú sa do
  **State API** (`PodcastSettings`, kľúč `petrolejka_core.podcast`), nie do konfigurácie, aby ich `drush deploy`
  neprepisoval. Predvolený obal 3000×3000 je `petrolejka_core/images/podcast-cover.jpg` (zdroj
  `assets/logo/petrolejka-podcast-cover.svg`).
- Menu odkaz „Podcast“ v main menu (obsah, nie konfigurácia) vytvoril deploy hook
  `petrolejka_core_deploy_podcast_menu()` v `petrolejka_core.deploy.php`. Podobné jednorazové zmeny obsahu na
  produkcii robiť cez deploy hooky.
- `web/.user.ini` zvyšuje limit nahrávania na 256 MB (na serveri bolo 2 MB). Websupport ho akceptuje (overené
  28. 10. 2026 vo formulári).

## Darovanie (PAY by square)

- Blok `petrolejka_donation` (`DonationBlock`) je na stránkach **/zapoj-sa** a **/podporte-nas** (región content, viditeľnosť podľa cesty,
  config `block.block.petrolejka_donation`). Návštevník zvolí alebo zadá sumu (a voliteľne meno) a **QR kód sa
  generuje v prehliadači** (`js/donation.js`, ES modul). Server nič neprijíma ani neukladá. Dá sa stiahnuť aj PNG
  (banky vedia načítať QR z obrázka v galérii, SVG nie).
- Knižnice sú vo `petrolejka_core/js/vendor/` (bysquare 4.1.0 ako samostatný ESM bundle z esm.sh, qrcode-generator
  2.0.4), bez npm/build kroku (pozri README tam). Na PHP sa nedalo spoľahnúť, lebo PAY by square potrebuje LZMA,
  ktoré PHP natívne nemá. `encode()` sám odstraňuje diakritiku.
- Nastavenia `/admin/config/services/darovanie` (oprávnenie `administer petrolejka donation`): IBAN (kontrola
  mod 97), BIC, príjemca, VS, KS, správa, predvolené sumy, úvodný text. Ukladajú sa v **State API**
  (`DonationSettings`), deploy ich neprepíše. **Kým nie je vyplnený IBAN, blok sa nezobrazí.**
- Apache na Websupporte nepozná `.mjs`. `AddType text/javascript .mjs` je v `assets/scaffold/htaccess-append.txt`.
- Pri zmene `donation.js` zvýšiť `version` v `petrolejka_core.libraries.yml` (URL `?v=` kvôli proxy cache), pri
  aktualizácii vendor knižníc zmeniť verziu v názve súboru.

## Kód

- `web/modules/custom/petrolejka_core`: route `/uvod` (titulná stránka, `system.site:page.front`),
  `FrontController` + šablóna `petrolejka-front.html.twig`. Obsahuje hlavný článok (sticky/najnovší), karty, 4
  nadchádzajúce podujatia, 5 správ a výzvu na zapojenie. Render polia sa posielajú ako zoznam
  jednotlivých `view()`, nie `viewMultiple()`, lebo v Twigu sa cez ne iteruje.
- `web/themes/custom/petrolejka`: vlastná téma (`base theme: false`), predvolená.
  - Farby obce: modrá `--blue-700 #1d3f8f` / `--blue-900`, biela, žltá `--yellow-500 #f4c20d` (plameň petrolejky).
    Papierový podklad `--paper`. Písma Fraunces (nadpisy) a Source Sans 3 (text) z Google Fonts.
  - Všetko CSS je v `css/style.css` (premenné na `:root`). `js/nav.js` je mobilné menu.
  - `petrolejka.theme`: preprocess page (logo, úvodná fotka `images/hero.jpg` + kredit), skrytie page title na uzloch
    a titulke, `petrolejka_event_dates()` (dátumy podujatí v miestnom čase), čas čítania článku. Všetky teasery
    dostávajú suggestion `node__teaser`.
  - Šablóny: plné zobrazenia `node--article--full`, `node--podujatie--full` a všeobecné `node.html.twig`. Teasery
    `node--teaser` (karta), `node--podujatie--teaser`, `node--sprava--teaser`. Ďalej `node--article--lead`,
    `field--field-galeria` a `menu--main`.
  - **Pozor v Twigu:** `node.field_x.entity` je pravdivé aj pri prázdnom poli. Na kontrolu používať
    `not node.field_x.isEmpty`.
  - Logo v téme: `images/logo.svg` (= `assets/logo/petrolejka-icon-color.svg`), `logo-inverse.svg` v päte,
    `petrolejka-favicon.svg` (nastavené v `petrolejka.settings`). URL loga majú `?v=<hash obsahu>`
    (`petrolejka_asset_url()`), aby ich proxy nekešovala. Pri zmene faviconu súbor premenovať. Nápis v hlavičke je HTML: `<span class="brand__petro">Petro</span>lejka`
    (Fraunces 800 / 300).

## Logo (`assets/logo/`)

- Zvolený variant A: lampa (nádržka, horák, sklenený cylinder so žiarou, žltý plameň) a nápis „**Petro**lejka“.
  „Petro“ je hrubým Fraunces (800) v modrej, „lejka“ tenkým (300) v tmavomodrej. Na tmavom pozadí je „Petro“
  žlté a „lejka“ biele. **Petro = slangový názov Liptovského Petra**, preto je zvýraznené. Bez podnadpisu.
- Verzie: `logo-horizontal`, `logo-stacked`, `icon` (vždy `-color` / `-inverse`), `icon-badge` (kruh). PNG v `png/`.
- Generuje ich `scripts/make-logo.py` (fonttools + resvg-py vo venv, font Fraunces z Google Fonts). Text je
  prevedený na krivky.

## Skripty (`scripts/`)

Spúšťajú sa cez `ddev drush php:script scripts/<názov>.php`. Slúžili na prvotné vytvorenie webu. Dnes je zdrojom
pravdy `config/sync`.
- `setup-structure.php`: typy obsahu, polia, slovníky, form/view displaye.
- `setup-views.php`: views (zmaže a znova vytvorí).
- `setup-site.php`: bloky témy, view mode `lead`, pathauto vzory, menu `footer`, webform `prihlaska`.
- `demo-content.php`: **zmaže všetky uzly a menu odkazy** a vytvorí ukážkový obsah. Na produkcii nespúšťať!

Fotky sú v `content/photos/` (Wikimedia Commons, CC BY / CC BY-SA, licencie v `credits.json`). Zoznam zdrojov je na
stránke `/fotografie` a odkaz naň je v päte. Nové fotky z internetu brať len s jasnou licenciou a autora uviesť
na `/fotografie`.

## Produkcia a deploy

- Hosting **Websupport** (zdieľaný), SSH `uid6682083@shell.r6.websupport.sk -p 29170`. Lokálne sa pripája kľúčom
  `~/.ssh/petrolejka_deploy` (`ssh -i ~/.ssh/petrolejka_deploy -o IdentitiesOnly=yes -p 29170 …`).
- Projekt je na serveri v `~/petrolejka.sk` (git checkout vetvy `main`). Document root domény je
  `~/petrolejka.sk/web`. Priečinky `logs/` a `sub/` patria hostingu a sú v `.gitignore`.
- DB MariaDB na `db.r6.websupport.sk`. Prístupy sú **len** v `web/sites/default/settings.local.php` na serveri
  (spravuje ho používateľ, je tam aj `hash_salt`, práva 444). Nikdy ich nedávať do gitu.
- `settings.php` načíta `settings.local.php`, ak existuje. Mimo ddev nastavuje `trusted_host_patterns` pre
  petrolejka.sk a www.
- **Automatický deploy**: push do `main` spustí `.github/workflows/deploy.yml`. GitHub Actions sa prihlási na server
  cez SSH (secrets `SSH_PRIVATE_KEY` = privátny kľúč `petrolejka_deploy`, `SSH_KNOWN_HOSTS`). Server si potom urobí
  `git fetch && git reset --hard origin/main` (vlastný read-only Deploy key `~/.ssh/github_deploy`),
  `composer install --no-dev` a `drush deploy`. Priebeh je na https://github.com/petiar/petrolejka/actions.
  GitHub CLI (`gh`) lokálne nie je, stav behov sa dá zistiť cez `curl` na GitHub API.
- Remote: `git@github.com:petiar/petrolejka.git`, vetva `main`.

### Workflow zmien
- Štruktúru a nastavenia meniť lokálne, potom `ddev drush config:export -y`, commit a push. `drush deploy` ich
  naimportuje. Zmeny konfigurácie urobené priamo na produkcii ďalší deploy prepíše.
- Obsah (články, podujatia…) sa pridáva priamo na produkcii a deploy ho nemení. Lokálna DB sa líši od produkcie.
  Na stiahnutie produkčnej DB použiť `drush sql:dump` na serveri a `ddev import-db`.
- Pri prvom spustení (26. 10. 2026) sa na produkciu preniesla lokálna DB s ukážkovým obsahom a fotky z
  `sites/default/files`.

### Známe problémy
- **AVIF:** image styles generujú derivácie `*.jpg.avif`. Apache na Websupporte nepozná `.avif`, posielal
  `image/jpeg` a kvôli `nosniff` sa obrázky nezobrazovali. Opravené pripojením `AddType image/avif .avif`
  do `.htaccess` cez composer scaffold (`assets/scaffold/htaccess-append.txt`, `extra.drupal-scaffold.file-mapping`
  v composer.json). `web/.htaccess` neupravovať ručne, prepíše sa.
- Pred serverom je proxy (openresty), ktorá kešuje statické súbory (`max-age` 1 rok). Zmenený súbor na tej istej
  URL sa návštevníkom neukáže, treba zmeniť URL (query `?v=`, nový názov). CSS/JS cez libraries to riešia samé.
- Titulok a description titulky sú nastavené v `metatag.metatag_defaults.front`.

## Otvorené / TODO
- Odporúčaná zmena hesla k DB (bolo zdieľané v chate).
- Po založení účtu vyplniť IBAN v /admin/config/services/darovanie (zapne QR darovanie na Zapoj sa) a doplniť
  IBAN a údaje pre 2 % aj na stránke „Podporte nás“ (zatiaľ text „bude doplnený“).
- Ukážkový obsah (označený „Toto je ukážkový text…“) nahradiť skutočným.
- Podcast: po prvej epizóde poslať feed do Apple Podcasts Connect a Spotify for Creators a URL doplniť v nastaveniach.
- Headless screenshoty (Chrome/Docker) nie sú v tomto prostredí povolené. Vizuálne kontroly robí používateľ.
