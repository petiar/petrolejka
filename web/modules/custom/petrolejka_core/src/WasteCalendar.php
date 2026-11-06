<?php

namespace Drupal\petrolejka_core;

/**
 * Kalendár zvozu odpadu prevzatý z iCal feedu obce (kategória Odpad).
 *
 * Termíny sa sťahujú cronom a ukladajú do State API ako jednoduchý zoznam,
 * nie ako uzly: vlastníkom údajov je obec a pri každom stiahnutí sa zoznam
 * celý nahradí. Feed obce zapisuje celodenné termíny ako 00:00–23:59 v pásme
 * Europe/Berlin, preto ich tu prevádzame na čisté dátumy.
 */
class WasteCalendar {

  const STATE_KEY = 'petrolejka_core.odpad';

  const CACHE_TAG = 'petrolejka_odpad';

  const SOURCE_URL = 'https://www.liptovskypeter.sk/kalendar/iCalFeed?category=53&ongoing=1';

  const SOURCE_PAGE = 'https://www.liptovskypeter.sk/kalendar';

  const TIMEZONE = 'Europe/Bratislava';

  /**
   * Ako často sa feed obce sťahuje (sekundy).
   */
  const REFRESH_INTERVAL = 6 * 3600;

  /**
   * Druhy odpadu: kľúč => [štítok, regex na názov udalosti].
   */
  const TYPES = [
    'plast' => ['Plasty', '/plast/iu'],
    'papier' => ['Papier', '/papier/iu'],
    'sklo' => ['Sklo', '/skl[oa]/iu'],
    'bio' => ['Bioodpad', '/bio|kuchyn/iu'],
    'konare' => ['Konáre', '/konár|konar|zelen/iu'],
    'objemny' => ['Objemný odpad', '/objem/iu'],
    'zmesovy' => ['Zmesový odpad', '/zmes|komunál/iu'],
  ];

  /**
   * Uložené údaje: ['events' => [...], 'fetched' => int, 'day' => 'Y-m-d'].
   */
  public static function data(): array {
    return (\Drupal::state()->get(self::STATE_KEY) ?? []) + ['events' => [], 'fetched' => 0, 'day' => ''];
  }

  /**
   * Nadchádzajúce termíny (vrátane práve prebiehajúcich).
   *
   * @return array[]
   *   Termíny zoradené podľa dátumu, každý s kľúčmi uid, title, type,
   *   type_label, start, end (Y-m-d) a time (H:i alebo NULL).
   */
  public static function upcoming(?int $limit = NULL): array {
    $today = self::today();
    $events = array_values(array_filter(self::data()['events'], fn($e) => $e['end'] >= $today));
    return $limit ? array_slice($events, 0, $limit) : $events;
  }

  /**
   * Termíny od daného dňa (Y-m-d), napr. aj nedávne pre iCal feed.
   */
  public static function since(string $date): array {
    return array_values(array_filter(self::data()['events'], fn($e) => $e['end'] >= $date));
  }

  /**
   * Dnešný dátum v miestnom čase.
   */
  public static function today(): string {
    return (new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE)))->format('Y-m-d');
  }

  /**
   * Volá sa z cronu: stiahne feed, ak je čas, a o polnoci zneplatní cache.
   */
  public static function cron(): void {
    $data = self::data();
    if (\Drupal::time()->getRequestTime() - $data['fetched'] >= self::REFRESH_INTERVAL) {
      self::refresh();
      return;
    }
    // Zoznam „nadchádzajúcich“ sa mení s dátumom aj bez zmeny feedu.
    if ($data['day'] !== self::today()) {
      $data['day'] = self::today();
      \Drupal::state()->set(self::STATE_KEY, $data);
      \Drupal::service('cache_tags.invalidator')->invalidateTags([self::CACHE_TAG]);
    }
  }

  /**
   * Stiahne a uloží termíny. Pri chybe ostanú posledné známe údaje.
   *
   * @return int|null
   *   Počet termínov, alebo NULL pri chybe.
   */
  public static function refresh(): ?int {
    $logger = \Drupal::logger('petrolejka_odpad');
    try {
      $response = \Drupal::httpClient()->get(self::SOURCE_URL, [
        'timeout' => 15,
        'headers' => ['User-Agent' => 'petrolejka.sk (kalendar zvozu odpadu)'],
      ]);
      $events = self::parse((string) $response->getBody());
    }
    catch (\Throwable $e) {
      $logger->warning('Kalendár odpadu sa nepodarilo stiahnuť: @msg', ['@msg' => $e->getMessage()]);
      return NULL;
    }
    // Prázdny feed berieme ako chybu, aby výpadok obce nevymazal termíny.
    if (!$events) {
      $logger->warning('Feed odpadu neobsahuje žiadne termíny, ponechávam staré údaje.');
      $data = self::data();
      $data['fetched'] = \Drupal::time()->getRequestTime();
      \Drupal::state()->set(self::STATE_KEY, $data);
      return NULL;
    }
    \Drupal::state()->set(self::STATE_KEY, [
      'events' => $events,
      'fetched' => \Drupal::time()->getRequestTime(),
      'day' => self::today(),
    ]);
    \Drupal::service('cache_tags.invalidator')->invalidateTags([self::CACHE_TAG]);
    return count($events);
  }

  /**
   * Doplní k termínom texty na zobrazenie (dátum, „dnes“, „zajtra“…).
   */
  public static function present(array $events): array {
    $formatter = \Drupal::service('date.formatter');
    $tz = new \DateTimeZone(self::TIMEZONE);
    $today = self::today();
    $tomorrow = (new \DateTimeImmutable($today, $tz))->modify('+1 day')->format('Y-m-d');
    $ts = fn(string $date) => (new \DateTimeImmutable($date . ' 12:00', $tz))->getTimestamp();

    foreach ($events as &$event) {
      $start = $ts($event['start']);
      if ($event['end'] !== $event['start']) {
        $event['date_label'] = $formatter->format($start, 'custom', 'j. n.', self::TIMEZONE) . ' – ' . $formatter->format($ts($event['end']), 'custom', 'j. n.', self::TIMEZONE);
      }
      else {
        $event['date_label'] = $formatter->format($start, 'custom', 'l j. n.', self::TIMEZONE);
      }
      if ($event['time']) {
        $event['date_label'] .= ', ' . $event['time'];
      }
      $event['day'] = $formatter->format($start, 'custom', 'j', self::TIMEZONE);
      $event['weekday'] = $formatter->format($start, 'custom', 'D', self::TIMEZONE);
      // Preklad mesiacov nemá jednotné veľké písmená (November, december).
      $month = $formatter->format($start, 'custom', 'F Y', self::TIMEZONE);
      $event['month'] = mb_strtoupper(mb_substr($month, 0, 1)) . mb_substr($month, 1);
      $event['ongoing'] = $event['start'] < $today;
      $event['relative'] = match (TRUE) {
        $event['ongoing'] => 'prebieha',
        $event['start'] === $today => 'dnes',
        $event['start'] === $tomorrow => 'zajtra',
        default => NULL,
      };
    }
    return $events;
  }

  /**
   * Cache metadáta pre všetko, čo zobrazuje termíny.
   */
  public static function cache(): array {
    return ['tags' => [self::CACHE_TAG]];
  }

  /**
   * Spracuje iCal (RFC 5545) na zoznam termínov.
   */
  public static function parse(string $ics): array {
    // Spojenie zalomených riadkov (pokračovanie začína medzerou alebo tabom).
    $ics = preg_replace("/\r?\n[ \t]/", '', $ics);
    $lines = preg_split("/\r?\n/", $ics);

    $events = [];
    $current = NULL;
    foreach ($lines as $line) {
      if ($line === 'BEGIN:VEVENT') {
        $current = [];
        continue;
      }
      if ($line === 'END:VEVENT') {
        if ($event = self::buildEvent($current ?? [])) {
          $events[$event['uid']] = $event;
        }
        $current = NULL;
        continue;
      }
      if ($current === NULL || !preg_match('/^([A-Z-]+)((?:;[^:]*)?):(.*)$/', $line, $m)) {
        continue;
      }
      $params = [];
      foreach (array_filter(explode(';', $m[2])) as $param) {
        [$key, $value] = array_pad(explode('=', $param, 2), 2, '');
        $params[strtoupper($key)] = trim($value, '"');
      }
      $current[$m[1]] = ['value' => $m[3], 'params' => $params];
    }

    $events = array_values($events);
    usort($events, fn($a, $b) => [$a['start'], $a['time'] ?? ''] <=> [$b['start'], $b['time'] ?? '']);
    return $events;
  }

  /**
   * Zostaví jeden termín z vlastností VEVENT.
   */
  protected static function buildEvent(array $props): ?array {
    if (empty($props['DTSTART']) || empty($props['SUMMARY']['value'])) {
      return NULL;
    }
    if (strtoupper($props['STATUS']['value'] ?? '') === 'CANCELLED') {
      return NULL;
    }
    $start = self::parseDate($props['DTSTART']);
    $end = isset($props['DTEND']) ? self::parseDate($props['DTEND']) : NULL;
    if (!$start) {
      return NULL;
    }

    $all_day = $start['date_only'];
    $end_date = $start['dt'];
    if ($end) {
      if ($end['date_only']) {
        // DTEND pri celodennej udalosti je exkluzívny.
        $end_date = $end['dt']->modify('-1 day');
      }
      else {
        $end_date = $end['dt'];
        // 00:00 – 23:59 (alebo 00:00 – 00:00 ďalšieho dňa) = celý deň.
        $all_day = $all_day || ($start['dt']->format('H:i') === '00:00' && in_array($end['dt']->format('H:i'), ['23:59', '00:00'], TRUE));
        if ($end['dt']->format('H:i') === '00:00' && $end['dt'] > $start['dt']) {
          $end_date = $end['dt']->modify('-1 minute');
        }
      }
    }
    if ($end_date < $start['dt']) {
      $end_date = $start['dt'];
    }

    $title = self::unescape($props['SUMMARY']['value']);
    $type = 'ine';
    $type_label = 'Odpad';
    foreach (self::TYPES as $key => [$label, $regex]) {
      if (preg_match($regex, $title)) {
        $type = $key;
        $type_label = $label;
        break;
      }
    }

    return [
      'uid' => $props['UID']['value'] ?? md5($title . $start['dt']->format('c')),
      'title' => $title,
      'type' => $type,
      'type_label' => $type_label,
      'start' => $start['dt']->format('Y-m-d'),
      'end' => $end_date->format('Y-m-d'),
      'time' => $all_day ? NULL : $start['dt']->format('H:i'),
    ];
  }

  /**
   * Prevedie DTSTART/DTEND na dátum v miestnom čase.
   */
  protected static function parseDate(array $prop): ?array {
    $value = trim($prop['value']);
    $local = new \DateTimeZone(self::TIMEZONE);
    if (($prop['params']['VALUE'] ?? '') === 'DATE' || preg_match('/^\d{8}$/', $value)) {
      $dt = \DateTimeImmutable::createFromFormat('!Ymd', substr($value, 0, 8), $local);
      return $dt ? ['dt' => $dt, 'date_only' => TRUE] : NULL;
    }
    try {
      $tz = str_ends_with($value, 'Z') ? new \DateTimeZone('UTC') : new \DateTimeZone($prop['params']['TZID'] ?? self::TIMEZONE);
    }
    catch (\Exception) {
      $tz = $local;
    }
    $dt = \DateTimeImmutable::createFromFormat('Ymd\THis', rtrim($value, 'Z'), $tz);
    return $dt ? ['dt' => $dt->setTimezone($local), 'date_only' => FALSE] : NULL;
  }

  /**
   * Odstráni escapovanie textu podľa RFC 5545.
   */
  protected static function unescape(string $text): string {
    return trim(strtr($text, ['\\n' => ' ', '\\N' => ' ', '\\,' => ',', '\\;' => ';', '\\\\' => '\\']));
  }

}
