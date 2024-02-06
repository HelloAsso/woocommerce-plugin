<?php 
 function get_oauth_token($client_id, $client_secret, $api_url)
 {

     $access_token = get_option('helloasso_access_token');
     $refresh_token = get_option('helloasso_refresh_token');
     $token_expires_in = get_option('helloasso_token_expires_in');
     $refresh_token_expires_in = get_option('helloasso_refresh_token_expires_in');

     if ($access_token && time() < $token_expires_in) {
         return $access_token;
     }

     if ($refresh_token && time() < $refresh_token_expires_in) {
         $url = $api_url . 'oauth2/token';

         $data = array(
             'client_id' => $client_id,
             'client_secret' => $client_secret,
             'grant_type' => 'refresh_token',
             'refresh_token' => $refresh_token
         );

         $ch = curl_init($url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

         $response = curl_exec($ch);
         curl_close($ch);

         if ($response === false) {
             return null;
         }

         $data = json_decode($response);
         if (isset($data->access_token)) {
             update_option('helloasso_access_token', $data->access_token);
             update_option('helloasso_refresh_token', $data->refresh_token);
             update_option('helloasso_token_expires_in', $data->expires_in);
             update_option('helloasso_refresh_token_expires_in', time() + 2629800);
             return $data->access_token;
         } else {
             return null;
         }
     }

     if (!$refresh_token) {
         $url = $api_url . '/oauth2/token';

         $data = array(
             'client_id' => $client_id,
             'client_secret' => $client_secret,
             'grant_type' => 'client_credentials'
         );

         $ch = curl_init($url);
         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($ch, CURLOPT_POST, true);
         curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

         $response = curl_exec($ch);
         curl_close($ch);

         if ($response === false) {
             return null;
         }

         $data = json_decode($response);

         if (isset($data->access_token)) {
             add_option('helloasso_access_token', $data->access_token);
             add_option('helloasso_refresh_token', $data->refresh_token);
             add_option('helloasso_token_expires_in', $data->expires_in);
             add_option('helloasso_refresh_token_expires_in', time() + 2629800);
             return $data->access_token;
         } else {
             return null;
         }
     }
 }