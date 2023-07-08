<?php

/**
 * @file
 * Contains NpErrorException.
 */

namespace Drupalway\NovaPoshta\Exception;

use GuzzleHttp\Command\Exception\CommandException;

/**
 * Adds extra methods to make logs pretty.
 */
class NpErrorException extends CommandException {
  /* @var string Category code for the error */
  protected $category;
  /* @var string Extended error message containing more technical information */
  protected $verboseMessage;

  public function setCategory($category) {
    $this->category = $category;
  }

  public function getCategory() {
    return $this->category;
  }

  public function setVerboseMessage($message) {
    $this->verboseMessage = $message;
  }

  public function getVerboseMessage() {
    return $this->verboseMessage;
  }

}
