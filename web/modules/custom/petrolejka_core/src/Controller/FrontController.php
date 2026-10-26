<?php

namespace Drupal\petrolejka_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

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

    $article_ids = $query('article')->sort('sticky', 'DESC')->sort('created', 'DESC')->range(0, 7)->execute();
    $articles = $storage->loadMultiple($article_ids);
    $lead = array_shift($articles);

    $now = (new DrupalDateTime('now', DateTimeItemInterface::STORAGE_TIMEZONE))->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $event_ids = $query('podujatie')->condition('field_datum.end_value', $now, '>=')->sort('field_datum.value')->range(0, 4)->execute();
    $news_ids = $query('sprava')->sort('created', 'DESC')->range(0, 5)->execute();

    // Jednotlivé render polia, aby sa v šablóne dalo iterovať a počítať.
    $view = fn(array $nodes) => array_values(array_map(fn($node) => $builder->view($node, 'teaser'), $nodes));

    return [
      '#theme' => 'petrolejka_front',
      '#lead' => $lead ? $builder->view($lead, 'lead') : NULL,
      '#articles' => $view($articles),
      '#events' => $view($storage->loadMultiple($event_ids)),
      '#news' => $view($storage->loadMultiple($news_ids)),
      '#cache' => ['tags' => ['node_list']],
    ];
  }

}
