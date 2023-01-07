<?php

namespace Drupal\learning_tool\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use \IMSGlobal\LTI;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\learning_tool\Form\DeepLinkingForm;


class LearningToolController extends ControllerBase
{
    // TODO: don't hardcode this
    private static $launch_url = "http://localhost:8888/drupal-learning-tool/web/learning-tool/launch";


    private static $resources = [
        [
            "title" => "Resource A",
            "url" => "http://localhost:8888/drupal-learning-tool/web/learning-tool/launch",
            "custom_params" => [
                "a1" => "a2",
                "a2" => "a2",
            ],
        ],
        [
            "title" => "Resource B",
            "url" => "http://localhost:8888/drupal-learning-tool/web/learning-tool/launch",
            "custom_params" => [
                "b1" => "b2",
                "b2" => "b2",
            ],
        ],
        [
            "title" => "Resource C",
            "url" => "http://localhost:8888/drupal-learning-tool/web/learning-tool/launch",
            "custom_params" => [
                "c1" => "c2",
                "c2" => "c2",
            ],
        ],
    ];    

    // 
    // 
    // 
    // LTI Routes 
    // 
    // 
    // 
    public function launch()
    {
        $launch = LTI\LTI_Message_Launch::new(LTI_Database::new());

        $launch->validate();

        if ($launch->is_resource_launch()) {
            return $this->handle_resource_launch($launch);
        }

        if ($launch->is_deep_link_launch()) {
            return $this->handle_deep_linking_launch($launch);
        }

        return [
            '#title' => 'Unknown Launch',
        ];
    }

    public function handle_resource_launch($launch){
        $launch_data = $launch->get_launch_data();
        $roles = $launch_data["https://purl.imsglobal.org/spec/lti/claim/roles"];
        $email = $launch_data['email'];
        $given_name = $launch_data['given_name'];
        $family_name = $launch_data['family_name'];
        $roles_message = implode(", ", $roles);
        $message = "Hello $given_name $family_name. Your email is ($email). Your roles are: $roles_message";

        return [
            '#title' => 'Resource Launch',
            '#markup' => $message,
        ];
    }

    public function handle_deep_linking_launch($launch)
    {
        $dl = $launch->get_deep_link();
        $launch_data = $launch->get_launch_data();
        $deep_linking_return_url = $launch_data["https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings"]["deep_link_return_url"];

        $resources = array_map(
            function($resource) use ($dl) {
                $lti_resource = LTI\LTI_Deep_Link_Resource::new()
                    ->set_url($resource['url'])
                    ->set_custom_params($resource['custom_params'])
                    ->set_title($resource['title']);

                $jwt = $dl->get_response_jwt([$lti_resource]);

                $resource['jwt'] = $jwt;

                return $resource;
            },
            self::$resources
        );
        
        // fixing: A required parameter (oauth_consumer_key) was missing


        return array(
            "#theme" => "deep_linking_launch",
            "#deep_linking_return_url" => $deep_linking_return_url,
            "#resources" => $resources
        );
    }
    public function keyset()
    {

        /* 
        

        this is how ltijs implements the keyset route:
        https://github.com/Cvmcosta/ltijs/blob/master/src/Provider/Provider.js#L91

        
        TODO:        
        - somehow get a hold of the issuer
        - get the keyset from the database
        - return the keyset as a json response
        
        
        
        */
        
        return new JsonResponse([
            'message' => 'hello from keyset'
        ]);
    }
    public function login()
    {
        $db = LTI_Database::new();
        
        $login = LTI\LTI_OIDC_Login::new($db);
        
        $redirect = $login->do_oidc_login_redirect(self::$launch_url, $_REQUEST);

        $redirect_url = $redirect->get_redirect_url();

        return new TrustedRedirectResponse($redirect_url);
    }

    // 
    // 
    // 
    // Helper Routes
    // 
    // 
    // 

    public function register_moodle() {    
        $result = LTI_Database::register_platform([
            "name" => "Moodle",
            "issuer" => "http://localhost:8888/moodle",
            "client_id" => "s6KjUEZZsAQfTWy",
            "auth_login_url" => "http://localhost:8888/moodle/mod/lti/auth.php",
            "auth_token_url" => "http://localhost:8888/moodle/mod/lti/token.php",
            "key_set_url" => "http://localhost:8888/moodle/mod/lti/certs.php",
        ]);
        return new JsonResponse($result);
    }

    public function unregister_moodle() {
        $result = LTI_Database::unregister_platform([
            "issuer"=> "http://localhost:8888/moodle"
        ]);
        return new JsonResponse($result);
    }

    public function db() {
        $database = \Drupal::service('database');
        $query = $database->query("SELECT * FROM learning_tool_platforms");
        $rows = $query->fetchAll();
        $output = [
            "learning_tool_platforms" => array_map(
                function ($row) {
                    return [ "id" => $row->id, "decoded_json_string" => json_decode($row->json_string, true)];
                }, 
                $rows
            )
        ];
        return new JsonResponse($output);
    }
}

//  
//  
//  
//  docs: https://github.com/1EdTech/lti-1-3-php-library
//  
//  
//  

class LTI_Database implements LTI\Database
{

    // 
    // 
    // 
    // Required by LTI\Database
    // 
    // 
    // 
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
        //
        // 
        $tool_private_key = read_private_key();

        return LTI\LTI_Registration::new ()
            ->set_auth_login_url($auth_login_url)
            ->set_auth_token_url($auth_token_url)
            ->set_client_id($client_id)
            ->set_key_set_url($key_set_url)
            ->set_issuer($issuer)
            ->set_tool_private_key($tool_private_key);
    }
    public function find_deployment($iss, $deployment_id) {
        return LTI\LTI_Deployment::new ()->set_deployment_id($deployment_id);
    }

    // 
    // 
    // 
    // Helpers
    // 
    // 
    // 

    public static function new()
    {
        return new LTI_Database();
    }
    public static function register_platform($input)
    {
        $required_keys = [
            "issuer",
            "client_id",
            "auth_login_url",
            "auth_token_url",
            "key_set_url",
            "name",
        ];

        if (!has_keys($input, $required_keys)) {
            return err("missing required keys");
        }

        $fields = [
            "issuer" => $input['issuer'],
            "json_string" => json_encode($input),
        ];

        \Drupal::service('database')->insert('learning_tool_platforms')->fields($fields)->execute();

        return ok("registered platform");
    }


    public static function unregister_platform($input)
    {
        $required_keys = [
            "issuer",
        ];

        if (!has_keys($input, $required_keys)) {
            return err("missing required keys");
        }

        \Drupal::service('database')->delete('learning_tool_platforms')->condition('issuer', $input['issuer'])->execute();

        return ok("unregistered platform");
    }


}


function read_private_key()
{
    $private_key_path = __DIR__ . '/private.key';
    $my_private_key = file_get_contents($private_key_path);
    return $my_private_key;
}

// 
// 
// 
// Helpers
// 
// 
// 

function ok($data)
{
    return ["type" => "ok", "data" => $data];
}

function is_ok($result)
{
    return $result["type"] == "err";
}

function err($err)
{
    return ["type" => "err", "err" => $err];
}

function is_err($result)
{
    return $result["type"] == "err";
}

function attempt($fn)
{
    // try {
    //     return ok($fn());
    // } catch (mixed $e) {
    //     return err($e->getMessage());
    // }
}

function has_keys($input, $required_keys)
{
    $inputKeys = array_keys($input);
    $missingRequiredKeys = array_diff($required_keys, $inputKeys);
    return empty($missingRequiredKeys);
}