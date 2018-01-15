<?php

/**
 * @file
 * Contains \Drupal\arborcat\Form\ArborcatAdminForm.
 */

namespace Drupal\ezproxy\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

class EzproxyAdminForm extends ConfigFormBase
{

    /**
     * {@inheritdoc}
     */
    public function getFormId()
    {
        return 'ezproxy_admin_form';
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $config = $this->config('ezproxy.settings');

        foreach (Element::children($form) as $variable) {
            $config->set($variable, $form_state->getValue($form[$variable]['#parents']));
        }
        $config->save();

        if (method_exists($this, '_submitForm')) {
            $this->_submitForm($form, $form_state);
        }

        parent::submitForm($form, $form_state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditableConfigNames()
    {
        return ['ezproxy.settings'];
    }

    public function buildForm(array $form, \Drupal\Core\Form\FormStateInterface $form_state)
    {
        $form = [];
        $form['ticket_secret'] = [
          '#type' => 'textfield',
          '#title' => t('Ezproxy Ticket Secret'),
          '#default_value' => \Drupal::config('ezproxy.settings')->get('ticket_secret'),
          '#size' => 64,
          '#maxlength' => 128,
          '#description' => t('Shared Secret for EZProxy Ticket Generation'),
        ];
        $form['ezproxy_url'] = [
          '#type' => 'textfield',
          '#title' => t('Ezproxy URL'),
          '#default_value' => \Drupal::config('ezproxy.settings')->get('ezproxy_url'),
          '#size' => 64,
          '#maxlength' => 128,
          '#description' => t('URL to Ezproxy install. Ex: https://ezproxy.university.edu'),
        ];

        return parent::buildForm($form, $form_state);
    }
}
