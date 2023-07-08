<?php

/**
 * @file
 * Contains CommandWrapper.
 *
 * Attach 'calledMethod' parameter to each command.
 *
 * @see GuzzleHttp\Command\Event\PreparedEvent
 */

namespace Drupalway\NovaPoshta\Subscriber;

use GuzzleHttp\Command\Event\PreparedEvent;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Stream\Stream;

/**
 * Add extra params.
 *
 * React last on prepared.
 */
class CommandWrapper implements SubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public function getEvents() {
    return ['prepared' => ['onPrepared']];
  }

  /**
   * Add extra parameter 'calledMethod'.
   *
   * React on prepared event, before send.
   */
  public function onPrepared(PreparedEvent $event) {
    $command = $event->getCommand();
    $request = $event->getRequest();
    $body    = (array) json_decode((string) $request->getBody());

    $body['calledMethod'] = $command->getName();

    $event->getRequest()->setBody(Stream::factory(json_encode($body)));
  }

}
