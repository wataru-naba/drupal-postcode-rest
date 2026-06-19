<?php

namespace Drupal\postcode_api;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Controls access for Postcode Master entities.
 */
class PostcodeMasterAccessControlHandler extends EntityAccessControlHandler {

  /**
   * The administer permission for Postcode Master entities.
   */
  private const ADMIN_PERMISSION = 'administer postcode master';

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, self::ADMIN_PERMISSION);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, self::ADMIN_PERMISSION);
  }

}
