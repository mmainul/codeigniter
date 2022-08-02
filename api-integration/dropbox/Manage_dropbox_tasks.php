<?php

defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . '../vendor/autoload.php';
use mikehaertl\wkhtmlto\Pdf;

/**
 * Upload files to the dropbox and send email to the customer after uploading all files
 */
class Manage_dropbox_tasks extends CI_Controller {
    /**
     * Directory separator var
     */
    const DS = '/';

    /**
     * @var date
     */
    private $_year_month = false;

    /**
     * @var string
     */
    private $_token = false;

    /**
     * @var array
     */
    private $_params = array();
    /**
     * @var bool|string
     */
    private $_completed_reports_master_folder = false;
    /**
     * @var bool
     */
    private $_table_name = false;
    /**
     * @var bool|string
     */
    private $_job_queue_table = false;
    /**
     * @var bool
     */
    private $_folder_name = false;
    /**
     * @var bool
     */
    private $_destination_folder_path = false;
    /**
     * @var int
     */
    private $_main_request_id = false;
    /**
     * @var string
     */
    private $_doc_relative_path = APPPATH . '../phpdocx/classes/CreateDocx.inc';
    /**
     * @var string
     */
    private $_claim_number = false;
    /**
     * @var string
     */
    private $_model = false;

    /**
     * @var string
     */
    private $_report_type = '';

    /**
     * @var string
     */
    private $_data_type = '';

    /**
     * @var bool
     */
    private $_inspector_type = false;

    /**
     * @var bool
     * Instance of PDF
     */
    private $_pdf = false;

    /**
     * @var bool | string
     */
    private $_pdf_file_path = false;

    /**
     * @var bool | string
     */
    private $_docx_file_path = false;

    /**
     * @var int
     */
    private $_http_success_code = 200;

    /**
     * @var bool | string
     */
    private $_invoice_file_path = false;

    /**
     * @var bool | int
     */
    private $_user_id = false;

    /**
     * @var bool | string
     */
    private $_pdf_shared_link = false;

    /**
     * @var bool | string
     */
    private $_docx_shared_link = false;

    /**
     * @var bool
     */
    private $_send_pdf_file = false;

    /**
     * @var bool
     */
    private $_send_docx_file = false;

    /**
     * @var int
     */
    private $_file_size_limit = 7;

    /**
     * @var int
     */
    private $_divider_value = 1024;

    /**
     * @var bool
     */
    private $_dropbox_pdf_file_path = false;

    /**
     * @var bool
     */
    private $_dropbox_docx_file_path = false;

    /**
     * @var bool|Pdf
     */
    private $_invoice_pdf = false;

    /**
     * @var bool | string
     */
    private $_report_status = false;

    /**
     * @var bool|string
     */
    private $_new_reports_folder = false;

    /**
     * @var bool | string
     */
    private $_file_upload_dir = false;

    /**
     * @var bool | string
     */
    private $_upload_images = false;

    /**
     * @var bool | string
     */
    private $_images_master_folder = false;

    /**
     * @var bool
     */
    private $_image_successfully_uploaded = false;

    /**
     * @var bool
     */
    private $_have_images = false;

    /**
     * @var string
     */
    private $_currently_upload_processing_table = 'track_currently_uploading_record';

    /**
     * @var null | int
     */
    private $_file_exe_newly_insert_id = null;

    /**
     * @var string
     */
    private $_email_pdf = 'Y';

    /**
     * @var string
     */
    private $_email_docx = 'Y';

    /**
     * @var string
     */
//    private $_email_invoice = 'Y';

    /**
     * @var string
     */
    private $_status_update_email = '';

    /**
     * @var string
     */
    private $_compress_pdf_folder_path = APPPATH . '../exports/after_compress/';

    /**
     * @var string
     */
    private $_compress_pdf_file_path = null;

    /**
     * @var int
     */
    private $_unknown_request_id_code = 99999;

    function __construct(){
        parent::__construct();
        // create pdf instance
        $this->_pdf = new Pdf();
        $this->_invoice_pdf = new Pdf();

        if(strpos(gethostname(), '.local') !== false){
            // Local Test
            $this->_token = '';
        }else if(strpos(gethostname(), 'dev-site.com') !== false) {
            $this->_token = '';
        }else{
            // Live Dropbox API
            $this->_token = '';
        }

        $this->_params['token'] = $this->_token;
        $this->_completed_reports_master_folder = 'autoinspections_completed_reports';
        $this->_new_reports_folder = 'autoinspections_open_reports';
        $this->_images_master_folder = 'autoinspections_images';
        $this->_job_queue_table = 'auto_dropbox_job_queue';
        $this->_year_month = date('Y_m');

        $this->load->model(array('auto_dropbox_job_queue_model'));
        $this->load->model(array('user_model'));

        // Load dropbox library
        $this->load->library('autodropboxintegration', $this->_params);
        // Load compresspdf library
        $this->load->library('compresspdf');
    }

    /**
     * Check whether any file is uploading to the dropbox or not
     * @return mixed]
     */
    public function get_currently_processing_file(){
        $this->db->select($this->_currently_upload_processing_table.'.*');
        $this->db->from($this->_currently_upload_processing_table);
        return $this->db->get();
    }

    /**
     * Return currently uploading record attributes
     *
     * @return mixed
     */
    public function extract_file_info(){
        $upload_file_info_query = $this->auto_dropbox_job_queue_model->get();
        $upload_file_info = $upload_file_info_query->row();
        return $upload_file_info;
    }

    /**
     * Create pdf of invoice
     *
     * @return bool
     */
    public function create_invoice_pdf(){
        $this->_invoice_pdf->addPage(site_url() . "/report/{$this->_report_type}/invoice/{$this->_main_request_id}/print");
        $this->_invoice_file_path = APPPATH . '..'.self::DS.'exports'.self::DS.$this->_report_type.'_invoice_'.$this->_claim_number.'.pdf';
        if (!$this->_invoice_pdf->saveAs($this->_invoice_file_path)) {
            log_message('error', $this->_invoice_pdf->getError());
        }
        return true;
    }

    /**
     * Create docx and pdf file of a report
     *
     * @param string $is_special
     * @return bool|int
     */
    public function create_doc_pdf_file($is_special = ''){
        if ($this->_main_request_id) {

            $this->load->model(array($this->_model));

            // check for valid report id
            $request_query = $this->{$this->_model}->get_by_id($this->_main_request_id);

            if (!$request_query->num_rows()) {
                // fake report id
                log_message('error', 'Sorry, Requested report is not found.');
                return $this->_unknown_request_id_code;
            } else {
                // get report images
                $this->db->order_by('id');
                $this->data['report_images'] = $this->db->get_where(
                    'photofiles',
                    array(
                        'table_name' => $this->_table_name,
                        'request_id' => $this->_main_request_id,
                        'photofile_type' => 'img'
                    )
                );

                // Create and save doc file
                require_once $this->_doc_relative_path;

                $html = file_get_contents(site_url() . "/report/".$this->_report_type."/print-report/{$this->_main_request_id}/img");

                $docx = new CreateDocx();
                $docx->embedHTML($html, array('downloadImages' => true));

                if(!is_dir($this->_file_upload_dir)){
                    mkdir($this->_file_upload_dir, '0755', true);
                }

                if(is_dir($this->_file_upload_dir)){
                    $docx->createDocx($this->_docx_file_path);
                }else{
                    log_message('error', 'File is not created, directory maybe is not exists');
                    return false;
                }

                $this->_pdf->addPage(site_url() . "/report/{$this->_report_type}/print-report/{$this->_main_request_id}/img");
                if (!$this->_pdf->saveAs($this->_pdf_file_path)) {
                    log_message('error', $this->_pdf->getError());
                    return false;
                }
                return true;
            }
        } else {
            log_message('error', 'Sorry, Requested report is not found.');
            return false;
        }
    }

    /**
     * Get size of a file
     *
     * @param $file_path
     * @return float
     */
    public function get_file_size($file_path){
        return round((filesize($file_path) / $this->_divider_value) / $this->_divider_value, 1);
    }

    /**
     * Return new report attributes
     *
     * @return mixed
     */
    public function extract_new_report_info(){
        $upload_file_info_query = $this->auto_dropbox_job_queue_model->get_new_report();
        $upload_file_info = $upload_file_info_query->row();
        return $upload_file_info;
    }

    /**
     * Send files to the customer
     *
     * @param null $client_data
     * @return bool
     * Send email to the customer with attachments
     */
    public function send_email_customer($client_data = null, $pdf_files = array()){
        $email_to = array($client_data->email);
        if(isset($client_data->send_status_upd_email) && $client_data->send_status_upd_email == 'Y') {
            $email_to[] = $this->_status_update_email;
        }
        if(!empty($client_data->additional_email_one)){
            $email_to[] = $client_data->additional_email_one;
        }
        if(!empty($client_data->additional_email_two)){
            $email_to[] = $client_data->additional_email_two;
        }
        if(!empty($client_data->additional_email_three)){
            $email_to[] = $client_data->additional_email_three;
        }

        $_report_files = array(
            'to' => $email_to,
            'claim_no' => $this->_claim_number,
            'invoice_file' => $this->_invoice_file_path,
            'event' => 'Completed report documents',
            'request_type' => strtoupper($this->_report_type)
        );

        if(trim($client_data->email_docx) == $this->_email_docx) {
            $_report_files['docx_shared_link'] = $this->_docx_shared_link;
            $_report_files['send_docx_file'] = $this->_send_docx_file;
            $_report_files['docx_file'] = $this->_docx_file_path.'.docx';
        }

        if(trim($client_data->email_pdf) == $this->_email_pdf) {
            $_report_files['pdf_shared_link'] = $this->_pdf_shared_link;
            $_report_files['send_pdf_file'] = $this->_send_pdf_file;
            $_report_files['pdf_file'] = isset($this->_compress_pdf_file_path) ? $this->_compress_pdf_file_path : $this->_pdf_file_path;
        }

        $additional_pdf_files = array();
        if(count($pdf_files) > 0){
            foreach ($pdf_files as $pdf_file) {
                if (file_exists(APPPATH . "../uploads/{$pdf_file->photofile_name}")) {
                    $additional_pdf_files[] = APPPATH . "../uploads/{$pdf_file->photofile_name}";
                }
            }
        }
        $_report_files['additional_pdf_files'] = $additional_pdf_files;
        if($this->email_model->send_report_files($_report_files)){
            return true;
        }else{
            log_message('error', "Something goes wrong with sending email to the customer");
            return false;
        }
    }

    /**
     * Check the condition and make lists for sending files to the customers
     *
     * @param array $pdf_files
     * @return bool
     */
    public function send_email_process($pdf_files = array()){
        // Send email to the user - PDF, DOCX, INVOICE, SHARED LINK PATH (PDF,DOCX)
        $this->load->model(array('email_model'));

        // get client email
        $client_query = $this->user_model->get_by_id( $this->_user_id );
        $client_data = $client_query->row();

        $total_docx_pdf_size = 0;
        if(file_exists($this->_pdf_file_path) && (trim($client_data->email_pdf) == $this->_email_pdf)) {
            $total_docx_pdf_size += $this->get_file_size($this->_pdf_file_path);
        }
        if(file_exists($this->_docx_file_path.".docx") && (trim($client_data->email_docx) == $this->_email_docx)) {
            $total_docx_pdf_size += $this->get_file_size($this->_docx_file_path.".docx");
        }

        // If total size above current file size limit then compress the pdf
        if($total_docx_pdf_size <= $this->_file_size_limit) {

            if(file_exists($this->_pdf_file_path) && (trim($client_data->email_pdf) == $this->_email_pdf)) {
                $this->_send_pdf_file = true;
            }
            if(file_exists($this->_docx_file_path.".docx") && (trim($client_data->email_docx) == $this->_email_docx)) {
                $this->_send_docx_file = true;
            }

        }else if($total_docx_pdf_size > $this->_file_size_limit) {
            $this->compresspdf->compress_pdf_file(null, null, null, true, $this->_pdf_file_path);
            $this->_compress_pdf_file_path = $this->_compress_pdf_folder_path . "{$this->_claim_number}.pdf";

            if(file_exists($this->_compress_pdf_file_path) && (trim($client_data->email_pdf) == $this->_email_pdf)) {
                $total_docx_pdf_size = $this->get_file_size($this->_compress_pdf_file_path);
            }
            if(file_exists($this->_docx_file_path.".docx") && (trim($client_data->email_docx) == $this->_email_docx)) {
                $total_docx_pdf_size += $this->get_file_size($this->_docx_file_path.".docx");
            }

            if($total_docx_pdf_size > $this->_file_size_limit) {
                if(file_exists($this->_compress_pdf_file_path) && ($this->get_file_size($this->_compress_pdf_file_path) < $this->_file_size_limit)){
                    if(file_exists($this->_compress_pdf_file_path) && (trim($client_data->email_pdf) == $this->_email_pdf)) {
                        $this->_send_pdf_file = true;
                    }
                }

            }else{
                if(file_exists($this->_compress_pdf_file_path) && (trim($client_data->email_pdf) == $this->_email_pdf)) {
                    $this->_send_pdf_file = true;
                }
                if(file_exists($this->_docx_file_path.".docx") && (trim($client_data->email_docx) == $this->_email_docx)) {
                    $this->_send_docx_file = true;
                }
            }

        }
        // Create invoice
        $this->create_invoice_pdf();

        // Create Shared Link
        // PDF shared link
        $_pdf_shared_link_res = $this->autodropboxintegration->create_shared_link($this->_dropbox_pdf_file_path);
        if($_pdf_shared_link_res['http_code'] == $this->_http_success_code){
            $_pdf_server_response = json_decode($_pdf_shared_link_res['response']);
            $this->_pdf_shared_link = $_pdf_server_response->url;
        }

        // DPCX shared link
        $_docx_shared_link_res = $this->autodropboxintegration->create_shared_link($this->_dropbox_docx_file_path);
        if($_docx_shared_link_res['http_code'] == $this->_http_success_code){
            $_docx_server_response = json_decode($_docx_shared_link_res['response']);
            $this->_docx_shared_link = $_docx_server_response->url;
        }

        // Send email to the customer
        if($this->send_email_customer($client_data, $pdf_files)){
            unlink($this->_pdf_file_path);
            unlink($this->_docx_file_path.".docx");
            unlink($this->_invoice_file_path);
            if(file_exists($this->_compress_pdf_file_path)){
                unlink($this->_compress_pdf_file_path);
            }

            return true;
        }

        return false;
    }

    /**
     * Assign file attributes to the corresponding variables
     *
     * @param null $_extract_file_info
     */
    public function populate_file_info($_extract_file_info = null){
        $this->_folder_name = $this->_report_type = $_extract_file_info->report_type;
        $this->_main_request_id = $_extract_file_info->request_id;
        $this->_table_name = $_extract_file_info->table_name;
        $this->_claim_number = $_extract_file_info->report_claim_number;
        $this->_model = $_extract_file_info->model_name;
        $this->_data_type = $_extract_file_info->data_type;
        $this->_user_id = $_extract_file_info->user_id;
        $this->_status_update_email = trim($_extract_file_info->status_update_email);
        if(!is_null($_extract_file_info->inspector_type)){
            $this->_inspector_type = $_extract_file_info->inspector_type;
        }
        if(!is_null($_extract_file_info->upload_images)){
            $this->_upload_images = $_extract_file_info->upload_images;
        }
    }

    /**
     * Save log messages for dropbox folder creation or anything related dropbox folder
     *
     * @param $folder_name
     * @param $server_res
     * @return false
     */
    public function log_dropbox_folder_error_msg($folder_name, $server_res){
        log_message('error', 'Http Code: '.$server_res['http_code'].' Server Response: '.$server_res['response']. ' Custom Message : '.$folder_name.' folder is not exists or program is not able to create new folder');
        return false;
    }

    /**
     * Save log messages for uploading files to the dropbox
     *
     * @param $server_res
     * @param null $file_type
     */
    public function log_dropbox_file_upl_error_msg($server_res, $file_type = null){
        log_message('error', 'Http Code : '.$server_res['http_code'].' Server Response : '.$server_res['response']. '  Custom Message : '.$file_type.' file is not uploaded on dropbox successfully');
    }

    /**
     * Create dropbox destination folder
     *
     * @param false $destination_folder_path
     * @return bool
     */
    public function destination_folder($destination_folder_path = false){
        // Create destination folder
        $destination_folder_res = $this->autodropboxintegration->create_folder($destination_folder_path);
        if($destination_folder_res['http_code'] != $this->_http_success_code){
            $this->log_dropbox_folder_error_msg($this->_folder_name, $destination_folder_res);
            return false;
        }
        return true;
    }

    /**
     * Check uploading folder exists in the dropbox, if not create new one
     *
     * @param array $folder_arr
     */
    public function has_report_folder($folder_arr = array()) {
        // Check report type folder exists or not
        if(array_key_exists('report_type_folder', $folder_arr)){
            if($this->autodropboxintegration->folder_exists($folder_arr['report_type_folder'])['http_code'] != $this->_http_success_code){
                // create report type folder
                $report_type_folder_res = $this->autodropboxintegration->create_folder($folder_arr['report_type_folder']);
                if($report_type_folder_res['http_code'] != $this->_http_success_code){
                    $this->log_dropbox_folder_error_msg($this->_year_month, $report_type_folder_res);
                }
            }
        } // end array key check
    }

    /**
     * Check dropbox upload folder exists
     *
     * @param array $folder_arr
     * @return bool
     */
    public function check_dropbox_upload_folder($folder_arr = array()){
        if($this->autodropboxintegration->folder_exists($folder_arr['destination_folder'])['http_code'] == $this->_http_success_code){
            return true;
        }else{

            // Check destination upload folder is exists like automobile, bbb, bicycle etc.
            if($this->autodropboxintegration->folder_exists($folder_arr['destination_folder'])['http_code'] != $this->_http_success_code){
                // Create destination folder
                if($this->destination_folder($folder_arr['destination_folder'])){
                    return true;
                }
            }

            // Check report folder has in the path
            $this->has_report_folder($folder_arr);

            // Create folder if year month folder not exists
            if($this->autodropboxintegration->folder_exists($folder_arr['year_month'])['http_code'] != $this->_http_success_code){
                // Create year month folder
                $year_month_folder_res = $this->autodropboxintegration->create_folder($folder_arr['year_month']);
                if($year_month_folder_res['http_code'] != $this->_http_success_code){
                    $this->log_dropbox_folder_error_msg($this->_year_month, $year_month_folder_res);
                }


                if($year_month_folder_res['http_code'] == $this->_http_success_code) {

                    // Check if path has report type folder
                    $this->has_report_folder($folder_arr);

                    // Create destination folder
                    if($this->destination_folder($folder_arr['destination_folder'])){
                        return true;
                    }
                }
            }

            // Check root folder is exists otherwise create new one
            if($this->autodropboxintegration->folder_exists( $folder_arr['master_folder'])['http_code'] != $this->_http_success_code){

                $master_folder_res = $this->autodropboxintegration->create_folder($folder_arr['master_folder']);
                if($master_folder_res['http_code'] != $this->_http_success_code){
                    $this->log_dropbox_folder_error_msg($this->_completed_reports_master_folder, $master_folder_res);
                }

                if ($master_folder_res['http_code'] == $this->_http_success_code) {

                    // Create year month folder
                    $year_month_folder_res = $this->autodropboxintegration->create_folder($folder_arr['year_month']);
                    if($year_month_folder_res['http_code'] != $this->_http_success_code){
                        $this->log_dropbox_folder_error_msg($this->_year_month, $year_month_folder_res);
                    }
                }

                if($year_month_folder_res['http_code'] == $this->_http_success_code) {

                    // check path has report folder
                    $this->has_report_folder();

                    if($this->destination_folder($folder_arr['destination_folder'])){
                        return true;
                    }
                }
                return false;
            }
            return false;
        }// end else
    }

    /**
     * Delete item from dropbox job queue table
     *
     * @param null $job_queue_id
     */
    public function delete_dropbox_record($job_queue_id = null){
        // Delete the record, if file upload is successful
        $this->db->delete($this->_job_queue_table, array('id' => $job_queue_id));
    }

    /**
     * Upload pdf docx to the dropbox
     *
     * @param null $master_folder
     * @return bool
     */
    public function upload_pdf_docx_dropbox($master_folder = null){
        if($this->create_doc_pdf_file()){
            // Check both docx and pdf file exists
            $destination_folder_path = self::DS.$master_folder.self::DS.$this->_year_month.self::DS.$this->_folder_name;
            $folder_lists = array(
                'master_folder' => self::DS.$master_folder,
                'year_month' => self::DS.$master_folder.self::DS.$this->_year_month,
                'destination_folder' => $destination_folder_path
            );
            // Check dropbox destination folder exists
            if($this->check_dropbox_upload_folder($folder_lists)){
                // Upload pdf file
                $upload_file_info = array(
                    'file' => $this->_pdf_file_path,
                    'file_name' => $this->_claim_number.".pdf",
                    'folder_name' => $destination_folder_path.self::DS
                );
                $pdf_upload_res = $this->autodropboxintegration->upload_file($upload_file_info);
                if($pdf_upload_res['http_code'] == $this->_http_success_code){
                    $this->_dropbox_pdf_file_path = $destination_folder_path.self::DS.$this->_claim_number.".pdf";
                }else{
                    $this->log_dropbox_file_upl_error_msg($pdf_upload_res, 'PDF');
                    return false;
                }

                // upload DOCX file
                $upload_docx_file_info = array(
                    'file' =>$this->_docx_file_path.".docx",
                    'file_name' => $this->_claim_number.".docx",
                    'folder_name' => $destination_folder_path.self::DS
                );
                $upload_docx_file_res = $this->autodropboxintegration->upload_file($upload_docx_file_info);
                if($upload_docx_file_res['http_code'] == $this->_http_success_code){
                    $this->_dropbox_docx_file_path = $destination_folder_path.self::DS.$this->_claim_number.".docx";
                }else{
                    $this->log_dropbox_file_upl_error_msg($upload_docx_file_res, 'DOCX');
                    return false;
                }

                return true;

            }
        }// endif create doc pdf file

        return false;
    }

    /**
     * Uploading files to the dropbox and send email to the customer and completing all intermediate tasks
     */
    public function index(){
        $_extract_file_info = $this->extract_file_info();
        // Check if relevant information find for a single file then start uploading process of a file
        if(is_object($_extract_file_info)){
            $get_currently_upload_file = $this->get_currently_processing_file();
            if($get_currently_upload_file->num_rows() == 0) {
                $this->db->insert($this->_currently_upload_processing_table, array('dropbox_job_queue_id' => $_extract_file_info->job_queue_id));
                $this->_file_exe_newly_insert_id = $this->db->insert_id();
                // Update is_processing status to avoid conflicts between other cron job
                $update_job_queue_table_data = array(
                    'is_processing' => 1,
                    'updated_at' => date('Y-m-d H:i:s')
                );
                $this->db->update($this->_job_queue_table, $update_job_queue_table_data, array('id' => $_extract_file_info->job_queue_id));

                // Populate file info
                $this->populate_file_info($_extract_file_info);
                $this->_file_upload_dir = APPPATH . '..' . self::DS . 'exports' . self::DS . 'dropbox';
                $this->_docx_file_path = APPPATH . '..' . self::DS . 'exports' . self::DS . 'dropbox' . self::DS . $this->_claim_number;
                $this->_pdf_file_path = APPPATH . '..' . self::DS . 'exports' . self::DS . 'dropbox' . self::DS . "{$this->_claim_number}.pdf";
//            $this->_destination_folder_path = self::DS.$this->_completed_reports_master_folder.self::DS.$this->_year_month.self::DS.$this->_folder_name;
                if ($this->upload_pdf_docx_dropbox($this->_completed_reports_master_folder)) {
                    // Upload images if have
                    if ($this->_upload_images) {
                        // Check both docx and pdf file exists
                        $report_type_folder = self::DS . $this->_images_master_folder . self::DS . $this->_year_month . self::DS . $this->_folder_name;
                        $destination_folder_path = self::DS . $this->_images_master_folder . self::DS . $this->_year_month . self::DS . $this->_folder_name . self::DS . $this->_claim_number;
                        $folder_lists = array(
                            'master_folder' => self::DS . $this->_images_master_folder,
                            'year_month' => self::DS . $this->_images_master_folder . self::DS . $this->_year_month,
                            'report_type_folder' => $report_type_folder,
                            'destination_folder' => $destination_folder_path
                        );

                        // load all related images
                        $images = $this->db->get_where(
                            'photofiles',
                            array(
                                'table_name' => $this->_table_name,
                                'request_id' => $this->_main_request_id,
                                'photofile_type' => 'img'
                            )
                        )->result();
                        if (count($images) > 0) {
                            $this->_have_images = true;
                            // Check dropbox destination folder exists
                            if ($this->check_dropbox_upload_folder($folder_lists)) {
                                foreach ($images as $image_data) {
                                    $folder_name_arr = explode('_', $image_data->table_name);
                                    $folder_name = $folder_name_arr[0];

                                    // upload image
                                    if (!empty($image_data->photofile_name) && file_exists(APPPATH . "../uploads/{$image_data->photofile_name}")) {
                                        // upload image file
                                        $upload_image_file_info = array(
                                            'file' => APPPATH . "../uploads/{$image_data->photofile_name}",
                                            'file_name' => $image_data->photofile_name,
                                            'folder_name' => $destination_folder_path . self::DS
                                        );
                                        $upload_image_file_res = $this->autodropboxintegration->upload_file($upload_image_file_info);
                                        if ($upload_image_file_res['http_code'] != $this->_http_success_code) {
                                            log_message('error', 'Http Code : ' . $upload_image_file_res['http_code'] . ' Server Response : ' . $upload_image_file_res['response'] . '  Custom Message : ' . $image_data->photofile_name . ' file is not uploaded on dropbox successfully');
                                            $this->_image_successfully_uploaded = false;
                                            break;
                                        }
                                    }
                                    if (!$this->_image_successfully_uploaded) {
                                        $this->_image_successfully_uploaded = true;
                                    }

                                }// end foreach

                            }
                        }

                    }// end if root upload images
                }// check pdf docx uploaded

                // Retrieve all related pdf of the report
                $pdf_files = $this->db->get_where(
                    'photofiles',
                    array(
                        'table_name' => $this->_table_name,
                        'request_id' => $this->_main_request_id,
                        'photofile_type' => 'pdf'
                    )
                )->result();

                // If report has own pdf files, update the file size limit so that email sending to the customer won't be failed
                if(count($pdf_files) > 0) {
                    $this->_file_size_limit = 4;
                }

                if ($this->_dropbox_docx_file_path && $this->_dropbox_pdf_file_path) {
                    if ($this->send_email_process($pdf_files)) {
                        if ($this->_upload_images && $this->_have_images) {
                            if ($this->_image_successfully_uploaded) {
                                $this->delete_dropbox_record($_extract_file_info->job_queue_id);
                                // Delete the current execution track record
                                if($this->_file_exe_newly_insert_id){
                                    $this->db->delete($this->_currently_upload_processing_table, array('id' => $this->_file_exe_newly_insert_id));
                                }
                            } else {
                                log_message('error', 'Images are not uploaded successfully');
                                exit;
                            }
                        }// upload image condition
                        $this->delete_dropbox_record($_extract_file_info->job_queue_id);
                    } // end email process
                }// check docx and pdf file exists
                // Delete the current execution track record
                if($this->_file_exe_newly_insert_id){
                    $this->db->delete($this->_currently_upload_processing_table, array('id' => $this->_file_exe_newly_insert_id));
                }
            }

        }
//        else{
////            log_message('error', 'No record is found for file uploading');
//        }
    }

    /**
     * Upload newly created reports to the dropbox
     */
    public function upload_new_report(){
        $_extract_file_info = $this->extract_new_report_info();
        // Check if relevant information find for a single file then start uploading process of a file
        if(is_object($_extract_file_info)) {
            $get_currently_upload_file = $this->get_currently_processing_file();
            if($get_currently_upload_file->num_rows() == 0) {
                $this->db->insert($this->_currently_upload_processing_table, array('dropbox_job_queue_id' => $_extract_file_info->job_queue_id));
                $this->_file_exe_newly_insert_id = $this->db->insert_id();
                // Update is_processing status to avoid conflicts between other cron job
                $update_job_queue_table_data = array(
                    'is_processing' => 1,
                    'updated_at' => date('Y-m-d H:i:s')
                );
                $this->db->update($this->_job_queue_table, $update_job_queue_table_data, array('id' => $_extract_file_info->job_queue_id));

                // Populate file info
                $this->populate_file_info($_extract_file_info);
                $this->_file_upload_dir = APPPATH . '..' . self::DS . 'exports' . self::DS . 'dropbox' . self::DS . 'new_reports';
                $this->_docx_file_path = APPPATH . '..' . self::DS . 'exports' . self::DS . 'dropbox' . self::DS . 'new_reports' . self::DS . $this->_claim_number;
                $this->_pdf_file_path = APPPATH . '..' . self::DS . 'exports' . self::DS . 'dropbox' . self::DS . 'new_reports' . self::DS . "{$this->_claim_number}.pdf";
//            $this->_destination_folder_path = self::DS.$this->_new_reports_folder.self::DS.$this->_year_month.self::DS.$this->_folder_name;

                $upload_status = $this->upload_pdf_docx_dropbox($this->_new_reports_folder);
                if ($upload_status === $this->_unknown_request_id_code){
                    $this->delete_dropbox_record($_extract_file_info->job_queue_id);
                    $this->db->delete($this->_currently_upload_processing_table, array('id' => $this->_file_exe_newly_insert_id));
                    exit;
                }
                if ($upload_status) {
                    if (file_exists($this->_pdf_file_path)) {
                        @unlink($this->_pdf_file_path);
                    }
                    if (file_exists($this->_docx_file_path . ".docx")) {
                        @unlink($this->_docx_file_path . ".docx");
                    }
                    $this->delete_dropbox_record($_extract_file_info->job_queue_id);
                }
                // Delete the current execution track record
                if ($this->_file_exe_newly_insert_id) {
                    $this->db->delete($this->_currently_upload_processing_table, array('id' => $this->_file_exe_newly_insert_id));
                }
            }

        }else{
//            log_message('error', 'No record is found for file uploading');
        }
    }

    /**
     * Lists all un-uploaded files if reports aren't uploading the dropbox
     * @return mixed
     */
    public function un_uploaded_record_lists(){
        $this->db->select($this->_job_queue_table.'.*');
        $this->db->from($this->_job_queue_table);
        $this->db->where('is_processing', 1);
        $this->db->where('deleted', 0);
        $this->db->where('date(`created_at`)', date('Y-m-d'));
        return $this->db->get()->result();
    }

    /**
     * Track un-uploaded reports of today
     * @return bool|void
     */
    public function track_incomplete_file_upload_today(){
        $this->load->model(array('email_model'));
        // get un uploaded file lists
        $get_un_uploaded_file_lists = $this->un_uploaded_record_lists();
        if(count($get_un_uploaded_file_lists) > 0){
            if($this->email_model->send_un_uploaded_file_lists($get_un_uploaded_file_lists)){
                return true;
            }else{
                log_message('error', "Something goes wrong with sending email to the developer");
                return false;
            }
        }

    }

    /**
     * Delete record from currently processing file if it exceeds a certain amount of time
     */
    public function delete_track_currently_uploading_record_manually(){
        $this->db->query("DELETE FROM `track_currently_uploading_record` WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 hour)");
    }

    /**
     * Update the old records status from is_processing = 1 to 0 after certain amount time
     */
    public function update_old_unprocessed_record(){
        $results = $this->db->query("SELECT * FROM {$this->_job_queue_table} WHERE is_processing = 1 AND deleted = 0 AND created_at < DATE_SUB(NOW(), INTERVAL 5 hour)")->result();
        if(count($results) > 0){
            foreach ($results as $single_record){
                $old_record_fields = array(
                    'is_processing' => 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                );
                $this->db->update($this->_job_queue_table, $old_record_fields, array('id' => $single_record->id));
            }
        }
    }
}
