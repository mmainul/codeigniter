<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dropbox_job_queue_model extends CI_Model {
    public function get()
    {
        $this->db->select(
                'job_queue.id AS job_queue_id,
                job_queue.request_id,
                job_queue.user_id,
                job_queue.report_claim_number,
                job_queue.report_type,
                job_queue.inspector_type,
                job_queue.data_type,
                job_queue.table_name,
                job_queue.model_name,
                job_queue.upload_images,
                job_queue.is_processing'
        );
        $this->db->from('dropbox_job_queue AS job_queue');
        $this->db->where('is_processing', 0);
        $this->db->where('deleted', 0);
        $this->db->where('report_status', 'completed');
        $this->db->limit(1);
        return $this->db->get();
    }

    public function get_new_report()
    {
        $this->db->select(
                'job_queue.id AS job_queue_id,
                job_queue.request_id,
                job_queue.user_id,
                job_queue.report_claim_number,
                job_queue.report_type,
                job_queue.inspector_type,
                job_queue.data_type,
                job_queue.table_name,
                job_queue.model_name,
                job_queue.is_processing,
                job_queue.upload_images,
                job_queue.report_status'
        );
        $this->db->from('dropbox_job_queue AS job_queue');
        $this->db->where('is_processing', 0);
        $this->db->where('deleted', 0);
        $this->db->where('report_status', 'open');
        $this->db->limit(1);
        return $this->db->get();
    }
}