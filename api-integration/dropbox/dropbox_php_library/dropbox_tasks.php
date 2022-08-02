<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * This library is performing several actions of dropbox upon user requests
 */
class Dropbox {

    /**
     * @var mixed|string
     */
    private $_token = false;

    /**
     * @var string
     */
    private $_dropbox_file = false;

    function __construct($params=array()){
        $this->_token = $params['token'];
    }

    /**
     * Check destination folder is exists on the dropbox
     *
     * @param null $folder_path
     * @return array
     */
    public function folder_exists($folder_path = null){
        $parameters = array(
            'path' => $folder_path,
        );

        $headers = array(
            'Authorization: Bearer '.$this->_token,
            'Content-Type: application/json'
        );

        $curlOptions = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($parameters),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => true
        );

        $ch = curl_init('https://api.dropboxapi.com/2/files/list_folder');
        curl_setopt_array($ch, $curlOptions);
        $response =curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return array( 'response' => $response, 'http_code' => $http_code);
    }

    /**
     * Create new folder based on user requests
     *
     * @param null $path
     * @return array
     */
    public function create_folder($path = null){
        $parameters = array(
            'path' => $path,
            'autorename' => false
        );

        $headers = array(
            'Authorization: Bearer '.$this->_token,
            'Content-Type: application/json'
        );

        $curlOptions = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($parameters),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => true
        );

        $ch = curl_init('https://api.dropboxapi.com/2/files/create_folder_v2');
        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return array('response' => $response, 'http_code' => $http_code);
    }

    /**
     * Return dropbox file meta data
     *
     * @return array
     */
    public function get_file_meta_data(){
        $parameters = array(
            'path' => $this->_dropbox_file,
            'include_media_info' => false
        );

        $headers = array(
            'Authorization: Bearer '.$this->_token,
            'Content-Type: application/json'
        );

        $curlOptions = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($parameters),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => true
        );

        $ch = curl_init('https://api.dropboxapi.com/2/files/get_metadata');
        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return array('response' => $response, 'http_code' => $http_code);
    }

    /**
     * Create shared link of the newly uploaded files
     *
     * @param null $dropbox_file_path
     * @return array
     */
    public function create_shared_link($dropbox_file_path = null){
        $parameters = array(
            'path' => $dropbox_file_path
//            'settings' => array(
//                'requested_visibility' => 'public',
//                'audience' => 'public'
//            )
        );

        $headers = array('Authorization: Bearer '.$this->_token,
            'Content-Type: application/json');

        $curlOptions = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($parameters),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => true
        );

        $ch = curl_init('https://api.dropboxapi.com/2/sharing/create_shared_link_with_settings');
        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return array('response' => $response, 'http_code' => $http_code);
    }

    /**
     * Delete file from the dropbox
     *
     * @return array
     */
    public function delete_file(){
        $parameters = array(
            'path' => $this->_dropbox_file
        );

        $headers = array(
            'Authorization: Bearer '.$this->_token,
            'Content-Type: application/json'
        );

        $curlOptions = array(
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($parameters),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => true
        );

        $ch = curl_init('https://api.dropboxapi.com/2/files/delete_v2');
        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return array('response' => $response, 'http_code' => $http_code);
    }

    /**
     * Upload file to the dropbox
     *
     * @param array $file_data
     * @return array
     */
    public function upload_file($file_data = array()){

        extract($file_data);

        // Check file exists on dropbox, if found delete the file first
        $this->_dropbox_file = $folder_name.$file_name;

        if($this->get_file_meta_data()['http_code'] == 200){
            $delete_file_res = $this->delete_file();
            if($delete_file_res['http_code'] != 200){
                log_message('error', 'Http Code : '.$delete_file_res['http_code'].' Server Response : '.$delete_file_res['response'].' Custom Message : File is not deleted from dropbox');
            }
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        $host = "https://content.dropboxapi.com/2/files/upload";
        //set headers
        $headers = array('Authorization: Bearer '.$this->_token,
            'Content-Type: application/octet-stream',
            'Dropbox-API-Arg: {"path":"'.$folder_name.$file_name.'","mode":"add"}',
        );

        $f = fopen($file, "rb");

        curl_setopt($ch, CURLOPT_URL, $host);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        curl_setopt($ch, CURLOPT_POSTFIELDS, fread($f, filesize($file)));

        $response = curl_exec($ch);

        fclose($f);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return array('http_code' => $http_code, 'response' => $response);

    }
}