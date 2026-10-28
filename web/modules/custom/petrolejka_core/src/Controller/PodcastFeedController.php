<?php

namespace Drupal\petrolejka_core\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\petrolejka_core\PodcastSettings;

/**
 * RSS feed podcastu pre Apple Podcasts, Spotify a ďalšie aplikácie.
 */
class PodcastFeedController extends ControllerBase {

  /**
   * Vygeneruje RSS 2.0 s menným priestorom iTunes.
   */
  public function feed(): CacheableResponse {
    $settings = PodcastSettings::get();
    $site_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    $explicit = $settings['explicit'] ? 'true' : 'false';
    $cover = petrolejka_core_podcast_cover_url();

    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'epizoda')
      ->condition('status', 1)
      ->sort('created', 'DESC')
      ->execute();
    /** @var \Drupal\node\NodeInterface[] $episodes */
    $episodes = $storage->loadMultiple($ids);

    $xml = new \XMLWriter();
    $xml->openMemory();
    $xml->setIndent(TRUE);
    $xml->startDocument('1.0', 'UTF-8');
    $xml->startElement('rss');
    $xml->writeAttribute('version', '2.0');
    $xml->writeAttribute('xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
    $xml->writeAttribute('xmlns:podcast', 'https://podcastindex.org/namespace/1.0');
    $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
    $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');

    $xml->startElement('channel');
    $xml->writeElement('title', $settings['title']);
    $xml->writeElement('link', Url::fromRoute('view.podcast.page_1', [], ['absolute' => TRUE])->toString());
    $xml->startElement('atom:link');
    $xml->writeAttribute('href', Url::fromRoute('petrolejka_core.podcast_feed', [], ['absolute' => TRUE])->toString());
    $xml->writeAttribute('rel', 'self');
    $xml->writeAttribute('type', 'application/rss+xml');
    $xml->endElement();
    $xml->writeElement('language', 'sk');
    $xml->writeElement('copyright', '© ' . date('Y') . ' ' . $settings['author']);
    $xml->writeElement('description', $settings['description']);
    $xml->writeElement('itunes:summary', $settings['description']);
    $xml->writeElement('itunes:author', $settings['author']);
    $xml->startElement('itunes:owner');
    $xml->writeElement('itunes:name', $settings['author']);
    $xml->writeElement('itunes:email', $settings['owner_email']);
    $xml->endElement();
    $xml->startElement('itunes:image');
    $xml->writeAttribute('href', $cover);
    $xml->endElement();
    $xml->startElement('image');
    $xml->writeElement('url', $cover);
    $xml->writeElement('title', $settings['title']);
    $xml->writeElement('link', $site_url);
    $xml->endElement();
    $xml->startElement('itunes:category');
    $xml->writeAttribute('text', $settings['category']);
    $xml->endElement();
    $xml->writeElement('itunes:explicit', $explicit);
    $xml->writeElement('itunes:type', 'episodic');
    $xml->writeElement('podcast:locked', 'no');

    $cache = (new CacheableMetadata())->setCacheTags(['node_list', PodcastSettings::STATE_KEY]);
    foreach ($episodes as $episode) {
      if ($this->writeItem($xml, $episode, $explicit)) {
        $cache->addCacheableDependency($episode);
      }
    }

    $xml->endElement();
    $xml->endElement();
    $xml->endDocument();

    $response = new CacheableResponse($xml->outputMemory(), 200, ['Content-Type' => 'application/rss+xml; charset=utf-8']);
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Zapíše jednu epizódu. Epizódy bez zvukového súboru preskočí.
   */
  protected function writeItem(\XMLWriter $xml, NodeInterface $episode, string $explicit): bool {
    /** @var \Drupal\file\FileInterface|null $audio */
    $audio = $episode->get('field_audio')->entity;
    if (!$audio) {
      return FALSE;
    }
    $url_generator = \Drupal::service('file_url_generator');
    $link = $episode->toUrl('canonical', ['absolute' => TRUE])->toString();

    $xml->startElement('item');
    $xml->writeElement('title', $episode->label());
    $xml->writeElement('link', $link);
    $xml->startElement('guid');
    $xml->writeAttribute('isPermaLink', 'false');
    $xml->text($episode->uuid());
    $xml->endElement();
    $xml->writeElement('pubDate', date(DATE_RSS, $episode->getCreatedTime()));
    $perex = (string) $episode->get('field_perex')->value;
    $xml->writeElement('description', $perex);
    $xml->writeElement('itunes:summary', $perex);
    $body = (string) $episode->get('body')->processed;
    if ($body !== '') {
      $xml->startElement('content:encoded');
      $xml->writeCdata($body);
      $xml->endElement();
    }
    $xml->startElement('enclosure');
    $xml->writeAttribute('url', $url_generator->generateAbsoluteString($audio->getFileUri()));
    $xml->writeAttribute('length', (string) $audio->getSize());
    $xml->writeAttribute('type', $audio->getMimeType());
    $xml->endElement();
    if ($seconds = (int) $episode->get('field_trvanie')->value) {
      $xml->writeElement('itunes:duration', (string) $seconds);
    }
    if ($number = (int) $episode->get('field_cislo_epizody')->value) {
      $xml->writeElement('itunes:episode', (string) $number);
    }
    if ($image = $episode->get('field_image')->entity) {
      $xml->startElement('itunes:image');
      $xml->writeAttribute('href', $url_generator->generateAbsoluteString($image->getFileUri()));
      $xml->endElement();
    }
    $xml->writeElement('itunes:explicit', $explicit);
    $xml->writeElement('itunes:episodeType', 'full');
    $xml->endElement();
    return TRUE;
  }

}
