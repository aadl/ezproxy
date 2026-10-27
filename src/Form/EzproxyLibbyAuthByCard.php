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

class EzproxyLibbyAuthByCard extends FormBase {

  public function getFormId() {
    return 'ezproxy_libby_auth_by_card_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    \Drupal::service('page_cache_kill_switch')->trigger();


    $form['card_number'] = [
      '#prefix' => '<div class="card-verify-wrap"><h3>Verify with your card</h3>',
      '#type' => 'textfield',
      '#title' => $this->t('Library Card #')
    ];
    $form['last_name'] = [
      '#prefix' => '</div>',
      '#type' => 'textfield',
      '#title' => $this->t('Last Name')
    ];

    // Button 2: Directs to a custom submission method
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    

    return $form;
  }


  public function validateForm(array &$form, FormStateInterface $form_state) {
     
      $barcode = trim($form_state->getValue('card_number'));
      $last_name = trim($form_state->getValue('last_name'));

      $response = $this->verify_barcode($barcode, $last_name);
      if ($response->status == 'ERROR') {
        $form_state->setErrorByName($response->error, $this->t($response->message));
      } else {
        
        // the verify call tells also checks if the card is actove, so no need to chjeck further permissions
        $pid = $response->patron_id;
        
        $form_state->set('pid', $pid);
     

    }   

    
  }


  public function submitForm(array &$form, FormStateInterface $form_state){

    //this method can return to ez proxy directly, no need for selecting cards or switching logins

    $pid = $form_state->get('pid');
    $uid = $form_state->get('uid');

    // get ezproxy url to redirect to
    $request = \Drupal::request();
    $session = $this->getRequest()->getSession();
    $ezproxy_url = $session->get('ezproxy_url');
    $session->remove('ezproxy_url');

    $response = EzproxyController::redirectEzproxy($pid, $ezproxy_url);
    $form_state->setResponse($response);

  }

  /*
  * 
  */
  public static function verify_barcode($barcode, $last_name = null, $street = null, $phone = null){
    $api_url = \Drupal::config('arborcat.settings')->get('api_url');
    $guzzle = \Drupal::httpClient();

    $query = [
      'barcode' => $barcode,
    ];
    if (!is_null($last_name)) {
      $query['name'] = $last_name;
    } else if (!is_null($street)) {
      $query['street'] = $street;
    } else if (!is_null($phone)) {
      $query['phone'] = $phone;
    }
    
    try{
    $response = $guzzle->request('GET', "$api_url/patron/validate_barcode", ['query' => $query]);
    $response_body = json_decode($response->getBody()->getContents());
    $resp = json_decode("{}");
    if ($response_body->status == 'ERROR') {
      $resp->error = $response_body->error;
      $resp->message = $response_body->message;
      $resp->status = $response_body->status;
      return $resp;
    } else {
      $resp->status = $response_body->status;
      $selfcheck_key = \Drupal::config('arborcat.settings')->get('selfcheck_key');
      $selfcheck_key .= '-' .  $barcode;
      $patron = json_decode($guzzle->get("$api_url/patron/$selfcheck_key/get")->getBody()->getContents());
      $patron_expires = strtotime($patron->expires);
      if ($patron_expires >= time()) {
        //not expired
        $resp->status = "VALID";
        $resp->patron_id = $response_body->patron_id;
        return $resp;
      } else {
        $resp->status = "ERROR";
        $resp->error = "barcode";
        $resp->message = 'Your Library card is expired!  Please visit <a href="https://aadl.org/myaccount">MY ACCOUNT</a> to renew';
        return $resp;
      }
    }

    } catch (\Exception $e){

      $resp->status = "ERROR";
      $resp->error = "barcode";
      $resp->message = "Error retreiving barcode information, Please try again later";
      return $resp;

    }

    $resp->status = "ERROR";
    $resp->error = "barcode";
    $resp->message = "Unable to validate barcode";
    return $resp;
  }

}