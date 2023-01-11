<?php

namespace Drupal\learning_tool\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class DeepLinkingForm extends FormBase
{
    public function getFormId()
    {
        return 'assignment_form';
    }
    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $form['#title'] = $this->t('Launch Deep Linking');

        $form['answer'] = [
            '#type' => 'select',
            '#title' => $this->t('Answer'),
            '#options' => [
                'red' => $this->t('Correct'),
                'green' => $this->t('Partially Correct'),
                'blue' => $this->t('Wrong'),
            ],
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Submit'),
        ];

        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state)
    {
        // Validate the form values.
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        // Handle form submission.

    }

}