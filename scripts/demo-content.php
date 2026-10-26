<?php

/**
 * @file
 * Ukážkový obsah s fotkami z Wikimedia Commons.
 *
 * Spustenie: ddev drush php:script scripts/demo-content.php
 */

use Drupal\Core\File\FileExists;
use Drupal\file\Entity\File;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

$root = DRUPAL_ROOT . '/../content/photos';
$credits = json_decode(file_get_contents("$root/credits.json"), TRUE);
$credit_by_file = array_column($credits, NULL, 'file');

$fs = \Drupal::service('file_system');
$dir = 'public://ukazka';
$fs->prepareDirectory($dir, 1);

/** Vytvorí obrázkové pole z lokálnej fotky. */
$image = function (string $file, string $alt) use ($root, $fs, $dir, $credit_by_file): array {
  $uri = $fs->copy("$root/$file", "$dir/$file", FileExists::Replace);
  $existing = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $uri]);
  $entity = reset($existing) ?: File::create(['uri' => $uri, 'status' => 1]);
  $entity->save();
  $c = $credit_by_file[$file];
  return ['target_id' => $entity->id(), 'alt' => $alt, 'title' => "Foto: {$c['author']}, {$c['license']}, Wikimedia Commons"];
};

/** Nájde alebo vytvorí termín. */
$term = function (string $vid, string $name, int $weight = 0): int {
  $found = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid, 'name' => $name]);
  $t = reset($found) ?: Term::create(['vid' => $vid, 'name' => $name, 'weight' => $weight]);
  $t->save();
  return (int) $t->id();
};

$html = fn(string $text) => ['value' => $text, 'format' => 'basic_html'];
$note = '<p><em>Toto je ukážkový text, ktorý slúži na odladenie dizajnu stránky.</em></p>';

// Vymažeme starý ukážkový obsah, aby sa skript dal spúšťať opakovane.
$storage = \Drupal::entityTypeManager()->getStorage('node');
$storage->delete($storage->loadMultiple($storage->getQuery()->accessCheck(FALSE)->execute()));
$links = \Drupal::entityTypeManager()->getStorage('menu_link_content');
$links->delete($links->loadMultiple());

// --- Slovníky ---------------------------------------------------------------
$r = [];
foreach (['Z obce', 'Pamäť obce', 'Rozhovor', 'Príroda', 'Kultúra'] as $i => $name) {
  $r[$name] = $term('rubrika', $name, $i);
}
$miesto_kd = $term('miesto', 'Kultúrny dom Liptovský Peter');
$miesto_ihrisko = $term('miesto', 'Ihrisko');
$typ = [];
foreach (['Premietanie', 'Workshop', 'Prednáška', 'Komunitné stretnutie'] as $name) {
  $typ[$name] = $term('typ_podujatia', $name);
}

// --- Osoby a číslo časopisu -------------------------------------------------
$redakcia = Node::create([
  'type' => 'osoba',
  'title' => 'Redakcia Petrolejky',
  'field_rola' => 'Redakcia',
  'body' => $html('<p>Spoločný podpis redakčného tímu občianskeho združenia Petrolejka.</p>'),
]);
$redakcia->save();

$cislo = Node::create([
  'type' => 'cislo',
  'title' => 'Číslo 1 – Jeseň 2026',
  'field_poradie' => 1,
  'field_image' => $image('img8.jpg', 'Pohľad na Liptovský Hrádok a Západné Tatry'),
  'body' => $html('<p>Vitajte pri prvom čísle Petrolejky. Chceme písať o tom, čo sa v obci deje, kto tu žije a čo sa tu kedysi dialo.</p>' . $note),
]);
$cislo->save();

// --- Články -------------------------------------------------------------------
$articles = [
  [
    'title' => 'Petrolejka sa rozsvietila',
    'perex' => 'V Liptovskom Petri vzniká nové občianske združenie. Chceme organizovať podujatia, písať o živote v obci a spájať ľudí, ktorým na nej záleží.',
    'rubrika' => 'Z obce',
    'img' => ['img8.jpg', 'Zasnežené Západné Tatry nad Liptovským Hrádkom'],
    'sticky' => TRUE,
    'body' => '<p>Petrolejka bola kedysi v každej domácnosti. Svietila, keď nebolo iné svetlo, a ľudia sa okolo nej schádzali. Podobne chceme fungovať aj my – ako miesto, kde sa ľudia stretnú a dozvedia, čo sa okolo nich deje.</p><h2>Čo chceme robiť</h2><ul><li>organizovať kultúrne a komunitné podujatia, prednášky a premietania,</li><li>vydávať občasný internetový časopis o živote v obci,</li><li>zbierať a uchovávať históriu, tradície a spomienky,</li><li>podporovať dobrovoľníctvo a spoluprácu susedov.</li></ul><blockquote>Obec nie sú len budovy a cesty. Sú to ľudia a príbehy, ktoré medzi nimi vznikajú.</blockquote><p>Ak chcete pomôcť, napísať článok alebo sa stať členom, ozvite sa nám.</p>',
  ],
  [
    'title' => 'Martin Martinček: fotograf z Liptovského Petra',
    'perex' => 'V obci si pripomíname rodáka, ktorý celý život fotografoval Liptov. Stojí tu jeho rodný dom aj pomník.',
    'rubrika' => 'Pamäť obce',
    'img' => ['img7.jpg', 'Rodný dom Martina Martinčeka v Liptovskom Petri'],
    'galeria' => [['img6.jpg', 'Pomník Martina Martinčeka']],
    'body' => '<p>Martin Martinček patrí k najznámejším slovenským fotografom 20. storočia. V jeho záberoch sa objavuje krajina Liptova, hory aj ľudia pri práci.</p><p>V seriáli <strong>Pamäť obce</strong> sa chceme vracať k osobnostiam a miestam, ktoré k Liptovskému Petru patria. Máte doma staré fotografie alebo spomienky? Radi ich zverejníme.</p>',
  ],
  [
    'title' => 'Budova starej školy pri kostole',
    'perex' => 'Stará škola a evanjelický kostol pamätajú generácie obyvateľov. Ako by sme mohli priestory využiť dnes?',
    'rubrika' => 'Z obce',
    'img' => ['img3.jpg', 'Budova školy a evanjelický kostol v Liptovskom Petri'],
    'galeria' => [['img4.jpg', 'Pamätná tabuľa na budove školy']],
    'body' => '<p>Budova školy stojí v strede obce a na jej fasáde je pamätná tabuľa. Pýtame sa, aké spomienky na ňu majú pamätníci a aké nápady na jej využitie majú mladší obyvatelia.</p><p>Názory a spomienky nám môžete poslať e-mailom. Tie najzaujímavejšie zaradíme do ďalšieho čísla.</p>',
  ],
  [
    'title' => 'Evanjelická fara a jej príbehy',
    'perex' => 'Budova evanjelickej fary patrí medzi pamätihodnosti obce. Pozreli sme sa na ňu bližšie.',
    'rubrika' => 'Pamäť obce',
    'img' => ['img2.jpg', 'Budova evanjelickej fary v Liptovskom Petri'],
    'body' => '<p>Fara je zapísaná medzi pamätihodnosťami Liptovského Petra. V seriáli o budovách obce budeme hľadať odpovede na otázky, kto ju postavil, kto v nej žil a čo sa v nej dialo.</p>',
  ],
  [
    'title' => 'Pamätné tabule: koho si v obci pripomíname',
    'perex' => 'Na budovách v obci nájdeme viacero pamätných tabúľ. Každá z nich pripomína jeden príbeh.',
    'rubrika' => 'Pamäť obce',
    'img' => ['img5.jpg', 'Pamätná tabuľa v Liptovskom Petri'],
    'galeria' => [['img4.jpg', 'Pamätná tabuľa na budove školy']],
    'body' => '<p>Pamätné tabule si ľahko nevšimneme, hoci okolo nich chodíme denne. Spolu s čitateľmi chceme zmapovať, kde v obci sú a koho pripomínajú.</p>',
  ],
  [
    'title' => 'Prechádzka k rieke',
    'perex' => 'Na jar sa rieka pri obci mení na divokú vodu. Pozývame na prechádzku po brehu.',
    'rubrika' => 'Príroda',
    'img' => ['img1.jpg', 'Rozvodnená rieka s lávkou pri Liptovskom Petri'],
    'body' => '<p>Keď sa topí sneh v horách, rieka nabere silu a hukot počuť až do dediny. Je to dobrý čas na prechádzku – pevná obuv je nutnosť.</p>',
  ],
];
$created = strtotime('2026-10-26 12:00');
$article_ids = [];
foreach ($articles as $i => $a) {
  $node = Node::create([
    'type' => 'article',
    'title' => $a['title'],
    'field_perex' => $a['perex'],
    'field_rubrika' => $r[$a['rubrika']],
    'field_autor' => [$redakcia->id()],
    'field_cislo' => $cislo->id(),
    'field_image' => $image(...$a['img']),
    'field_galeria' => array_map(fn($g) => $image(...$g), $a['galeria'] ?? []),
    'body' => $html($a['body'] . $note),
    'sticky' => $a['sticky'] ?? FALSE,
    'created' => $created - $i * 86400 * 3,
    'uid' => 1,
  ]);
  $node->save();
  $article_ids[] = $node->id();
}

// --- Podujatia --------------------------------------------------------------------
// Časy sú v miestnom čase a do databázy idú v UTC.
$dt = fn(string $local) => (new \DateTime($local, new \DateTimeZone('Europe/Bratislava')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
$events = [
  ['Ustanovujúce stretnutie OZ Petrolejka', '2026-10-26 17:00', '2026-10-26 19:00', $miesto_kd, 'Komunitné stretnutie', 'Zdarma', 'Prvé stretnutie členov združenia: predstavili sme ciele a plány na najbližšie mesiace.'],
  ['Filmový večer pod petrolejkou', '2026-11-07 18:00', '2026-11-07 20:30', $miesto_kd, 'Premietanie', 'Dobrovoľné', 'Premietame slovenský film a potom sa pri čaji porozprávame. Vezmite si deku a dobrú náladu.'],
  ['Workshop: Ako písať do Petrolejky', '2026-11-15 10:00', '2026-11-15 12:00', $miesto_kd, 'Workshop', 'Zdarma', 'Ukážeme, ako napísať krátky článok, urobiť rozhovor so susedom a odfotiť podujatie mobilom.'],
  ['Liptovský Peter na starých fotografiách', '2026-11-28 17:00', '2026-11-28 19:00', $miesto_kd, 'Prednáška', 'Dobrovoľné', 'Večer spomienok nad starými fotografiami. Prineste vlastné fotky – naskenujeme ich na mieste.'],
  ['Mikulášske stretnutie na ihrisku', '2026-12-06 15:00', '2026-12-06 17:00', $miesto_ihrisko, 'Komunitné stretnutie', 'Zdarma', 'Stretnutie pre deti aj dospelých s balíčkami, teplým čajom a pesničkami.'],
];
foreach ($events as [$title, $from, $to, $miesto, $t, $vstupne, $text]) {
  Node::create([
    'type' => 'podujatie',
    'title' => $title,
    'field_datum' => ['value' => $dt($from), 'end_value' => $dt($to)],
    'field_miesto' => $miesto,
    'field_typ_podujatia' => $typ[$t],
    'field_vstupne' => $vstupne,
    'body' => $html("<p>$text</p>" . $note),
    'uid' => 1,
  ])->save();
}

// --- Správy z obce ----------------------------------------------------------------
$news = [
  ['Zasadnutie obecného zastupiteľstva', 'Na stránke budeme stručne zhrnúť, o čom poslanci rokovali, a pridáme odkaz na úradnú zápisnicu.'],
  ['Zimná údržba ciest: na čo myslieť', 'Pripomíname, aby autá neparkovali na úzkych uliciach a nebránili pluhom pri odhŕňaní snehu.'],
  ['Hľadáme staré fotografie obce', 'Pre seriál Pamäť obce zbierame fotografie spred roku 1990. Originál vám po naskenovaní vrátime.'],
  ['Petrolejka má novú webstránku', 'Spúšťame web, na ktorom nájdete časopis, kalendár podujatí aj správy z obce.'],
];
foreach ($news as $i => [$title, $text]) {
  Node::create([
    'type' => 'sprava',
    'title' => $title,
    'body' => $html("<p>$text</p>" . $note),
    'created' => $created - $i * 86400 * 2,
    'uid' => 1,
  ])->save();
}

// --- Stránky ----------------------------------------------------------------------
$credits_html = '<p>Fotografie na tejto stránke pochádzajú z <a href="https://commons.wikimedia.org/wiki/Category:Liptovsk%C3%BD_Peter">Wikimedia Commons</a> a sú použité podľa ich licencií:</p><ul>';
foreach ($credits as $c) {
  $name = htmlspecialchars(preg_replace('/^File:|\.(jpe?g|JPG)$/', '', $c['title']));
  $credits_html .= "<li><a href=\"{$c['page']}\">$name</a> – {$c['author']}, {$c['license']}</li>";
}
$credits_html .= '</ul>';

$pages = [
  'o-nas' => ['O nás', '<p><strong>Petrolejka</strong> je dobrovoľné, samostatné a nezávislé občianske združenie so sídlom v Liptovskom Petri.</p><h2>Naše ciele</h2><p>Podporujeme rozvoj kultúrneho, spoločenského, vzdelávacieho a komunitného života v obci a jej okolí, posilňujeme občiansku angažovanosť a podporujeme aktivity prospešné pre obyvateľov.</p><ul><li>organizujeme podujatia, prednášky, workshopy a filmové premietania,</li><li>podporujeme miestnu kultúru, tradície, históriu a regionálnu identitu,</li><li>vydávame občasný internetový časopis,</li><li>informujeme o dianí v obci a o iniciatívach obyvateľov,</li><li>podporujeme dobrovoľníctvo a spoluprácu,</li><li>spolupracujeme s obcou, školami, spolkami a inštitúciami.</li></ul><h2>Ako fungujeme</h2><p>Najvyšším orgánom je <strong>zhromaždenie členov</strong>, ktoré sa schádza najmenej dvakrát ročne. Činnosť medzi zhromaždeniami riadi trojčlenná <strong>rada združenia</strong> s funkčným obdobím štyri roky.</p><h2>Prípravný výbor</h2><ul><li>Peter Lachký</li><li>Václav Papež</li><li>Elena Jányová</li></ul><h2>Kontakt</h2><p>Občianske združenie Petrolejka<br>Nad ihriskom 178/19<br>033 01 Liptovský Peter<br><a href="mailto:info@petrolejka.sk">info@petrolejka.sk</a></p>'],
  'zapoj-sa' => ['Zapoj sa', '<p>Petrolejka je otvorená každému, komu záleží na Liptovskom Petri. Pomôcť môžete rôzne:</p><ul><li><strong>Napíšte článok</strong> – rozhovor so susedom, reportáž z podujatia, spomienku.</li><li><strong>Pomôžte s podujatím</strong> – príprava, upratovanie, technika, pečenie.</li><li><strong>Podeľte sa o históriu</strong> – staré fotografie, dokumenty, príbehy.</li><li><strong>Staňte sa členom</strong> – členom môže byť fyzická aj právnická osoba, ktorá súhlasí s cieľmi a stanovami združenia.</li></ul><p><a class="button button--primary" href="/form/prihlaska">Vyplniť prihlášku</a></p><p>O prijatí nového člena rozhoduje rada združenia, ktorá vás o rozhodnutí bude informovať.</p>'],
  'podporte-nas' => ['Podporte nás', '<p>Združenie funguje vďaka dobrovoľníkom, darom a grantom. Všetky príjmy používame výlučne na naše ciele.</p><h2>Darujte 2 % (3 %) z dane</h2><p>Údaje pre vyhlásenie budú doplnené po registrácii združenia.</p><h2>Dar na účet</h2><p>IBAN: <em>bude doplnený</em></p><h2>Pomôžte vecne</h2><p>Potrebujeme projektor, stoličky, ale aj šikovné ruky pri podujatiach.</p>'],
  'fotografie' => ['Zdroje fotografií', $credits_html],
];
$page_nodes = [];
foreach ($pages as $alias => [$title, $body]) {
  $node = Node::create([
    'type' => 'page',
    'title' => $title,
    'body' => ['value' => $body, 'format' => 'full_html'],
    'path' => ['alias' => "/$alias", 'pathauto' => 0],
    'uid' => 1,
  ]);
  $node->save();
  $page_nodes[$alias] = $node;
}

// --- Menu ------------------------------------------------------------------------
$main = [
  ['Časopis', 'internal:/casopis'],
  ['Dianie v obci', 'internal:/dianie'],
  ['Podujatia', 'internal:/podujatia'],
  ['Pamäť obce', 'internal:/taxonomy/term/' . $r['Pamäť obce']],
  ['Zapoj sa', 'entity:node/' . $page_nodes['zapoj-sa']->id()],
  ['O nás', 'entity:node/' . $page_nodes['o-nas']->id()],
];
$footer = [
  ['O nás', 'entity:node/' . $page_nodes['o-nas']->id()],
  ['Podporte nás', 'entity:node/' . $page_nodes['podporte-nas']->id()],
  ['Prihláška za člena', 'internal:/form/prihlaska'],
  ['Čísla časopisu', 'internal:/casopis/cisla'],
  ['Archív podujatí', 'internal:/podujatia/archiv'],
  ['Zdroje fotografií', 'entity:node/' . $page_nodes['fotografie']->id()],
];
foreach (['main' => $main, 'footer' => $footer] as $menu => $items) {
  foreach ($items as $w => [$title, $uri]) {
    MenuLinkContent::create(['menu_name' => $menu, 'title' => $title, 'link' => ['uri' => $uri], 'weight' => $w, 'expanded' => FALSE])->save();
  }
}

echo "Ukážkový obsah hotový.\n";
