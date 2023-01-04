<?php

namespace Drupal\learning_tool\Controller;

use \IMSGlobal\LTI;
use Symfony\Component\HttpFoundation\JsonResponse;


class LearningToolController
{
    public function index()
    {
        return array(
            '#title' => 'Hello from learning tool controller!',
            '#markup' => 'Here is some content.',
        );
    }

    public function deep_linking()
    {
        return array(
            '#title' => 'Deep Linking',
            '#markup' => 'Select some content to deep link',
        );
    }


    public function keyset()
    {

        $db = new LTIDatabase();
        
        $found = $db->find_registration_by_issuer("moodle");

        return new JsonResponse([
            'message' => 'hello from keyset',
            'found' => $found,
        ]);
    }

    public function login()
    {
        return new JsonResponse([
            'message' => 'Hello from login!',
        ]);
    }
}


/**
 * 
 * LTI Database
 * @link https://github.com/1EdTech/lti-1-3-php-library
 * 
 */
class LTIDatabase implements LTI\Database
{
    public function find_registration_by_issuer($iss) {
        $database = \Drupal::service('database');
        $query = $database->query("SELECT * FROM learning_tool_lti WHERE issuer = :issuer", [':issuer' => $iss]);
        $results = $query->fetchAll();

        if (count($results) == 0) {
            return false;
        }

        $issuer_row = $results[0];

        if(!$issuer_row) {
            return false;
        }

        $auth_login_url = $issuer_row['auth_login_url'];
        $auth_token_url = $issuer_row['auth_token_url'];
        $client_id = $issuer_row['client_id'];
        $key_set_url = $issuer_row['key_set_url'];
        $kid = $issuer_row['kid'];
        $issuer = $issuer_row['issuer'];
        $private_key = $issuer_row['private_key'];

        return LTI\LTI_Registration::new ()
            ->set_auth_login_url($auth_login_url)
            ->set_auth_token_url($auth_token_url)
            ->set_client_id($client_id)
            ->set_key_set_url($key_set_url)
            ->set_kid($kid)
            ->set_issuer($issuer)
            ->set_tool_private_key($private_key);
    }
    public function find_deployment($iss, $deployment_id) {
        return LTI\LTI_Deployment::new ()->set_deployment_id($deployment_id);
    }
}