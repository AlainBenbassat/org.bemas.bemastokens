<?php

class CRM_Bemastokens_Helper {
  public static function getEventTokenList() {
    return [
      'evalformurl' => 'Link naar evaluatieformulier',
    ];
  }

  public static function replaceEventTokenValues(\Civi\Token\TokenRow $tokenRow) {
    $eventId = $tokenRow->context['eventId'];
    $event = self::getEvent($eventId);
    if (!empty($event['Evaluatie_evenement.Formulier_voor_deelnemers'])) {
      $tokenRow->tokens('event','evalformurl', strip_tags($event['Evaluatie_evenement.Formulier_voor_deelnemers']));
    }
  }

  private static function getEvent($eventId) {
    $event = \Civi\Api4\Event::get(FALSE)
      ->addSelect('*', 'custom.*')
      ->addWhere('id', '=', $eventId)
      ->execute()
      ->first();

    return $event;
  }
}
