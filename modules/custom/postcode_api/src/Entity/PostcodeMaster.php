<?php

namespace Drupal\postcode_api\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\postcode_api\Form\PostcodeMasterDeleteForm;
use Drupal\postcode_api\Form\PostcodeMasterForm;
use Drupal\postcode_api\PostcodeMasterAccessControlHandler;
use Drupal\postcode_api\PostcodeMasterInterface;
use Drupal\postcode_api\PostcodeMasterListBuilder;

/**
 * Defines the Postcode Master content entity.
 */
#[ContentEntityType(
  id: 'postcode_master',
  label: new TranslatableMarkup('Postcode Master'),
  label_collection: new TranslatableMarkup('Postcode Masters'),
  label_singular: new TranslatableMarkup('postcode master'),
  label_plural: new TranslatableMarkup('postcode masters'),
  handlers: [
    'list_builder' => PostcodeMasterListBuilder::class,
    'access' => PostcodeMasterAccessControlHandler::class,
    'form' => [
      'add' => PostcodeMasterForm::class,
      'edit' => PostcodeMasterForm::class,
      'delete' => PostcodeMasterDeleteForm::class,
    ],
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  base_table: 'postcode_master',
  admin_permission: 'administer postcode master',
  entity_keys: [
    'id' => 'zipcode',
    'label' => 'zipcode',
  ],
  links: [
    'collection' => '/admin/content/postcode-master',
    'add-form' => '/admin/content/postcode-master/add',
    'edit-form' => '/admin/content/postcode-master/{postcode_master}/edit',
    'delete-form' => '/admin/content/postcode-master/{postcode_master}/delete',
  ],
)]
class PostcodeMaster extends ContentEntityBase implements PostcodeMasterInterface {

  /**
   * {@inheritdoc}
   */
  public function getZipcode(): string {
    return (string) $this->get('zipcode')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setZipcode(string $zipcode): static {
    $this->set('zipcode', $zipcode);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrefecture(): string {
    return (string) $this->get('prefecture')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPrefecture(string $prefecture): static {
    $this->set('prefecture', $prefecture);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCity(): string {
    return (string) $this->get('city')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCity(string $city): static {
    $this->set('city', $city);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTown(): string {
    return (string) $this->get('town')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTown(string $town): static {
    $this->set('town', $town);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['zipcode'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Zipcode'))
      ->setDescription(t('The seven digit postcode without a hyphen.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 7)
      ->addConstraint('Length', ['max' => 7])
      ->addConstraint('Regex', [
        'pattern' => '/^\d{7}$/',
        'message' => t('Zipcode must be a 7 digit number.'),
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['prefecture'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Prefecture'))
      ->setDescription(t('The prefecture name.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 50)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['city'] = BaseFieldDefinition::create('string')
      ->setLabel(t('City'))
      ->setDescription(t('The city, ward, town, or village name.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 100)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 20,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['town'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Town'))
      ->setDescription(t('The town area name.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => 30,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 30,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
