<?php

namespace Drupal\learning_tool\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use \IMSGlobal\LTI;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/*





TODO: MUST FIX
Indexing by platforms by issuer and client id causing breaking the tool






*/

class LearningToolController extends ControllerBase
{
    //
    //
    //
    //
    //
    // LTI Routes
    // docs: https://github.com/1EdTech/lti-1-3-php-library
    //
    //
    //
    //
    public function launch()
    {

        $launch = LTI\LTI_Message_Launch::new(LTI_Database::new());

        try {
            $launch->validate();
        } catch (LTI\LTI_Exception $e) {
            $error_string = $e->getMessage();
            return [
                '#title' => 'Problem. Invalid launch',
                "#markup" => $error_string
            ];
        }

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
        // TODO: check if assignment is completed

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
        $resource = get_resource_from_launch($launch);

        if(!$resource) {
            // we're just assuming that the LMS wants to link resources
            return $this->launch_resource_linking($launch);
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

    //
    //
    // idk why but Canvas LMS routes here when linking resources
    // I thinks its because content selection in canvas does not support deep linking.
    // https://canvas.instructure.com/doc/api/file.link_selection_tools.html
    //
    //
    private function launch_resource_linking(LTI\LTI_Message_Launch $launch)
    {
        //
        $launch_data = $launch->get_launch_data();

        $return_url = $launch_data["https://purl.imsglobal.org/spec/lti/claim/launch_presentation"]["return_url"];

        $resources = get_all_resources();

        $post_resource_url = Url::fromRoute('learning_tool.post_resource', []);
        $post_resource_url->setAbsolute(true);
        $post_resource_url_string = replace_http_with_https($post_resource_url->toString());

        return [
            "#theme" => "launch_resource_linking",
            "#return_url" => $return_url,
            "#launch_id" => $launch->get_launch_id(),
            "#post_resource_url" => $post_resource_url_string,
            "#resources" => $resources
        ];
    }

    public function post_resource(Request $request) {
        $launch_id_form = $request->request->get("launch_id");
        $launch = LTI\LTI_Message_Launch::from_cache($launch_id_form, LTI_Database::new ());

        $return_url = $request->request->get('return_url');
        $resource_id = $request->request->get('resource_id');
        $resource = get_one_resource_by_id($resource_id);

        if(!$resource) {
            return new JsonResponse([
                "error" => "Resource not found"
            ]);
        }

        $resource['url'] = replace_http_with_https($resource['url']);

        //
        //
        // TODO somehow post this data to the lms...
        //
        //
        //

        $message = [
            'lti_message_type' => 'ContentItemSelection',
            'lti_version' => 'LTI-1p0',
            'content_items' => [
                "@context" => "http://purl.imsglobal.org/ctx/lti/v1/ContentItem",
                "@graph" => [
                    [
                        "@type" => "LtiLinkItem",
                        "@id" => $resource['url'],
                        "url" => $resource['url'],
                        "title" => $resource['title'],
                        "text" => $resource['description'],
                        "mediaType" => "application/vnd.ims.lti.v1.ltilink",
                        "placementAdvice" => [
                            "presentationDocumentTarget" => "frame"
                        ]
                    ]
                ]
            ]
        ];

        $redirect = new TrustedRedirectResponse($return_url);

        return $redirect;
    }

    //
    //
    // Moodle LMS routes here when linking resources
    //
    //
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
                ->set_custom_params(['id' => $resource['id']])
                ->set_title($resource['title']);

            $jwt = $dl->get_response_jwt([$lti_resource]);

            $resource['jwt'] = $jwt;

            return $resource;
        };
        $resources = array_map($append_jwt, get_all_resources());

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

        $resource = get_resource_from_launch($launch);

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
        $response = $ags->put_grade($grade);

        $is_ok = str_contains($response["headers"][0], "200");

        if($is_ok) {
            return [
                "#title" => "$score_given / $score_maximum is your score."
            ];
        }

        $roles = $launch_data["https://purl.imsglobal.org/spec/lti/claim/roles"];
        $is_learner = in_array("http://purl.imsglobal.org/vocab/lis/v2/membership#Learner", $roles);

        if(!$is_learner) {
            return [
                "#title" => "Failed to submit. Probably because you're not a student."
            ];
        }

        return [
            "#title" => "Error - Something went wrong.",
            "#markup" => "<pre>" . print_r($response, true) . "</pre>"
        ];
    }


    //
    //
    // JWKS Keyset Route
    //
    //

    public function jwks(Request $request)
    {

        // TODO: the platform has to pass the issuer in the query params. should figure out away without query params
        $issuer = $request->query->get('issuer');

        if(!$issuer) {
            $response = new JsonResponse(["err", "issuer must be provided in query params"]);
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

    public function open_id_connect(Request $request)
    {

        $db = LTI_Database::new();

        $login = LTI\LTI_OIDC_Login::new($db);

        $target_link_uri = $_POST["target_link_uri"];

        $launch_url = replace_http_with_https($target_link_uri);

        $redirect = $login->do_oidc_login_redirect($launch_url);

        // NOTE: when using canvas, this is query params not a full url
        $redirect_url = $redirect->get_redirect_url();

        $trusted_redirect = new TrustedRedirectResponse($redirect_url);

        return $trusted_redirect;
    }

    //
    //
    //
    //
    //
    // Helper Routes
    //
    //
    //
    //
    //

    public function register_canvas()
    {
        return register_platform("https://canvas.instructure.com");
    }

    public function unregister_canvas()
    {
        return unregister_platform("https://canvas.instructure.com");
    }

    public function register_moodle()
    {
        return register_platform("http://localhost:8888/moodle");
    }

    public function unregister_moodle()
    {
        return unregister_platform("http://localhost:8888/moodle");
    }

    public function unregister_all()
    {
        return new JsonResponse(LTI_Database::unregister_platform_all());
    }

    public function platforms()
    {
        $found = LTI_Database::find_all_platforms();
        return new JsonResponse($found);
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
    public function find_registration_by_issuer($iss) {
        $found_result = self::find_many_platforms_by_issuer($iss);

        if($found_result[0] == 'err') {
            return false;
        }

        $found = $found_result[1];

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
    //
    // Helpers
    //
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

        $found_result = self::find_many_platforms_by_indexes($input);

        if($found_result[0] == 'err') {
            return $found_result;
        }

        $found = $found_result[1];

        if(count($found) > 0) {
            return ["err", "platform already registered"];
        }

        $fields = [
            "issuer" => $input['issuer'],
            "client_id" => $input['client_id'],
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
        $client_id = $input['client_id'];
        $found_result = self::find_many_platforms_by_issuer($issuer);

        if($found_result[0] == 'err') {
            return $found_result;
        }

        $found = $found_result;

        if(count($found) == 0) {
            return ["err", "platform not registered"];
        }

        $connection = \Drupal::service('database');
        $table_name = self::$table_name;

        $query = $connection->query("DELETE FROM $table_name WHERE issuer = '$issuer'");
        $query->execute();

        return ['ok', 'unregistered platform'];
    }

    public static function unregister_platform_all()
    {
        $connection = \Drupal::service('database');
        $table_name = self::$table_name;
        $query = $connection->query("DELETE FROM $table_name");
        $query->execute();
        return ['ok', 'unregistered all platform'];
    }

    public static function find_many_platforms_by_indexes($input)
    {
        $required_keys = ['issuer', 'client_id'];

        if (!has_keys($input, $required_keys)) {
            return ["err", "find_many_platforms_by_indexes. missing required keys"];
        }

        $database = \Drupal::service('database');
        $table_name = self::$table_name;
        $issuer = $input['issuer'];
        $client_id = $input['client_id'];
        $query = $database->query("SELECT * FROM $table_name WHERE issuer = '$issuer' AND client_id = '$client_id'");
        $found = $query->fetchAll();
        return ['ok', $found];
    }

    public static function find_many_platforms_by_issuer(string $issuer)
    {
        $database = \Drupal::service('database');
        $table_name = self::$table_name;
        $sql = "SELECT * FROM $table_name WHERE issuer = '$issuer'";
        $query = $database->query($sql);
        $found = $query->fetchAll();
        return ['ok', $found];
    }


    public static function find_all_platforms()
    {
        $database = \Drupal::service('database');
        $table_name = self::$table_name;
        $query = $database->query("SELECT * FROM $table_name");
        $rows = $query->fetchAll();
        $output = [
            "learning_tool_platforms" => array_map(
                function ($row) {
                    return [ "id" => $row->id, "decoded_json_string" => json_decode($row->json_string, true)];
                },
                $rows
            )
        ];
        return $output;
    }


}


//
//
//
//
// Helpers
//
//
//
//

function has_keys($input, $required_keys)
{
    return empty(array_diff($required_keys, array_keys($input)));
}

function get_resource_from_launch(LTI\LTI_Message_Launch $launch) {
    $launch_data = $launch->get_launch_data();
    $custom = $launch_data["https://purl.imsglobal.org/spec/lti/claim/custom"];
    $resource_id = $custom['id'];
    $resource = get_one_resource_by_id($resource_id);
    return $resource;
}

function unregister_platform($issuer)
{
    $result = LTI_Database::unregister_platform(["issuer" => $issuer]);
    return new JsonResponse($result);
}
function register_platform($issuer)
{
    $platform_configs = json_decode(file_get_contents(__DIR__ . '/platform-configs.json'), true);

    $config = $platform_configs[$issuer];

    if(!$config) {
        return new JsonResponse(["err", "platform for $issuer not found"]);
    }

    $result = LTI_Database::register_platform($config);

    return new JsonResponse($result);
}

function get_all_resources()
{
    return json_decode(file_get_contents(__DIR__ . '/resources.json'), true);
}

function get_one_resource_by_id($resource_id)
{
    $resources = get_all_resources();

    foreach ($resources as $resource) {
        if ($resource['id'] == $resource_id) {
            return $resource;
        }
    }

    return false;
}


function attempt(Callable $f) {
    try {
        return ['ok', $f()];
    } catch (\Exception $e) {
        return ['err', $e];
    }
}

function replace_http_with_https(string $url) {
    if(str_contains($url, "http://")) {
        return str_replace("http://", "https://", $url);
    }

    return $url;
}
