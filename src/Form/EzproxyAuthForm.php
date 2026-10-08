<?php

/**
 * @file
 * Contains \Drupal\ezproxy\Form\EzproxyAuthForm.
 */

namespace Drupal\ezproxy\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\user\Entity\User;
use Drupal\user\UserAuthentication;
use Drupal\ezproxy\Controller\EzproxyController;

class EzproxyAuthForm extends FormBase {

  public function getFormId() {
    return 'ezproxy_auth_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
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

    // check if provided credentials match
    $uid = \Drupal\user\UserAuthentication::authenticate($username, $password);

    if (!$uid) {
      $form_state->setErrorByName('username', $this->t('Invalid username or password. Please try again.'));
    }

    // grab the pid for the account and set it on the form
    $pid = $user->get('field_patron_id')->value;
    $form_state->set('pid', $pid);
    $form_state->set('uid', $uid);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $pid = $form_state->get('pid');
    $uid = $form_state->get('uid');
    $user = $user = \Drupal\user\Entity\User::load($uid);

    // log user in if they weren't already
    \Drupal\user\LoginFinalizer::finalizeLogin($user);

    // get ezproxy url to redirect to
    $session = $this->getRequest()->getSession();
    $ezproxy_url = $session->get('ezproxy_url');
    $session->remove('ezproxy_url');

    $response = EzproxyController::redirectEzproxy($pid, $ezproxy_url);
    $form_state->setResponse($response);
  }

}