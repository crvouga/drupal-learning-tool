<?php

namespace Drupal\learning_tool\Controller;

use \IMSGlobal\LTI;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;


class LearningToolController
{
    public function launch()
    {
        return array(
            '#title' => 'Launched',
            '#markup' => 'Here is some content to learn from.',
        );
    }
    public function launch_deep_linking()
    {
        return array(
            '#title' => 'Deep Linking',
            '#markup' => 'Select some content to deep link',
        );
    }
    public function keyset()
    {
        return new JsonResponse([
            'message' => 'hello from keyset'    
        ]);
    }
    public function login()
    {
        return new JsonResponse([
            'message' => 'Hello from login!',
        ]);
    }

    // 
    // 
    // 
    // 
    // 
    public function register_moodle() {
        $platform_data= [
            "issuer" => "http://localhost:8888/moodle",
            "client_id" => "zFADOCswVIf6d77",
            "auth_login_url" => "http://localhost:8888/moodle/mod/lti/auth.php",
            "auth_token_url" => "http://localhost:8888/moodle/mod/lti/token.php",
            "key_set_url" => "http://localhost:8888/moodle/mod/lti/certs.php",
            "name" => "Moodle",
        ];
        
        $result = register_platform($platform_data);

        return new JsonResponse($result);
    }

    public function list_platforms() {
        $database = \Drupal::service('database');
        $query = $database->query("SELECT * FROM learning_tool_platforms");
        $results = $query->fetchAll();
        $output = [];
        foreach($results as $result) {
            $output[] = json_decode($result->json_string);
        }
        return new JsonResponse($output);
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
        $query = $database->query("SELECT * FROM learning_tool_platforms WHERE issuer = :issuer", [':issuer' => $iss]);
        $results = $query->fetchAll();

        if (count($results) == 0) {
            return false;
        }

        $platform_data = json_decode($results[0]->json_string, true);

        $auth_login_url = $platform_data['auth_login_url'];
        $auth_token_url = $platform_data['auth_token_url'];
        $client_id = $platform_data['client_id'];
        $key_set_url = $platform_data['key_set_url'];
        $issuer = $platform_data['issuer'];
        // 
        $kid = $platform_data['kid'];

        // 
        $my_private_key = "my private key";

        return LTI\LTI_Registration::new ()
            ->set_auth_login_url($auth_login_url)
            ->set_auth_token_url($auth_token_url)
            ->set_client_id($client_id)
            ->set_key_set_url($key_set_url)
            ->set_issuer($issuer)
            // 
            ->set_kid($kid)
            ->set_tool_private_key($my_private_key);
    }
    public function find_deployment($iss, $deployment_id) {
        return LTI\LTI_Deployment::new ()->set_deployment_id($deployment_id);
    }


    
}

function register_platform($input)
{
    $required_keys = [
        "issuer",
        "client_id",
        "auth_login_url",
        "auth_token_url",
        "key_set_url",
        "name",
    ];

    if(!has_keys($input, $required_keys)) {
        return ['err', 'missing required keys'];
    }

    $fields = [
        "issuer" => $input['issuer'],
        "json_string" => json_encode($input),
    ];

    \Drupal::service('database')->insert('learning_tool_platforms')->fields($fields)->execute();

    return ['ok', 'platform registered'];
}


function has_keys($input, $required_keys)
{
    $inputKeys = array_keys($input);
    $missingRequiredKeys = array_diff($required_keys, $inputKeys);
    return empty($missingRequiredKeys);
}


/* 
await lti.registerPlatform({
url: "http://localhost:8888/moodle",
name: "Moodle",
clientId: "zFADOCswVIf6d77",
authenticationEndpoint: "http://localhost:8888/moodle/mod/lti/auth.php",
accesstokenEndpoint: "http://localhost:8888/moodle/mod/lti/token.php",
authConfig: {
method: "JWK_SET",
key: "http://localhost:8888/moodle/mod/lti/certs.php",
},
});
*/