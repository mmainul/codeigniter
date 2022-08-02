<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Email_model extends CI_Model
{

    public function __construct()
    {
        parent::__construct();

        $config['protocol'] = 'sendmail';

        $config['charset'] = 'iso-8859-1';
        $config['wordwrap'] = TRUE;
        $config['useragent'] = 'php';
        $config['mailtype'] = 'html';
        $this->load->library('email', $config);
    }

    public function send_notification($request_type, $claim_no, $to, $event, $report_link, $invoice_file = '', $status_info_diff = '')
    {

        $this->email->clear(true);

        $this->email->from('info@autoinspections.net', 'AutoInspections.net');
        $this->email->to($to);
        $this->email->reply_to($this->config->item('admin_email'), 'AutoInspections Admin');
        $this->email->subject($event . " for {$request_type} Claim # {$claim_no}");

        $data['event'] = $event;
        $data['report_link'] = $report_link;
        $data['status_info_diff'] = $status_info_diff;
        $data['request_type'] = $request_type;
        $data['claim_no'] = $claim_no;

        $msg = $this->load->view('email/send_notification', $data, true);

        $this->email->message($msg);

        if($invoice_file) {
            $this->email->attach($invoice_file);
        }

        if(!$this->email->send()) {
//            echo $this->email->print_debugger();
        }
    }

    public function send_report_files($report_files = array())
    {

        $total_file_size = 0;
        $allowed_file_size_limit = 7;
        $divider_value = 1024;
        extract($report_files);

        $this->email->clear(true);

        $this->email->from('info@autoinspections.net', 'AutoInspections.net');
        $this->email->to($to);
        $this->email->cc('howieK@autoinspections.net');
        $this->email->reply_to($this->config->item('admin_email'), 'AutoInspections Admin');
        $this->email->subject($event . " for {$request_type} Claim # {$claim_no}");

        $data['event'] = $event;
        $data['pdf_shared_link'] = isset($pdf_shared_link) ? $pdf_shared_link : null;
        $data['docx_shared_link'] = isset($docx_shared_link) ? $docx_shared_link : null;
        $data['request_type'] = $request_type;
        $data['claim_no'] = $claim_no;

        $msg = $this->load->view('email/send_report_files', $data, true);

        $this->email->message($msg);

        if($invoice_file) {
            $this->email->attach($invoice_file);
        }

        if($send_pdf_file && isset($pdf_file)){
            $total_file_size += round((filesize($pdf_file) / $divider_value) / $divider_value, 1);
            if($total_file_size <= $allowed_file_size_limit){
                $this->email->attach($pdf_file);
            }
        }

        if($send_docx_file && isset($docx_file)){
            $total_file_size += round((filesize($docx_file) / $divider_value) / $divider_value, 1);
            if($total_file_size <= $allowed_file_size_limit){
                $this->email->attach($docx_file);
            }
        }

        if(count($additional_pdf_files) > 0) {
            foreach ($additional_pdf_files as $single_pdf_file) {
                 $total_file_size += round((filesize($single_pdf_file) / $divider_value) / $divider_value, 1);
                 if($total_file_size <= $allowed_file_size_limit){
                    $this->email->attach($single_pdf_file);
                 }
            }
        }

        if(!$this->email->send()) {
            log_message('error', $this->email->print_debugger());
            return false;
        }

        return true;

    }

    public function send_un_uploaded_file_lists($un_uploaded_file_lists = array())
    {
        $data['un_uploaded_file_lists'] = $un_uploaded_file_lists;
        $this->email->clear(true);
        $this->email->from('mainul@mvisolutions.com', 'AutoInspections.net');
        $this->email->to('mainul@mvisolutions.com');
        $this->email->reply_to($this->config->item('admin_email'), 'AutoInspections Admin');
        $this->email->subject('Un uploaded file lists of Auto inspections');
        $msg = $this->load->view('email/send_un_uploaded_file_lists', $data, true);
        $this->email->message($msg);
        if(!$this->email->send()) {
            log_message('error', $this->email->print_debugger());
            return false;
        }
        return true;
    }

    public function new_request_notify_admin($request_type, $claim_no, $report_link)
    {
        $to = $this->config->item('admin_email');

        $this->email->clear(true);
        $this->email->from('info@autoinspections.net', 'AutoInspections.net');
        $this->email->to($to);
        $this->email->reply_to($this->config->item('admin_email'), 'AutoInspections Admin');
        $this->email->subject("New inspection request is submitted for {$request_type} Claim # {$claim_no}");

        $data['request_type'] = $request_type;
        $data['claim_no'] = $claim_no;
        $data['date_time'] = date("m/d/Y h:i:s");
        $data['report_link'] = $report_link;

        $msg = $this->load->view('email/new_request_notify_admin', $data, true);
        $this->email->message($msg);

        if(!$this->email->send()) {
//            echo $this->email->print_debugger();
//            exit;
        }
    }

    public function invoice_by_insp_email($req_type, $claim_no, $insp_name, $fee_arr, $date_time) {
        $to = $this->config->item('admin_email');
        $this->email->clear(true);
        $this->email->from('info@autoinspections.net', 'AutoInspections.net');
        $this->email->to($to);
        $this->email->reply_to($this->config->item('admin_email'), 'AutoInspections Admin');
        $this->email->subject("Invoice is generated by {$insp_name} for {$req_type} Claim # {$claim_no}");

        $data['request_type'] = $req_type;
        $data['claim_no'] = $claim_no;
        $data['insp_name'] = $insp_name;
        $data['date_time'] = $date_time;
        $data['insp_fee'] = $fee_arr['insp_fee'];
        $data['misc_charge'] = $fee_arr['misc_charge'];
        $data['total_due'] = $fee_arr['total_due'];


        $msg = $this->load->view('email/invoice_by_insp_email', $data, true);
        $this->email->message($msg);

        if(!$this->email->send()) {
//            echo $this->email->print_debugger();
//            exit;
        }

    }
}
