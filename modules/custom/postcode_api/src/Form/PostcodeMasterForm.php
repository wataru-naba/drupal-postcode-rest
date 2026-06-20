<?php

namespace Drupal\postcode_api\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Postcode Master add and edit forms.
 */
class PostcodeMasterForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    if (!$this->entity->isNew() && isset($form['zipcode']['widget'][0]['value'])) {
      $form['zipcode']['widget'][0]['value']['#attributes']['readonly'] = 'readonly';
      $form['zipcode']['widget'][0]['value']['#description'] = $this->t('The postcode is the entity ID and cannot be changed after creation.');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $zipcode = $this->getSubmittedFieldValue($form_state, 'zipcode');
    if ($zipcode === '') {
      $form_state->setErrorByName('zipcode', $this->t('Postcode is required.'));
      return;
    }

    if (!preg_match('/^\d{7}$/', $zipcode)) {
      $form_state->setErrorByName('zipcode', $this->t('Postcode must be a 7 digit number.'));
    }

    if (!$this->entity->isNew() && $zipcode !== (string) $this->entity->id()) {
      $form_state->setErrorByName('zipcode', $this->t('Postcode cannot be changed after creation.'));
    }

    if ($this->entity->isNew()) {
      $existing = $this->entityTypeManager
        ->getStorage($this->entity->getEntityTypeId())
        ->load($zipcode);

      if ($existing !== NULL) {
        $form_state->setErrorByName('zipcode', $this->t('Postcode %postcode already exists.', [
          '%postcode' => $zipcode,
        ]));
      }
    }

    $this->validateRequiredStringField($form_state, 'prefecture', $this->t('Prefecture is required.'));
    $this->validateRequiredStringField($form_state, 'city', $this->t('City is required.'));
    $this->validateRequiredStringField($form_state, 'town', $this->t('Town is required.'));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->getEntity();
    $status = parent::save($form, $form_state);

    $this->messenger()->addStatus($status === SAVED_NEW
      ? $this->t('Created Postcode Master %label.', ['%label' => $entity->label()])
      : $this->t('Updated Postcode Master %label.', ['%label' => $entity->label()])
    );

    $form_state->setRedirectUrl($entity->toUrl('collection'));
    return $status;
  }

  /**
   * Validates a required string field when it exists on the entity.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field name.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $message
   *   The validation message.
   */
  protected function validateRequiredStringField(FormStateInterface $form_state, string $field_name, $message): void {
    if (!$this->entity->hasField($field_name)) {
      return;
    }

    if ($this->getSubmittedFieldValue($form_state, $field_name) === '') {
      $form_state->setErrorByName($field_name, $message);
    }
  }

  /**
   * Gets the submitted scalar value from a field widget.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $field_name
   *   The field name.
   *
   * @return string
   *   The submitted value.
   */
  protected function getSubmittedFieldValue(FormStateInterface $form_state, string $field_name): string {
    $value = $form_state->getValue($field_name);

    if (is_array($value)) {
      $value = $value[0]['value'] ?? '';
    }

    return trim((string) $value);
  }

}
