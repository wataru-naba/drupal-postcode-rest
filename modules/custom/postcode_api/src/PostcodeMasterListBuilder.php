<?php

namespace Drupal\postcode_api;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for Postcode Master entities.
 */
class PostcodeMasterListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['zipcode'] = $this->t('Zipcode');
    $header['prefecture'] = $this->t('Prefecture');
    $header['city'] = $this->t('City');
    $header['town'] = $this->t('Town');

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    assert($entity instanceof PostcodeMasterInterface);

    $row['zipcode'] = $entity->getZipcode();
    $row['prefecture'] = $entity->getPrefecture();
    $row['city'] = $entity->getCity();
    $row['town'] = $entity->getTown();

    return $row + parent::buildRow($entity);
  }

}
