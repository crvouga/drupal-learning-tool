<?php

namespace Drupal\learning_tool\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use \IMSGlobal\LTI;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


class LearningToolController extends ControllerBase
{
    // TODO: don't hardcode this
    private static $launch_url = "http://localhost:8888/drupal-learning-tool/web/learning-tool/launch";

    // 
    // 
    // LTI Routes 
    // docs: https://github.com/1EdTech/lti-1-3-php-library
    // 
    // 
    public function launch()
    {
        
        $launch = LTI\LTI_Message_Launch::new(LTI_Database::new());

        $launch->validate();

        if ($launch->is_resource_launch()) {
            return $this->launch_resource($launch);
        }

        if ($launch->is_deep_link_launch()) {
            return $this->launch_deep_linking($launch);
        }

        if($launch->is_submission_review_launch()) {
            return [
                '#title' => 'Submission review launch',
            ];
        }

        return [
            '#title' => 'Unknown Launch',
        ];
    }

    private function launch_resource(LTI\LTI_Message_Launch $launch){
        $launch_data = $launch->get_launch_data();


        // 
        // 
        // 
        // 
        // 

        if(!$launch->has_ags()) {
            return [
                "#title" => "Error. Must have assignments and grades enabled"
            ];
        }

        
        $scope = $launch_data["https://purl.imsglobal.org/spec/lti-ags/claim/endpoint"]["scope"];
        $endpoint = $launch_data["https://purl.imsglobal.org/spec/lti-ags/claim/endpoint"]["lineitem"];
        $db = LTI_Database::new();
        $registration = $db->find_registration_by_issuer($launch_data["iss"]);
        if(!$registration) {
            return [ "#title" => "Error. Registration not found"];
        }
        $service_connector = new LTI\LTI_Service_Connector($registration);
        
        // $ags = $launch->get_ags();
        $result = $service_connector->make_service_request($scope, "GET", $endpoint);
        $line_item = new LTI\LTI_Lineitem($result['body']);
        





        // $ags->get_grades();


        // 
        // 
        // 
        // 
        // 

        $roles = $launch_data["https://purl.imsglobal.org/spec/lti/claim/roles"];
        $email = $launch_data['email'];
        $given_name = $launch_data['given_name'];
        $family_name = $launch_data['family_name'];
        // 
        $resource = self::get_resource_from_launch($launch);

        if(!$resource) {
            return [
                '#title' => 'Resource not found',
            ];
        }

        $launch_id = $launch->get_launch_id();

        $url = Url::fromRoute('learning_tool.grade', []);
        $url->setAbsolute(true);
        $grade_action = $url->toString();

        return [
            "#theme" => "launch_resource",
            //
            "#roles" => $roles,
            "#email" => $email,
            "#name" => "$given_name $family_name",
            // 
            "#resource" => $resource,
            "#grade_action" => $grade_action,
            "#launch_id" => $launch_id,
        ];
    }
    
    private function launch_deep_linking(LTI\LTI_Message_Launch $launch)
    {
        $dl = $launch->get_deep_link();
        $launch_data = $launch->get_launch_data();
        $deep_linking_return_url = $launch_data["https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings"]["deep_link_return_url"];

        $resources = array_map(
            function($resource) use ($dl) {
                $lti_resource = LTI\LTI_Deep_Link_Resource::new()
                    ->set_url($resource['url'])
                    ->set_custom_params($resource)
                    ->set_title($resource['title']);

                $jwt = $dl->get_response_jwt([$lti_resource]);

                $resource['jwt'] = $jwt;

                return $resource;
            },
            self::get_all_resources()
        );

        // 
        // TODO: somehow set launch_id in session.
        // I think there is a problem with the session cookie not being set properly
        // because the app is running inside of a iframe.
        // 
        // $launch_id = $launch->get_launch_id();
        // $_SESSION['launch_id'] = $launch_id;
        // 

        return array(
            "#theme" => "launch_deep_linking",
            "#deep_linking_return_url" => $deep_linking_return_url,
            "#resources" => $resources
        );
    }


    // 
    // 
    // Grade Route
    // 
    // 

    public function grade() {
        $launch_id_form = $_POST["launch_id"];

        $launch = LTI\LTI_Message_Launch::from_cache($launch_id_form, LTI_Database::new());
        
        // $launch->validate();
        
        if(!$launch->is_resource_launch()) 
        {
            return [
                "#title" => "Error. Must be a resource launch"
            ];
        }

        $resource = self::get_resource_from_launch($launch);

        if(!$resource) 
        {
            return [
                '#title' => 'Error. Resource not found',
            ];
        }

        $choice = $_POST["choice"];
    
        if(!in_array($choice, $resource["choices"])) {
            return [
                "#title" => "Error. Invalid submission"
            ];
        }

        $score_maximum = 1;
        $score = $choice == $resource["answer"] ? 1 : 0;

        $grade = LTI\LTI_Grade::new()
            ->set_score_given($score)
            ->set_score_maximum($score_maximum)
            ->set_activity_progress("Completed")
            ->set_grading_progress("FullyGraded")
            ->set_timestamp(date(DATE_ATOM));

        if(!$launch->has_ags()) 
        {
            return [
                "#title" => "Error. Grade service not available"
            ];
        }

        $ags = $launch->get_ags();

        $ags->put_grade($grade);

        return [
            "#title" => "Grade submitted"
        ];
    }


    private static function get_resource_from_launch(LTI\LTI_Message_Launch $launch) {
        $launch_data = $launch->get_launch_data();
        $custom = $launch_data["https://purl.imsglobal.org/spec/lti/claim/custom"];
        $resource_id = $custom['id'];
        $resource = self::get_one_resource_by_id($resource_id);
        return $resource;
    }

    // 
    // 
    // Keyset Route
    // 
    // 

    public function keyset(Request $request)
    {
           
        // 
        // TODO: somehow get launch_id from session
        // 
        // $launch_id = $_SESSION['launch_id'];
        // 
        // WHY? we can get the issuer from the launch_id
        // 
        // NOTICE: this is a hack. The LMS has to send it's issuer id
        $issuer = $request->query->get('issuer');

        if(!$issuer) {
            $response = new JsonResponse(err("issuer must be provided"));
            $response->setStatusCode(400);
            return $response;
        }

        $public_jwks = LTI\JWKS_Endpoint::from_issuer(LTI_Database::new(), $issuer)->get_public_jwks();
        
        return new JsonResponse($public_jwks);
    }

    // 
    // 
    // Login Route
    // 
    // 
    
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
    // Resource Helpers
    // 
    // 

    private static function get_all_resources()
    {
        return json_decode(file_get_contents(__DIR__ . '/resources.json'), true);
    }

    private static function get_one_resource_by_id($resource_id)
    {
        $resources = self::get_all_resources();

        foreach ($resources as $resource) {
            if ($resource['id'] == $resource_id) {
                return $resource;
            }
        }

        return false;
    }




    // 
    // 
    // 
    // Helper Routes
    // 
    // 
    // 

    private static function unregister_platform($issuer) {
        $result = LTI_Database::unregister_platform(["issuer" => $issuer]);
        return new JsonResponse($result);
    }
    private static function register_platform($issuer)
    {
        $platform_configs = json_decode(file_get_contents(__DIR__ . '/platform-configs.json'), true);
        $moodle_config = $platform_configs[$issuer];
        if (!$moodle_config) {
            return new JsonResponse(err("no config for $issuer"));
        }
        $result = LTI_Database::register_platform($moodle_config);
        return new JsonResponse($result);
    } 
    
    public function register_moodle() {
        return self::register_platform("http://localhost:8888/moodle");
    }
    
    public function unregister_moodle() {
        return self::unregister_platform("http://localhost:8888/moodle");
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

    public static $table_name = 'learning_tool_platforms';

    // 
    // 
    // 
    // Required by LTI\Database Interface
    // 
    // 
    // 
    public function find_registration_by_issuer($issuer) {
        $found = self::find_many_platforms_by_issuer($issuer);

        if (count($found) == 0) {
            return false;
        }

        $platform_data = json_decode($found[0]->json_string, true);

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

        $found = self::find_many_platforms_by_issuer($input['issuer']);

        if(count($found) > 0) {
            return err("platform already registered");
        }

        $fields = [
            "issuer" => $input['issuer'],
            "json_string" => json_encode($input),
        ];
        
        \Drupal::database()
            ->insert(self::$table_name)
            ->fields($fields)
            ->execute();

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

        $issuer = $input['issuer'];

        $found = self::find_many_platforms_by_issuer($issuer);

        if(count($found) == 0) {
            return err("platform not registered");
        }

        $connection = \Drupal::service('database');
        $table_name = self::$table_name;
        $query = $connection->query("DELETE FROM $table_name WHERE issuer = '$issuer'");
        $query->execute();

        return ok("unregistered platform");
    }

    // 
    // 
    // 
    // 
    // 
    // 

    private static function find_many_platforms_by_issuer($issuer)
    {
        $database = \Drupal::service('database');
        $table_name = self::$table_name;
        $query = $database->query("SELECT * FROM $table_name WHERE issuer = '$issuer'");
        $found = $query->fetchAll();
        return $found;
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

function err($err)
{
    return ["type" => "err", "err" => $err];
}
function has_keys($input, $required_keys)
{
    $inputKeys = array_keys($input);
    $missingRequiredKeys = array_diff($required_keys, $inputKeys);
    return empty($missingRequiredKeys);
}