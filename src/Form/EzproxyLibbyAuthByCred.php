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

class EzproxyLibbyAuthByCred extends FormBase {

  public function getFormId() {
    return 'ezproxy_libby_auth_by_cred_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    \Drupal::service('page_cache_kill_switch')->trigger();


    $form['username'] = [
      '#prefix' => '<div class="credential-wrap"><h3>Verify with your aadl.org account</h3>',
      '#type' => 'textfield',
      '#title' => $this->t('Username or e-mail address')
    ];
    $form['password'] = [
      '#prefix' => '</div>',
      '#type' => 'password',
      '#title' => $this->t('Password')
    ];


    // Button 1: Triggers the default submitForm() method
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }


  public function validateForm(array &$form, FormStateInterface $form_state) {

      // Perform standard validation
      $user = $form_state->getValue('username');
      $password = $form_state->getValue('password');

      if (filter_var($user, FILTER_VALIDATE_EMAIL)){
        $users = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['mail' => $user]);
        $account = $users ? reset($users) : NULL;
        if (is_null($account)) {
          $form_state->setErrorByName('username', $this->t('Invalid username or password. Please try again.'));
          return;
        }
        $user = $account->getAccountName();
      }

      // check if provided credentials match
      $user_auth = \Drupal::service('user.auth');
      $uid = $user_auth->authenticate($user, $password);

      if (!$uid) {
        $form_state->setErrorByName('username', $this->t('Invalid username or password. Please try again.'));
        return;
      }

      // grab the pid for the account and set it on the form
      $user = User::load($uid);
      
      $pid = $user->get('field_patron_id')->value;
      if (is_null($pid)) {
        $form_state->setErrorByName('username', $this->t('Your AADL account does not have a library card attached to it.  Please add your library card at ' . '<a href="https://aadl.org/user/'.$uid.'/edit/barcode">https://aadl.org/user/'.$uid.'/edit/barcode</a> and try again'));
        return;
      }
      if (!$user->hasPermission('access ezproxy content')) {
        $form_state->setErrorByName('barcode', $this->t('Your library card may be expired.  Please renew it at https://aadl.org/myaccount'));
        return;
      }
      $form_state->set('pid', $pid);
      $form_state->set('uid', $uid);
    
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {

    //needs rework to forward to card selection screens

    $pid = $form_state->get('pid');
    $uid = $form_state->get('uid');
    
    $request = \Drupal::request();
    $session = $request->getSession();
    $session->set('ezproxy_auth_pid', $pid);
    $session->set('ezproxy_auth_uid', $uid);

    $form_state->setResponse(new RedirectResponse('/ezproxy/libby-select'));

    //return new RedirectResponse('/ezproxy/libby-select');

 
  }

  public function barcode_expired($barcode){
    $api_url = \Drupal::config('arborcat.settings')->get('api_url');
    $guzzle = \Drupal::httpClient();
    $selfcheck_key = \Drupal::config('arborcat.settings')->get('selfcheck_key');
    $selfcheck_key .= '-' .  $barcode;
    try{
      $patron = json_decode($guzzle->get("$api_url/patron/$selfcheck_key/get")->getBody()->getContents());
      if ($patron_expires >= time()) {
        return true;
      } else {
        return false;
      }
    } catch (\Exception $e){
      return false;
    }
  }

}
