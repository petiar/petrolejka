<?php

namespace Drupal\petrolejka_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\petrolejka_core\WasteCalendar;

/**
 * Titulná stránka: hlavný článok, ďalšie články, podujatia a správy.
 */
class FrontController extends ControllerBase {

  /**
   * Zostaví titulnú stránku.
   */
  public function build(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $builder = $this->entityTypeManager()->getViewBuilder('node');

    $query = fn(string $type) => $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', 1)
      ->condition('type', $type);

    // Články z rubriky „Z obce“ sú v samostatnom zozname Dianie v obci.
    $z_obce = array_keys($this->entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'rubrika', 'name' => 'Z obce']));
    $articles_query = $query('article');
    if ($z_obce) {
      $articles_query->condition($articles_query->orConditionGroup()
        ->notExists('field_rubrika')
        ->condition('field_rubrika', $z_obce, 'NOT IN'));
    }
    $article_ids = $articles_query->sort('sticky', 'DESC')->sort('created', 'DESC')->range(0, 7)->execute();
    $articles = $storage->loadMultiple($article_ids);
    $lead = array_shift($articles);

    $now = (new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE))->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $event_ids = $query('podujatie')->condition('field_datum.end_value', $now, '>=')->sort('field_datum.value')->range(0, 4)->execute();
    $news_ids = $z_obce ? $query('article')->condition('field_rubrika', $z_obce, 'IN')->sort('created', 'DESC')->range(0, 5)->execute() : [];

    // Jednotlivé render polia, aby sa v šablóne dalo iterovať a počítať.
    $view = fn(array $nodes) => array_values(array_map(fn($node) => $builder->view($node, 'teaser'), $nodes));

    return [
      '#theme' => 'petrolejka_front',
      '#lead' => $lead ? $builder->view($lead, 'lead') : NULL,
      '#articles' => $view($articles),
      '#events' => $view($storage->loadMultiple($event_ids)),
      '#news' => array_values(array_map(fn($node) => $builder->view($node, 'news'), $storage->loadMultiple($news_ids))),
      '#waste' => WasteCalendar::present(WasteCalendar::upcoming(3)),
      '#cache' => ['tags' => ['node_list', 'taxonomy_term_list', WasteCalendar::CACHE_TAG]],
    ];
  }

}
