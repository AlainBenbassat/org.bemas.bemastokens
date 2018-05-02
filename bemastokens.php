<?php

require_once 'bemastokens.civix.php';
use CRM_Bemastokens_ExtensionUtil as E;

function bemastokens_civicrm_tokens(&$tokens) {
  $tokens['bemastokens'] = array(
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
      , ctry.name country_name
      , p.phone
      , e.email
      , w.url
      , act.activity__nl__3 description_nl
      , act.activity__en__4 description_en
      , act.activity__fr__5 description_fr
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
    where
      c.id = %1
  ";
  $sqlParams = [
    1 => [$id, 'Integer'],
  ];

  $contact = CRM_Core_DAO::executeQuery($sql, $sqlParams);
  $contact->fetch();

  return $contact;
}

function bemastokens_getMemberContacts($employerID) {
  $contactList = [];

  if ($employerID) {
    $sql = "
      select
        c.first_name
        , c.last_name
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
        types_of_member_contact_60 in ('M1 - Primary member contact', 'Mc - Member contact')
    ";
    $sqlParams = [
      1 => [$employerID, 'Integer'],
    ];

    $dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
    while ($dao->fetch()) {
      $contact = $dao->first_name . ' ' . $dao->last_name;
      if ($dao->email) {
        $contact .= ' (' . $dao->email . ')';
      }
      $contactList[] = $contact;
    }
  }

  return implode(', ', $contactList);
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
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function bemastokens_civicrm_xmlMenu(&$files) {
  _bemastokens_civix_civicrm_xmlMenu($files);
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
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function bemastokens_civicrm_postInstall() {
  _bemastokens_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function bemastokens_civicrm_uninstall() {
  _bemastokens_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function bemastokens_civicrm_enable() {
  _bemastokens_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function bemastokens_civicrm_disable() {
  _bemastokens_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function bemastokens_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _bemastokens_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function bemastokens_civicrm_managed(&$entities) {
  _bemastokens_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function bemastokens_civicrm_caseTypes(&$caseTypes) {
  _bemastokens_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function bemastokens_civicrm_angularModules(&$angularModules) {
  _bemastokens_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function bemastokens_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _bemastokens_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function bemastokens_civicrm_preProcess($formName, &$form) {

} // */

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