<?php

/**
 * @file
 * Contains \Drupal\ezproxy\Form\EzproxyAuthForm.
 */

namespace Drupal\ezproxy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\ezproxy\Controller\EzproxyController;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EzproxyLibbyCardSelection extends FormBase {

  public function getFormId() {
    return 'ezproxy_libby_card_selection_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {

    \Drupal::service('page_cache_kill_switch')->trigger();

    $session = $this->getRequest()->getSession();
    $ezproxy_url = $session->get('ezproxy_url');
    $ezproxy_auth_pid = $session->get('ezproxy_auth_pid');
    $ezproxy_auth_uid = $session->get('ezproxy_auth_uid');

    if ((!$ezproxy_url || is_null($ezproxy_url) || $ezproxy_url == "")) {
      return new RedirectResponse(\Drupal\Core\Url::fromRoute('user.page')->toString());
    }

    // if no form of auth, we can return to login
    if ( (!$ezproxy_auth_pid || is_null($ezproxy_auth_pid) ) && !\Drupal::currentUser()->isAuthenticated()){
      return new RedirectResponse('/ezproxy/libby');
    }
    
    //if the user is logged and we don't have pid then fall back to the logged in user.
    if ((!$ezproxy_auth_pid || is_null($ezproxy_auth_pid) ) && \Drupal::currentUser()->isAuthenticated()) {
      $current_uid = \Drupal::currentUser()->id();
      $current_user = User::load($current_uid);
      if ($current_user->hasPermission('access ezproxy content')) {
        $ezproxy_auth_pid = $current_user->get('field_patron_id')->value;
      } else {
         // if the logged in user has no PID, we can just redirect to the libby login form
         return new RedirectResponse('/ezproxy/libby');
      }
    }

    $user = \Drupal::entityTypeManager()->getStorage('user')->load($ezproxy_auth_uid);

    /*
    * now lets list our library cards
    * The tricky part here, is only showing cards that are valid
    * sometimes getValue can have a bug, but its inconsistent
    *
    */
    $field_barcodes = $user->get('field_barcode')->getValue();
    $api_keys = $user->get('field_api_key')->getValue();
    $pids = $user->get('field_patron_id')->getValue();

    $pids_list = [];
    $radio_options = [];
    $primary_pid = $pids[0]['value'];

    // Check for existing barcodes and list with option to remove
    foreach ($field_barcodes as $delta => $field_barcode) {
      // Get corresponding API Key
      $api_key = $api_keys[$delta]['value'];
      $guzzle = \Drupal::httpClient();
      $api_url = \Drupal::config('arborcat.settings')->get('api_url');
      // Get corresponding API Key
      $api_key = $api_keys[$delta]['value'];

      // Pull Patron Data
      $api_url = \Drupal::config('arborcat.settings')->get('api_url');
      $patron = FALSE;
      try {
        $patron = json_decode($guzzle->get("$api_url/patron/$api_key/get")->getBody()->getContents());
      } catch (\Exception $e) {
        \Drupal::messenger()->addError('Error retrieving patron data for ' . $field_barcode['value']);  
        continue;
      }
      
      //only show cards from patrons who are not expired
      $patron_expires = strtotime($patron->expires);
      if ($patron_expires < time()) {
        continue;
      }
          
      foreach ($patron->evg_user->cards as $card){
        if ($field_barcode['value'] == $card->barcode && $card->active == "t") {
          $pids_list[] = $patron->pid;
          $radio_options[$patron->pid] = $field_barcode['value'] .", " . $patron->name;
        }
      }
    }

   if (count($radio_options)) {
      $name = $user->getDisplayName();

      $form['card_selection'] = [
        '#type' => 'radios',
        '#title' => $this->t('Please choose a card to verify'),
        '#description' => "<p>Displaying library cards on account: <b>$name</b></p>",
        '#options' => $radio_options,
        '#default_value' => $primary_pid,
        '#required' => TRUE,
      ];
    } else {
      \Drupal::messenger()->addError('There are no valid cards on this account.  Please add or renew your library card at <a href="https://aadl.org/user/'.$ezproxy_auth_uid.'/edit/barcode">https://aadl.org/user/'.$ezproxy_auth_uid.'/edit/barcode</a>');  
    }
    $session->set('ezproxy_auth_valid_pids', $pids_list);
    $form['#attached']['library'][] = 'ezproxy/libby';
    $form['submit']= [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#suffix' => '<div class="submit-divider"></div><a href="/ezproxy/libby">Authorize card from a different account</a>'
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {

      $pid = $form_state->getValue('card_selection');
      $session = $this->getRequest()->getSession();
      $ezproxy_auth_valid_pids = $session->get('ezproxy_auth_valid_pids');
      if (!in_array($pid, $ezproxy_auth_valid_pids)) {
        $form_state->setErrorByName('', $this->t('Invalid barcode selection'));
        return;
      }
    
  }

  public function submitForm(array &$form, FormStateInterface $form_state){
    $pid = $form_state->getValue('card_selection');

    // get ezproxy url to redirect to
    $request = \Drupal::request();
    $session = $this->getRequest()->getSession();
    $ezproxy_url = $session->get('ezproxy_url');
    $session->remove('ezproxy_url');
    $session->remove('ezproxy_auth_uid');
    $session->remove('ezproxy_auth_pid');

    $response = EzproxyController::redirectEzproxy($pid, $ezproxy_url);
    $form_state->setResponse($response);
    
  }


}