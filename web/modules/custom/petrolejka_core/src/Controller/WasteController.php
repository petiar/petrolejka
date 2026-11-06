<?php

namespace Drupal\petrolejka_core\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\petrolejka_core\WasteCalendar;

/**
 * Stránka /odpad a iCal feed so zvozom odpadu.
 */
class WasteController extends ControllerBase {

  /**
   * Stránka so všetkými nadchádzajúcimi termínmi po mesiacoch.
   */
  public function page(): array {
    $months = [];
    foreach (WasteCalendar::present(WasteCalendar::upcoming()) as $event) {
      $months[$event['ongoing'] ? 'Prebieha' : $event['month']][] = $event;
    }
    $feed = Url::fromRoute('petrolejka_core.odpad_feed', [], ['absolute' => TRUE])->toString();
    $data = WasteCalendar::data();

    return [
      '#theme' => 'petrolejka_odpad',
      '#months' => $months,
      '#feed_url' => $feed,
      '#webcal_url' => preg_replace('#^https?://#', 'webcal://', $feed),
      '#source_url' => WasteCalendar::SOURCE_PAGE,
      '#updated' => $data['fetched'] ? \Drupal::service('date.formatter')->format($data['fetched'], 'custom', 'j. n. Y, H:i', WasteCalendar::TIMEZONE) : NULL,
      '#cache' => WasteCalendar::cache(),
    ];
  }

  /**
   * ICal feed s celodennými termínmi a pripomienkou o 18:00 deň vopred.
   */
  public function feed(): CacheableResponse {
    $since = (new \DateTimeImmutable(WasteCalendar::today()))->modify('-30 days')->format('Y-m-d');
    $stamp = gmdate('Ymd\THis\Z', WasteCalendar::data()['fetched'] ?: \Drupal::time()->getRequestTime());
    $escape = fn(string $text) => strtr($text, ['\\' => '\\\\', ';' => '\;', ',' => '\,', "\n" => '\n']);

    $lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//OZ Petrolejka//Zvoz odpadu//SK',
      'CALSCALE:GREGORIAN',
      'METHOD:PUBLISH',
      'X-WR-CALNAME:Zvoz odpadu – Liptovský Peter',
      'X-WR-CALDESC:' . $escape('Termíny zvozu odpadu podľa kalendára obce Liptovský Peter (' . WasteCalendar::SOURCE_PAGE . ').'),
      'X-WR-TIMEZONE:' . WasteCalendar::TIMEZONE,
      'REFRESH-INTERVAL;VALUE=DURATION:PT12H',
      'X-PUBLISHED-TTL:PT12H',
    ];
    foreach (WasteCalendar::since($since) as $event) {
      $start = str_replace('-', '', $event['start']);
      $end = (new \DateTimeImmutable($event['end']))->modify('+1 day')->format('Ymd');
      $lines[] = 'BEGIN:VEVENT';
      $lines[] = 'UID:odpad-' . $escape($event['uid']);
      $lines[] = 'DTSTAMP:' . $stamp;
      $lines[] = 'DTSTART;VALUE=DATE:' . $start;
      $lines[] = 'DTEND;VALUE=DATE:' . $end;
      $lines[] = 'SUMMARY:' . $escape($event['title']);
      $lines[] = 'URL:' . WasteCalendar::SOURCE_PAGE;
      $lines[] = 'TRANSP:TRANSPARENT';
      $lines[] = 'BEGIN:VALARM';
      $lines[] = 'ACTION:DISPLAY';
      $lines[] = 'DESCRIPTION:' . $escape('Zajtra: ' . $event['title']);
      $lines[] = 'TRIGGER:-PT6H';
      $lines[] = 'END:VALARM';
      $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';

    // Riadky dlhšie ako 75 bajtov sa podľa RFC 5545 zalamujú.
    $folded = array_map(function (string $line): string {
      $out = '';
      while (strlen($line) > 75) {
        $cut = 75;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
          $cut--;
        }
        $out .= substr($line, 0, $cut) . "\r\n ";
        $line = substr($line, $cut);
      }
      return $out . $line;
    }, $lines);

    $response = new CacheableResponse(implode("\r\n", $folded) . "\r\n", 200, [
      'Content-Type' => 'text/calendar; charset=utf-8',
      'Content-Disposition' => 'inline; filename="zvoz-odpadu.ics"',
    ]);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray(['#cache' => WasteCalendar::cache()]));
    return $response;
  }

}
