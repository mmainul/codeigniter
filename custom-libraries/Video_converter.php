<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Video_converter extends CI_Controller
{
    /**
     * @var bool | string
     */
    private $_handbrake_cli_path = false;

    /**
     * @var int
     */
    private $_not_converted = 0;

    /**
     * @var int
     */
    private $_converted = 1;

    /**
     * @var string
     */
    private $_photo_file_type = 'video';

    /**
     * @var string
     */
    private $_file_execution_track_table = 'track_currently_converted_file';

    /**
     * @var string
     */
    private $_file_exe_newly_insert_id = null;

    /**
     * @var string
     */
    private $video_file_ext = '.mp4';

    /**
     * @var bool | string
     */
    private $_execution_path = false;

    /**
     * @var bool | string
     */
    private $_uploaded_video_file_path = false;

    /**
     * @var bool | string
     */
    private $_destination_folder_path = false;

    public function __construct()
    {
        parent::__construct();

        if(strpos(gethostname(), '.local') !== false){
            // Local Test
            $this->_handbrake_cli_path = '/usr/local/bin/handbrakeCLI';
        }else{
            // Server Test
            $this->_handbrake_cli_path = '/usr/local/bin/handbrake';
        }

        $this->load->model(array('photofiles_model'));

        $this->load->helper(array(
            'string'
        ));
    }

    public function get_currently_executed_file(){
        $this->db->select($this->_file_execution_track_table.'.*');
        $this->db->from($this->_file_execution_track_table);
        return $this->db->get();
    }

    public function convert_video_file(){
        $request_query_object = $this->get_currently_executed_file();
        if($request_query_object->num_rows() == 0 ){
            // get converted file info
            $unconverted_file = $this->photofiles_model->get_unconverted_video_file($this->_photo_file_type, $this->_not_converted)->row();
            if($unconverted_file){
                $this->db->insert($this->_file_execution_track_table, array('photofiles_id' => $unconverted_file->id));
                $this->_file_exe_newly_insert_id = $this->db->insert_id();

                if($unconverted_file->photofile_type == $this->_photo_file_type){
                    $folder_name_arr = explode('_', $unconverted_file->table_name);
                    $folder_name = $folder_name_arr[0];

                    $photofile_name_arr = explode('_',$unconverted_file->photofile_name);

                    $time = time();
                    $random_string = random_string('nozero');

                    $new_photofile_name = "{$photofile_name_arr[0]}_{$photofile_name_arr[1]}_{$photofile_name_arr[2]}_{$time}_{$random_string}{$this->video_file_ext}";

                    $original_file_name = explode(".", $unconverted_file->original_name);
                    $update_original_file_name = "{$original_file_name[0]}{$this->video_file_ext}";


                    // check file exists
                    $this->_uploaded_video_file_path =  APPPATH . "../photos/{$folder_name}/{$unconverted_file->photofile_name}";
                    if(file_exists($this->_uploaded_video_file_path)){
                        $this->_destination_folder_path = APPPATH . "../photos/{$folder_name}/{$new_photofile_name}";
                        // Converting video file into mp4
                        $this->_execution_path = $this->_handbrake_cli_path.' -i '.$this->_uploaded_video_file_path.' -o '.$this->_destination_folder_path.' -e x264';
                        if(exec($this->_execution_path)){
                            $this->db->update(
                                'photofiles',
                                array(
                                    'photofile_name' => $new_photofile_name,
                                    'photofile_name_before_converted' => $unconverted_file->photofile_name,
                                    'original_name' => $update_original_file_name,
                                    'initial_name' => $unconverted_file->original_name,
                                    'is_converted' => $this->_converted,
                                    'updated_at' => date('Y-m-d H:i:s')
                                ),
                                array(
                                    'id' => $unconverted_file->id
                                )
                            );
                        }else{
                            log_message('error', 'File is not converted');
                        }
                    }

                    // exec endif
                    // Delete the current execution track record
                    if($this->_file_exe_newly_insert_id){
                        $this->db->delete($this->_file_execution_track_table, array('id' => $this->_file_exe_newly_insert_id));
                    }
                }
            }
        }else{
            echo 'Sorry, a file is converted right now.';
            exit;
        }
    }
}