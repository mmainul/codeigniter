<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Prevent_multiusers_edit extends CI_Controller {

    private $_table_ext = '_request';
    private $_time_locked_field = 'time_locked';
    private $_user_id_field = 'user_id';
    private $_session_id_field = 'session_id';
    // private $_forward_minutes = 5;
    // private $_one_minute = 60;
    private $_forward_seconds = 15;

    function __construct(){
        parent::__construct();
    }

    public function update_time_locked(){
        if($this->input->post('report_type') == 'automobile'){
            $table_first_portion = 'auto';
        }else{
            $table_first_portion = $this->input->post('report_type') == 'pre-purchase' || $this->input->post('report_type') == 'pre_purchase' ? 'prepurchase' : $this->input->post('report_type');
        }
        $data_array = array(
//          $this->_user_id_field => intval($this->session->userdata('logged_user_id')),
            $this->_session_id_field => session_id(),
//          $this->_time_locked_field =>  time() + ($this->_forward_minutes * $this->_one_minute)
            $this->_time_locked_field =>  time() + $this->_forward_seconds
        );

        if($this->input->post('report_type') == 'pre-purchase' || $this->input->post('report_type') == 'pre_purchase'){
            $this->_table_ext = '_report';
        }

        $this->db->update($table_first_portion.$this->_table_ext, $data_array, array('id' => intval($this->input->post('request_id'))));

        return;

    }
}
