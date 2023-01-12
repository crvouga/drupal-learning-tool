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
    

    private $launch_url = "";

    // constructor
    public function __construct()
    {
        // 
        $url = Url::fromRoute('learning_tool.launch', []);
        $url->setAbsolute(true);
        $this->launch_url = $url->toString();
    }


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

        if(!$launch->has_ags()) {
            return [
                "#title" => "Error. Must have assignments and grades enabled"
            ];
        }

        // TODO: check if assignment is completed
        
        // $scope = $launch_data["https://purl.imsglobal.org/spec/lti-ags/claim/endpoint"]["scope"];
        // $endpoint = $launch_data["https://purl.imsglobal.org/spec/lti-ags/claim/endpoint"]["lineitem"];
        // $db = LTI_Database::new();
        // $registration = $db->find_registration_by_issuer($launch_data["iss"]);
        // if(!$registration) {
        //     return [ "#title" => "Error. Registration not found"];
        // }
        // $service_connector = new LTI\LTI_Service_Connector($registration);
        // // $ags = $launch->get_ags();
        // $result = $service_connector->make_service_request($scope, "GET", $endpoint);
        // $line_item = new LTI\LTI_Lineitem($result['body']);
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
        // 
        $launch_data = $launch->get_launch_data();
        $deep_linking_return_url = $launch_data["https://purl.imsglobal.org/spec/lti-dl/claim/deep_linking_settings"]["deep_link_return_url"];

        // 
        $dl = $launch->get_deep_link();
        $append_jwt = function ($resource) use ($dl) {
            $lti_resource = LTI\LTI_Deep_Link_Resource::new ()
                ->set_url($resource['url'])
                ->set_custom_params($resource)
                ->set_title($resource['title']);

            $jwt = $dl->get_response_jwt([$lti_resource]);

            $resource['jwt'] = $jwt;

            return $resource;
        };
        $resources = array_map($append_jwt, self::get_all_resources());

        return [
            "#theme" => "launch_deep_linking",
            "#deep_linking_return_url" => $deep_linking_return_url,
            "#resources" => $resources
        ];
    }

    // 
    // 
    // Grade Route
    // 
    // 

    public function grade() 
    {
        $launch_id_form = $_POST["launch_id"];

        $launch = LTI\LTI_Message_Launch::from_cache($launch_id_form, LTI_Database::new());

        $launch_data = $launch->get_launch_data();

        // NOTE: this throws
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

        $score_maximum = 100;
        $score_given = $choice == $resource["answer"] ? 100 : 1;

        $external_user_id = $launch_data["sub"];

        $grade = LTI\LTI_Grade::new ()
            ->set_score_given($score_given)
            ->set_score_maximum($score_maximum )
            ->set_activity_progress("Completed")
            ->set_grading_progress("FullyGraded")
            ->set_timestamp(date("c"))
            ->set_user_id($external_user_id);

    

        if(!$launch->has_ags()) 
        {
            return [
                "#title" => "Error. Grade service not available"
            ];
        }

        $ags = $launch->get_ags();

        // https://github.com/cengage/moodle-ltiservice_gradebookservices/blob/f223ca8493c7a8b181818a77d6419f76d7901c52/classes/local/resources/scores.php#L195
        $result = $ags->put_grade($grade);

        $is_ok = str_contains($result["headers"][0], "200");
    
        if($is_ok) {
            return [
                "#title" => "$score_given / $score_maximum is your score."
            ];
        }

        $roles = $launch_data["https://purl.imsglobal.org/spec/lti/claim/roles"];
        $is_learner = in_array("http://purl.imsglobal.org/vocab/lis/v2/membership#Learner", $roles);

        if(!$is_learner) {
            return [
                "#title" => "Error - Probably failed because you're not a student."
            ];
        }

        return [
            "#title" => "Error - Something went wrong."
        ];
    }


    // 
    // 
    // Keyset Route
    // 
    // 

    public function keyset(Request $request)
    {
        $issuer = $request->query->get('issuer');

        if(!$issuer) {
            $response = new JsonResponse(["err", "issuer must be provided"]);
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

        $redirect = $login->do_oidc_login_redirect($this->launch_url);
    
        $redirect_url = $redirect->get_redirect_url();
    
        $trusted_redirect = new TrustedRedirectResponse($redirect_url);

        return $trusted_redirect;
    }


    // 
    // 
    // 
    // Helper Routes
    // 
    // 
    // 

    public function register_canvas(){ 
        return self::register_platform("http://canvas.docker");
    }

    public function unregister_canvas(){
        return self::unregister_platform("http://canvas.docker");
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


    // 
    // 
    // 
    // Helpers
    // 
    // 
    // 

    
    
    private static function get_resource_from_launch(LTI\LTI_Message_Launch $launch) {
        $launch_data = $launch->get_launch_data();
        $custom = $launch_data["https://purl.imsglobal.org/spec/lti/claim/custom"];
        $resource_id = $custom['id'];
        $resource = self::get_one_resource_by_id($resource_id);
        return $resource;
    }

    private static function unregister_platform($issuer)
    {
        $result = LTI_Database::unregister_platform(["issuer" => $issuer]);
        return new JsonResponse($result);
    }
    private static function register_platform($issuer)
    {
        $platform_configs = json_decode(file_get_contents(__DIR__ . '/platform-configs.json'), true);
        $moodle_config = $platform_configs[$issuer];
        if (!$moodle_config) {
            return new JsonResponse(["err", "no config for $issuer"]);
        }
        $result = LTI_Database::register_platform($moodle_config);
        return new JsonResponse($result);
    }
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




}

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
    
        $tool_private_key = file_get_contents(__DIR__ . '/private.key');

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
            return ["err", "missing required keys"];
        }

        $found = self::find_many_platforms_by_issuer($input['issuer']);

        if(count($found) > 0) {
            return ["err", "platform already registered"];
        }

        $fields = [
            "issuer" => $input['issuer'],
            "json_string" => json_encode($input),
        ];
        
        \Drupal::database()
            ->insert(self::$table_name)
            ->fields($fields)
            ->execute();

        return ['ok', 'registered platform'];
    }


    public static function unregister_platform($input)
    {
        $required_keys = [
            "issuer",
        ];

        if (!has_keys($input, $required_keys)) {
            return ["err", "missing required keys"];
        }

        $issuer = $input['issuer'];

        $found = self::find_many_platforms_by_issuer($issuer);

        if(count($found) == 0) {
            return ["err", "platform not registered"];
        }

        $connection = \Drupal::service('database');
        $table_name = self::$table_name;
        $query = $connection->query("DELETE FROM $table_name WHERE issuer = '$issuer'");
        $query->execute();

        return ['ok', 'unregistered platform'];
    }

    private static function find_many_platforms_by_issuer($issuer)
    {
        $database = \Drupal::service('database');
        $table_name = self::$table_name;
        $query = $database->query("SELECT * FROM $table_name WHERE issuer = '$issuer'");
        $found = $query->fetchAll();
        return $found;
    }


}

// 
// 
// 
// Helpers
// 
// 
// 

function has_keys($input, $required_keys)
{
    return empty(array_diff($required_keys, array_keys($input)));
}