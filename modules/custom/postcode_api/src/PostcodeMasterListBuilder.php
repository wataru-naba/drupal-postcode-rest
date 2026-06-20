<?php

namespace Drupal\postcode_api;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for Postcode Master entities.
 */
class PostcodeMasterListBuilder extends EntityListBuilder {

  /**
   * Constructs a new PostcodeMasterListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected DateFormatterInterface $dateFormatter,
  ) {
    parent::__construct($entity_type, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_field.manager'),
      $container->get('date.formatter'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('ID');
    $header['zipcode'] = $this->t('Postcode');

    if ($this->hasFieldDefinition('created')) {
      $header['created'] = $this->t('Created');
    }

    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    assert($entity instanceof PostcodeMasterInterface);

    $row['id'] = $entity->id();
    $row['zipcode'] = $entity->getZipcode();

    if ($this->hasFieldDefinition('created')) {
      $row['created'] = $this->formatCreated($entity);
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * Determines whether the entity type has the given field definition.
   *
   * @param string $field_name
   *   The field name.
   *
   * @return bool
   *   TRUE if the field exists.
   */
  protected function hasFieldDefinition(string $field_name): bool {
    $definitions = $this->entityFieldManager->getBaseFieldDefinitions($this->entityTypeId);
    return isset($definitions[$field_name]);
  }

  /**
   * Formats the created timestamp when the field exists.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The formatted created timestamp, or an empty string.
   */
  protected function formatCreated(EntityInterface $entity): string {
    if (!$entity->hasField('created') || $entity->get('created')->isEmpty()) {
      return '';
    }

    return $this->dateFormatter->format((int) $entity->get('created')->value, 'short');
  }

}
