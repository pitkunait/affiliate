<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class API extends MY_Controller{
	function __construct(){
		parent::__construct();
		$this->load->model('user_model', 'user');
		$this->load->model('Product_model');
		$this->load->model('Report_model');
		$this->load->model('PagebuilderModel');
		___construct(1);	
	}

	public function register_custom_field(){
		$register_form = $this->PagebuilderModel->getSettings('registration_builder');
		$customField = json_decode($register_form['registration_builder'],1);

		foreach ($customField as $key => $value) { 
		  
			$required    = (isset($value['required']) && $value['required'] == 'true') ? true : false;
			$label       = (isset($value['label']) && $value['label'] ) ? $value['label'] : '';
			$placeholder = (isset($value['placeholder']) && $value['placeholder'] ) ? $value['placeholder'] : '';
			$className   = (isset($value['className']) && $value['className'] ) ? $value['className'] : '';
			$name        = 'custom_'.((isset($value['name']) && $value['name'] ) ? $value['name'] : '');
			$ivalue      = (isset($value['value']) && $value['value'] ) ? $value['value'] : (isset($customValue[$name]) ? $customValue[$name] : '');
			$maxlength   = (isset($value['maxlength']) && $value['maxlength'] ) ? $value['maxlength'] : '';
			$min         = (isset($value['min']) && $value['min'] ) ? $value['min'] : '';
			$max         = (isset($value['max']) && $value['max'] ) ? $value['max'] : '';
			$mobile_validation         = (isset($value['mobile_validation']) && $value['mobile_validation'] ) ? true : false;
			$_customValue = $ivalue;

			if ($value['type'] != 'header') {
				$json['fields'][] = [
					"type"              => $value['type'],
					"required"          => $required,
					"label"             => $label,
					"className"         => $className,
					"name"              => $name,
					"min"               => $min,
					"max"               => $max,
					"maxlength"         => $maxlength,
					"values" => $value['values'],
					"mobile_validation" => $mobile_validation,
				];
			}
		}

		header('Content-Type: application/json');
		echo json_encode($json);
	}

	public function register(){
		$this->load->library('form_validation');
		$post = $this->input->post(null,true);
		$json = array();

		$post['user_type'] = 'user';
		$refid = isset($post['refid']) ? $post['refid'] : '';
		$post['affiliate_id'] = !empty($refid) ? base64_decode($refid) : 0;

		$this->form_validation->set_rules('firstname', 'First Name', 'required|trim');
		$this->form_validation->set_rules('lastname', 'Last Name', 'required|trim');
		$this->form_validation->set_rules('username', 'Username', 'required|trim');
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email|xss_clean');
		$this->form_validation->set_rules('terms', 'Terms and Condition', 'required');
		$this->form_validation->set_rules('password', 'Password', 'required|trim', array('required' => '%s is required'));
		$this->form_validation->set_rules('cpassword', 'Confirm Password', 'required|trim|matches[password]', array('required' => '%s is required'));

		$json['errors'] = array();

		$register_form = $this->PagebuilderModel->getSettings('registration_builder');
		if($register_form){
			$customField = json_decode($register_form['registration_builder'],1);
			
			foreach ($customField as $_key => $_value) {
				$field_name = 'custom_'. $_value['name'];

				if($_value['required'] == 'true'){
					if(!isset($post[$field_name]) || $post[$field_name] == ''){ 
						$json['errors'][$field_name] = $_value['label'] ." is required.!";
					}
				}

				if(!isset($json['errors'][$field_name]) && (int)$_value['maxlength'] > 0){
					if(strlen( $post[$field_name] ) > (int)$_value['maxlength']){
						$json['errors'][$field_name] = $_value['label'] ." Maximum length is ". (int)$_value['maxlength'];
					}
				}

				if(!isset($json['errors'][$field_name]) && (int)$_value['minlength'] > 0){
					if(strlen( $post[$field_name] ) > (int)$_value['minlength']){
						$json['errors'][$field_name] = $_value['label'] ." Minimum length is ". (int)$_value['minlength'];
					}
				}
			}
		}

		if ($this->form_validation->run() == FALSE) {
			$json['errors'] = array_merge($this->form_validation->error_array(), $json['errors']);
		}

		if( count($json['errors']) == 0){
			$checkEmail = $this->db->query("SELECT id FROM users WHERE email like ". $this->db->escape($this->input->post('email',true)) ." ")->num_rows();
			if($checkEmail > 0){ $json['errors']['email'] = "Email Already Exist"; }

			$checkUsername = $this->db->query("SELECT id FROM users WHERE username like ". $this->db->escape($this->input->post('username',true)) ." ")->num_rows();
			if($checkUsername > 0){ $json['errors']['username'] = "Username Already Exist"; }

			if(count($json['errors']) == 0){	
				$user_type = 'user';
				$geo = $this->ip_info();
				
				$refid = !empty($refid) ? base64_decode($refid) : 0;
				$commition_setting = $this->Product_model->getSettings('referlevel');

				$disabled_for = json_decode( (isset($commition_setting['disabled_for']) ? $commition_setting['disabled_for'] : '[]'),1); 
				if((int)$commition_setting['status'] == 0){ $refid  = 0; }
				else if((int)$commition_setting['status'] == 2 && in_array($refid, $disabled_for)){ $refid = 0; }

				$custom_fields = array();
                foreach ($this->input->post(null,true) as $key => $value) {
                	if(!in_array($key, array('affiliate_id','terms','cpassword','firstname','lastname','email','username','password'))){
                		$custom_fields[$key] = $value;
                	}
                }

				$data = $this->user->insert(array(
					'firstname'                 => $this->input->post('firstname',true),
					'lastname'                  => $this->input->post('lastname',true),
					'email'                     => $this->input->post('email',true),
					'username'                  => $this->input->post('username',true),
					'password'                  => sha1($this->input->post('password',true)),
					'refid'                     => $refid,
					'type'                      => $user_type,
					'Country'                   => $geo['id'],
					'City'                      => (string)$geo['city'],
					'phone'                     => $geo['city'],
					'twaddress'                 => '',
					'address1'                  => '',
					'address2'                  => '',
					'ucity'                     => $geo['city'],
					'ucountry'                  => $geo['id'],
					'state'                     => $geo['state'],
					'uzip'                      => '',
					'avatar'                    => '',
					'online'                    => '0',
					'unique_url'                => '',
					'bitly_unique_url'          => '',
					'created_at'                => date("Y-m-d H:i:s"),
					'updated_at'                => date("Y-m-d H:i:s"),
					'google_id'                 => '',
					'facebook_id'               => '',
					'twitter_id'                => '',
					'umode'                     => '',
					'PhoneNumber'               => '',
					'Addressone'                => '',
					'Addresstwo'                => '',
					'StateProvince'             => '',
					'Zip'                       => '',
					'f_link'                    => '',
					't_link'                    => '',
					'l_link'                    => '',
					'product_commission'        => '0',
					'affiliate_commission'      => '0',
					'product_commission_paid'   => '0',
					'affiliate_commission_paid' => '0',
					'product_total_click'       => '0',
					'product_total_sale'        => '0',
					'affiliate_total_click'     => '0',
					'sale_commission'           => '0',
					'sale_commission_paid'      => '0',
					'status'                    => '1',
					'value'                    => json_encode($custom_fields),
				));

				$post['refid'] = !empty($refid) ? base64_decode($refid) : 0;

				if(!empty($data) && $user_type == 'user'){
					$notificationData = array(
						'notification_url'          => '/userslist/'.$data,
						'notification_type'         =>  'user',
						'notification_title'        =>  __('user.new_user_registration'),
						'notification_viewfor'      =>  'admin',
						'notification_actionID'     =>  $data,
						'notification_description'  =>  $this->input->post('firstname',true).' '.$this->input->post('lastname',true).' register as a  on affiliate Program on '.date('Y-m-d H:i:s'),
						'notification_is_read'      =>  '0',
						'notification_created_date' =>  date('Y-m-d H:i:s'),
						'notification_ipaddress'    =>  $_SERVER['REMOTE_ADDR']
					);
					$this->insertnotification($notificationData);

					if ($post['affiliate_id'] > 0) {
						$notificationData = array(
							'notification_url'          => '/managereferenceusers',
							'notification_type'         =>  'user',
							'notification_title'        =>  __('user.new_user_registration_under_your'),
							'notification_viewfor'      =>  'user',
							'notification_view_user_id' =>  $post['affiliate_id'],
							'notification_actionID'     =>  $data,
							'notification_description'  =>  $this->input->post('firstname',true).' '.$this->input->post('lastname',true).' has been register under you on '.date('Y-m-d H:i:s'),
							'notification_is_read'      =>  '0',
							'notification_created_date' =>  date('Y-m-d H:i:s'),
							'notification_ipaddress'    =>  $_SERVER['REMOTE_ADDR']
						);
						$this->insertnotification($notificationData);
					}

                    $post['user_type'] = 'user';

					$json['success']  =  "You've Successfully registered";
					unset($json['errors']);
                    $user_details_array=$this->user->login($this->input->post('username',true));
                    $this->load->model('Mail_model');
					$this->Mail_model->send_register_mail_api($post,__('user.welcome_to_new_user_registration'));
				}
			}
		}
		
		header('Content-Type: application/json');
		echo json_encode($json);
	}

	public function ip_info($ip = NULL, $purpose = "location", $deep_detect = TRUE) {
	    $output = NULL;
	    if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
	        $ip = $_SERVER["REMOTE_ADDR"];
	        if ($deep_detect) {
	            if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
	                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	            if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
	                $ip = $_SERVER['HTTP_CLIENT_IP'];
	        }
	    }
	    $purpose    = str_replace(array("name", "\n", "\t", " ", "-", "_"), NULL, strtolower(trim($purpose)));
	    $support    = array("country", "countrycode", "state", "region", "city", "location", "address");
	    $continents = array(
	        "AF" => "Africa",
	        "AN" => "Antarctica",
	        "AS" => "Asia",
	        "EU" => "Europe",
	        "OC" => "Australia (Oceania)",
	        "NA" => "North America",
	        "SA" => "South America"
	    );

	    if (filter_var($ip, FILTER_VALIDATE_IP) && in_array($purpose, $support)) {
	        
	        $curl = curl_init("http://www.geoplugin.net/json.gp?ip=" . $ip);
	        $request = '';
	        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
	        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	        curl_setopt($curl, CURLOPT_HEADER, false);
	        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	        
	        $ipdat = json_decode(curl_exec($curl));
	        if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
	            switch ($purpose) {
	                case "location":
		                $id = 0;
	                    $code = @$ipdat->geoplugin_countryCode;
	                    $data = $this->db->query("SELECT id FROM countries WHERE sortname LIKE '{$code}' ")->row();
	                    if($data){
	                    	$id = $data->id;
	                    }
	                    $output = array(
							"city"           => @$ipdat->geoplugin_city,
							"state"          => @$ipdat->geoplugin_regionName,
							"country"        => @$ipdat->geoplugin_countryName,
							"country_code"   => @$ipdat->geoplugin_countryCode,
							"continent"      => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
							"continent_code" => @$ipdat->geoplugin_continentCode,
							"id"             => $id
	                    );
	                    break;
	                case "address":
	                    $address = array($ipdat->geoplugin_countryName);
	                    if (@strlen($ipdat->geoplugin_regionName) >= 1)
	                        $address[] = $ipdat->geoplugin_regionName;
	                    if (@strlen($ipdat->geoplugin_city) >= 1)
	                        $address[] = $ipdat->geoplugin_city;
	                    $output = implode(", ", array_reverse($address));
	                    break;
	                case "city":
	                    $output = @$ipdat->geoplugin_city;
	                    break;
	                case "state":
	                    $output = @$ipdat->geoplugin_regionName;
	                    break;
	                case "region":
	                    $output = @$ipdat->geoplugin_regionName;
	                    break;
	                case "country":
	                    //$output = @$ipdat->geoplugin_countryName;
	                    $output = 0;
	                    $code = @$ipdat->geoplugin_countryCode;
	                    $data = $this->db->query("SELECT id FROM countries WHERE sortname LIKE '{$code}' ")->row();
	                    if($data){
	                    	$output = $data->id;
	                    }
	                    break;
	                case "countrycode":
	                    $output = @$ipdat->geoplugin_countryCode;
	                    break;
	            }
	        }
	    }
	   
	    return $output;
	}

	public function insertnotification($postData = null){
		if(!empty($postData)){
			$data['custom'] = $this->Product_model->create_data('notification', $postData);
		}
	}
}