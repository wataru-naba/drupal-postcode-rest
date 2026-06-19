<?php

namespace Drupal\postcode_api;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Provides an interface for Postcode Master entities.
 */
interface PostcodeMasterInterface extends ContentEntityInterface {

  /**
   * Gets the zipcode.
   *
   * @return string
   *   The zipcode.
   */
  public function getZipcode(): string;

  /**
   * Sets the zipcode.
   *
   * @param string $zipcode
   *   The zipcode.
   *
   * @return $this
   */
  public function setZipcode(string $zipcode): static;

  /**
   * Gets the prefecture.
   *
   * @return string
   *   The prefecture.
   */
  public function getPrefecture(): string;

  /**
   * Sets the prefecture.
   *
   * @param string $prefecture
   *   The prefecture.
   *
   * @return $this
   */
  public function setPrefecture(string $prefecture): static;

  /**
   * Gets the city.
   *
   * @return string
   *   The city.
   */
  public function getCity(): string;

  /**
   * Sets the city.
   *
   * @param string $city
   *   The city.
   *
   * @return $this
   */
  public function setCity(string $city): static;

  /**
   * Gets the town.
   *
   * @return string
   *   The town.
   */
  public function getTown(): string;

  /**
   * Sets the town.
   *
   * @param string $town
   *   The town.
   *
   * @return $this
   */
  public function setTown(string $town): static;

}
