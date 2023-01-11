<?php

namespace Drupal\learning_tool\LTI;
use \IMSGlobal\LTI;

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