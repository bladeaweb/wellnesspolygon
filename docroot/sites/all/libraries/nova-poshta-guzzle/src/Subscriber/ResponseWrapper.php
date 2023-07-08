<?php

/**
 * @file
 * Contains ResponseWrapper.
 */

namespace Drupalway\NovaPoshta\Subscriber;

use GuzzleHttp\Command\Event\ProcessEvent;
use GuzzleHttp\Event\SubscriberInterface;

/**
 * Return single array() instead array('data' => array()).
 */
class ResponseWrapper implements SubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function getEvents() {
    // Fire the event last, after signing.
    return array('process' => array('onProcess', 'last'));
  }

  /**
   * Callback for process event.
   */
  public function onProcess(ProcessEvent $event) {
    $result = $event->getResult();
    !isset($result['data']) ?: $event->setResult($result['data']);
  }

}
