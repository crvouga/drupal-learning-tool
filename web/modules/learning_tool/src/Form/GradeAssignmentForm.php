<?php

namespace Drupal\learning_tool\GradeAssignmentForm;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class GradeAssignmentForm extends FormBase
{
    public function getFormId()
    {
        return 'custom_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $deep_linking_resources = [])
    {
        $form['name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Name'),
            '#required' => TRUE,
        ];

        $form['color'] = [
            '#type' => 'select',
            '#title' => $this->t('Color'),
            '#options' => [
                'red' => $this->t('Red'),
                'green' => $this->t('Green'),
                'blue' => $this->t('Blue'),
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