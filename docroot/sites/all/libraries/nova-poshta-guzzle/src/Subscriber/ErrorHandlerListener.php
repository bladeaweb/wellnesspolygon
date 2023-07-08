<?php

/**
 * @file
 * Contains ErrorHandlerListener.
 *
 * Handle & Wrap Nova Poshta request errors.
 */

namespace Drupalway\NovaPoshta\Subscriber;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Command\Guzzle\DescriptionInterface;
use GuzzleHttp\Command\Guzzle\Operation;
use Drupalway\NovaPoshta\Exception\NpErrorException;

/**
 * Process request, does first.
 */
class ErrorHandlerListener implements SubscriberInterface {

  /* @var DescriptionInterface */
  private $description;

  /* @var Operation */
  private $operation;

  /**
   * {@inheritdoc}
   */
  public function getEvents() {
    return ['process' => ['onProcess', 'first']];
  }

  /**
   * Set description.
   *
   * Used to get later operation params.
   *
   * @param \GuzzleHttp\Command\Guzzle\DescriptionInterface $description
   *   ServiceDescription.
   */
  public function __construct(
    DescriptionInterface $description
  ) {
    $this->description = $description;
  }

  /**
   * Callback for process event.
   */
  public function onProcess(ProcessEvent $event) {
    $response  = $event->getResponse();
    // var_dump((string) $event->getRequest());
    // var_dump((string) $response);
    if (!$response) {
      return;
    }
    try {
      $json = $response->json();
    }
    catch (\RuntimeException $e) {
      return;
    }

    if (NULL === $response || $json['success']) {
      return;
    }

    $command = $event->getCommand();
    $message = '';

    $this->operation = $this->description->getOperation($command->getName());

    // string,
    // simple array,
    // assoc array.
    $errors = $json['errors'];

    $category = $this->operation->getParam('modelName')->getDefault();
    $method   = $this->operation->getName();
    $suffix   = " on $category operation { $method }";

    if (is_string($errors)) {
      $message = 'Common error: "' . $errors . '"' . $suffix;
    }
    elseif (is_array($errors)) {
      $keys     = array_keys($errors);
      $message  = $errors[$keys[0]];
      $property = $keys[0];
      $alias    = $this::getParamBySentAs($keys[0]);

      if ($alias) {
        $message = str_replace($property, $alias, $message);
      }

      $message = 'Error: "' . $message . '"' . $suffix;
    }

    $e = new NpErrorException($message, $event->getTransaction());
    $e->setCategory($category);
    $e->setVerboseMessage($message);

    throw $e;
  }

  /**
   * Get parameter name by 'sentAs' parameter alias.
   *
   * @param string $sent_as
   *   Search string.
   *
   * @return string|bool
   *   Property name or FALSE.
   */
  private function getParamBySentAs($sent_as = '') {
    $param = $this->operation->getParam('filters');
    foreach ($param->getProperties() as $property) {
      if ($sent_as === $property->getSentAs()) {
        return $property->getName();
      }
    }
    return FALSE;
  }

}
