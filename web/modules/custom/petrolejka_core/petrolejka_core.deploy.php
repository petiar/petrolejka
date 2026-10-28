<?php

/**
 * @file
 * Jednorazové úpravy obsahu pri deployi (drush deploy).
 */

use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Pridá „Podcast“ do hlavného menu za „Podujatia“.
 */
function petrolejka_core_deploy_podcast_menu(): string {
  $storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  if ($storage->loadByProperties(['menu_name' => 'main', 'link__uri' => 'internal:/podcast'])) {
    return 'Odkaz Podcast už v menu je.';
  }
  $links = $storage->loadByProperties(['menu_name' => 'main']);
  uasort($links, fn($a, $b) => $a->getWeight() <=> $b->getWeight());
  $weight = 0;
  $podcast_weight = NULL;
  foreach ($links as $link) {
    $link->set('weight', $weight++)->save();
    if ($link->getTitle() === 'Podujatia') {
      $podcast_weight = $weight++;
    }
  }
  MenuLinkContent::create([
    'menu_name' => 'main',
    'title' => 'Podcast',
    'link' => ['uri' => 'internal:/podcast'],
    'weight' => $podcast_weight ?? $weight,
  ])->save();
  return 'Do hlavného menu pribudol Podcast.';
}
