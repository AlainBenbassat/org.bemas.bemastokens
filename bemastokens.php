<?php

require_once 'bemastokens.civix.php';
use CRM_Bemastokens_ExtensionUtil as E;

use \Symfony\Component\DependencyInjection\ContainerBuilder;
use \Symfony\Component\Config\Resource\FileResource;

function bemastokens_civicrm_container(ContainerBuilder $container) {
  $container->addResource(new FileResource(__FILE__));
  $container->findDefinition('dispatcher')->addMethodCall('addListener', ['civi.token.list', 'bemastokens_register_tokens'])->setPublic(TRUE);
  $container->findDefinition('dispatcher')->addMethodCall('addListener', ['civi.token.eval', 'bemastokens_evaluate_tokens'])->setPublic(TRUE);
}

function bemastokens_register_tokens(\Civi\Token\Event\TokenRegisterEvent $e) {
  $tokenList = CRM_Bemastokens_Helper::getEventTokenList();
  foreach ($tokenList as $token => $tokenDescription) {
    $e->entity('event')->register($token, $tokenDescription);
  }
}

function bemastokens_evaluate_tokens(\Civi\Token\Event\TokenValueEvent $e) {
  foreach ($e->getRows() as $tokenRow) {
    if (!empty($tokenRow->context['eventId'])) {
      CRM_Bemastokens_Helper::replaceEventTokenValues($tokenRow);
    }
  }
}

function bemastokens_civicrm_tokens(&$tokens) {
  $tokens['bemastokens'] = array(
    'bemastokens.werkgever_volledig_adres' => 'werkgever: 0. volledig adres (straat, postcode, plaats...)',
    'bemastokens.werkgever_straat' => 'werkgever: 1. straat en nummer',
    'bemastokens.werkgever_adreslijn1' => 'werkgever: 2. extra adreslijn1',
    'bemastokens.werkgever_adreslijn2' => 'werkgever: 3. extra adreslijn2',
    'bemastokens.werkgever_postcode' => 'werkgever: 4. postcode',
    'bemastokens.werkgever_plaats' => 'werkgever: 5. plaats',
    'bemastokens.werkgever_land' => 'werkgever: 6. land',
    'bemastokens.werkgever_telefoon' => 'werkgever: telefoonnummer',
    'bemastokens.werkgever_email' => 'werkgever: e-mail',
    'bemastokens.werkgever_website' => 'werkgever: website',
    'bemastokens.werkgever_omschrijving_nl' => 'werkgever: NL omschrijving bedrijfsactiviteit',
    'bemastokens.werkgever_omschrijving_fr' => 'werkgever: FR omschrijving bedrijfsactiviteit',
    'bemastokens.werkgever_omschrijving_en' => 'werkgever: EN omschrijving bedrijfsactiviteit',
    'bemastokens.werkgever_lidcontacten' => 'werkgever: lidcontacten',
    'bemastokens.lid_sinds' => 'lid: sinds',
    'bemastokens.lid_type' => 'lid: type lidmaatschap',
    'bemastokens.lid_tarief' => 'lid: tarief lidmaatschap',
  );
}

function bemastokens_civicrm_tokenValues(&$values, $cids, $job = null, $tokens = array(), $context = null) {
  // sometimes $cids is not an array
  if (!is_array($cids)) {
    $cids = array($cids);
  }

  // make sure we have at least 1 contact id
  // and the token profilepagelink (which is sometimes an array key, sometime an array value)
  if (array_key_exists('bemastokens', $tokens)) {
    foreach ($cids as $cid) {
      // get the contact details
      $contact = bemastokens_getContactDetails($cid);

      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_volledig_adres', $contact->full_address);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_straat', $contact->street_address);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_adreslijn1', $contact->supplemental_address_1);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_adreslijn2', $contact->supplemental_address_2);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_postcode', $contact->postal_code);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_plaats', $contact->city);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_land', $contact->country_name);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_telefoon', $contact->phone);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_website', $contact->url);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_email', $contact->email);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_omschrijving_nl', $contact->description_nl);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_omschrijving_fr', $contact->description_fr);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_omschrijving_en', $contact->description_en);

      bemastokens_matchTokenWithField($cid, $tokens, $values, 'lid_sinds', $contact->membership_join_date);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'lid_type', $contact->membership_type);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'lid_tarief', $contact->membership_fee);

      $membercontacts = bemastokens_getMemberContacts($contact->employer_id);
      bemastokens_matchTokenWithField($cid, $tokens, $values, 'werkgever_lidcontacten', $membercontacts);
    }
  }
}

function bemastokens_matchTokenWithField($cid, $tokens, &$values, $token, $val) {
  if (in_array($token, $tokens['bemastokens']) || array_key_exists($token, $tokens['bemastokens'])) {
    $values[$cid]['bemastokens.' . $token] = $val;
  }
}

function bemastokens_getContactDetails($id) {
  $sql = "
    select
      c.employer_id
      , a.street_address
      , a.supplemental_address_1
      , a.supplemental_address_2
      , a.postal_code
      , a.city
      , ctry.iso_code country_iso_code
      , ctry.name country_name
      , p.phone
      , e.email
      , w.url
      , act.activity__nl__3 description_nl
      , act.activity__en__4 description_en
      , act.activity__fr__5 description_fr
      , mt.name membership_type
      , m.join_date membership_join_date
      , format(mt.minimum_fee, 2) membership_fee
    from
      civicrm_contact c
    left outer join
      civicrm_address a on a.contact_id = c.employer_id and a.is_primary = 1
    left outer join
      civicrm_country ctry on a.country_id = ctry.id
    left outer join
      civicrm_email e on e.contact_id = c.employer_id and e.is_primary = 1
    left outer join
      civicrm_phone p on p.contact_id = c.employer_id and p.is_primary = 1
    left outer join
      civicrm_website w on w.contact_id = c.employer_id
    left outer join
      civicrm_value_activity_9 act on act.entity_id = c.employer_id
    left outer join
      civicrm_membership m on m.contact_id = c.employer_id and m.join_date <= NOW() and m.end_date >= NOW()
    left outer join
      civicrm_membership_type mt on mt.id = m.membership_type_id
    left outer join
      civicrm_membership_status ms on ms.id = m.status_id
    where
      c.id = %1
  ";
  $sqlParams = [
    1 => [$id, 'Integer'],
  ];

  $contact = CRM_Core_DAO::executeQuery($sql, $sqlParams);
  $contact->fetch();

  // create full_address
  $contact->full_address = '';
  if ($contact->street_address) {
    $contact->full_address .= $contact->street_address;
  }
  if ($contact->supplemental_address_1) {
    if (strlen($contact->full_address) > 0) {
      $contact->full_address .= '<br>';
    }
    $contact->full_address .= $contact->supplemental_address_1;
  }
  if ($contact->supplemental_address_2) {
    if (strlen($contact->full_address) > 0) {
      $contact->full_address .= '<br>';
    }
    $contact->full_address .= $contact->supplemental_address_2;
  }
  if ($contact->postal_code) {
    if (strlen($contact->full_address) > 0) {
      $contact->full_address .= '<br>';
    }
    $contact->full_address .= $contact->postal_code;
  }
  if ($contact->city) {
    if ($contact->postal_code) {
      // there's a postal code, just add a space between postal code and city
      $contact->full_address .= ' ' . $contact->city;
    }
    else {
      if (strlen($contact->full_address) > 0) {
        $contact->full_address .= '<br>';
      }
      $contact->full_address .= $contact->city;
    }
  }
  if ($contact->country_name && $contact->country_iso_code != 'BE') {
    if (strlen($contact->full_address) > 0) {
      $contact->full_address .= '<br>';
    }
    $contact->full_address .= $contact->country_name;
  }

  return $contact;
}

function bemastokens_getMemberContacts($employerID) {
  $PRIMARY_MEMBER_CONTACT = 14;
  $MEMBER_CONTACT = 15;

  $contactList = [];

  if ($employerID) {
    $sql = "
      select
        c.first_name
        , c.last_name
        , c.job_title
        , e.email
      from
        civicrm_contact c
      left outer join
        civicrm_value_individual_details_19 det on det.entity_id = c.id
      left outer join
        civicrm_email e on e.contact_id = c.id and e.is_primary = 1
      where
        c.employer_id = %1
      and
        c.is_deleted = 0
      and
        exists (
          select
            rmc.id
          from
            civicrm_relationship rmc
          where
            rmc.contact_id_a = c.id
          and
            rmc.relationship_type_id in ($PRIMARY_MEMBER_CONTACT, $MEMBER_CONTACT)
          and
            rmc.is_active = 1
        )
    ";
    $sqlParams = [
      1 => [$employerID, 'Integer'],
    ];

    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    while ($dao->fetch()) {
      $contact = $dao->first_name . ' ' . $dao->last_name;

      if ($dao->job_title) {
        $contact .= ' - ' . $dao->job_title;
      }

      if ($dao->email) {
        $contact .= ' (' . $dao->email . ')';
      }
      $contactList[] = $contact;
    }
  }

  if (count($contactList) > 1) {
    // more than one record, return unordered list
    $htmlList = '<ul>';
    foreach ($contactList as $c) {
      $htmlList .= "<li>$c</li>";
    }
    $htmlList .= '</ul>';
    return $htmlList;
  }
  else {
    // just one record, return string
    return $contactList[0];
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function bemastokens_civicrm_config(&$config) {
  _bemastokens_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function bemastokens_civicrm_install() {
  _bemastokens_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function bemastokens_civicrm_enable() {
  _bemastokens_civix_civicrm_enable();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *

 // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function bemastokens_civicrm_navigationMenu(&$menu) {
  _bemastokens_civix_insert_navigation_menu($menu, NULL, array(
    'label' => E::ts('The Page'),
    'name' => 'the_page',
    'url' => 'civicrm/the-page',
    'permission' => 'access CiviReport,access CiviContribute',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _bemastokens_civix_navigationMenu($menu);
} // */
