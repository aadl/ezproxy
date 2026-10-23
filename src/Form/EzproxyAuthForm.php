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

class EzproxyAuthForm extends FormBase {

  public function getFormId() {
    return 'ezproxy_auth_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username or e-mail address'),
      '#required' => TRUE,
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Authenticate'),
    ];

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
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
      $form_state->set('pid', $pid);
      $form_state->set('uid', $uid);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pid = $form_state->get('pid');
    $uid = $form_state->get('uid');

    // get ezproxy url to redirect to
    $session = $this->getRequest()->getSession();
    $ezproxy_url = $session->get('ezproxy_url');
    $session->remove('ezproxy_url');

    $response = EzproxyController::redirectEzproxy($pid, $ezproxy_url);
    $form_state->setResponse($response);
  }

}