<?php


class CRM_CivirulesAction_Contact_CreateCMSUser extends CRM_Civirules_Action {

public function getExtraDataInputUrl($ruleActionId) {
  return FALSE;
}


public function processAction(CRM_Civirules_TriggerData_TriggerData $triggerData) {
  $cid = $triggerData->getContactId();
  
  CRM_Contact_BAO_Contact::deleteContact($cid);
 
 /*
  //we cannot delete domain contacts
  if (CRM_Contact_BAO_Contact::checkDomainContact($contactId)) {
    return;
  }
 
 
  //CRM_Contact_BAO_Contact::deleteContact($contactId);
  */
  
  
  $send_pwd_email_to_user = false; 
   $verification_only = "create" ;

	$user_count = 0; 
	$contacts_skipped = 0 ; 
	$users_emailed = 0; 
	$users_validated = 0; 
	
	$age_cutoff_date = "now()";
	
	 
    		//foreach($this->_contactIds as $cid){
    		
    		 $tmp_sql_age =  "((date_format(".$age_cutoff_date.",'%Y') - date_format(c.birth_date,'%Y')) - 
    	          (date_format(".$age_cutoff_date.",'00-%m-%d') < date_format(c.birth_date,'00-%m-%d')))";
    		
    			$sql = "select ufm.uf_id, c.sort_name,  lower(c.first_name) as first_name, lower(c.last_name) as last_name , 
    				lower(e.email) as email, c.contact_type, c.is_deceased, c.is_deleted,
    				ufm.uf_id , ufe.id as uf_email_id, count(extras.contact_id) as count_contacts_with_shared_email,
    				$tmp_sql_age as age FROM
    				civicrm_contact c 
    				LEFT JOIN civicrm_email e ON c.id = e.contact_id AND e.is_primary =1
    				LEFT JOIN civicrm_uf_match ufm ON ufm.contact_id = c.id 
    				LEFT JOIN civicrm_uf_match ufe ON lower(ufe.uf_name) = lower(e.email)
    				LEFT JOIN civicrm_email extras ON extras.email = e.email AND extras.contact_id IS NOT NULL AND extras.contact_id <> c.id
    				WHERE c.id = $cid
    				GROUP BY c.id ";
    			
    				
			 $params = array();
		
		   	$dao = CRM_Core_DAO::executeQuery($sql, $params);
		   
		   	if( $dao->fetch()){ 	
		   		$first_name = $dao->first_name;
		   		$first_initial = substr ($first_name, 0, 1) ;
		   		$last_name = $dao->last_name; 
		   		$count_contacts_with_shared_email = $dao->count_contacts_with_shared_email; 
		   		
		   		$tmp_sort_name = $dao->sort_name;   
		   		// Need the following to validate that this contact is eligible for a user. 
		   		$contact_type = $dao->contact_type;
		   		$tmp_user_email = $dao->email; 
		   		$is_deceased = $dao->is_deceased; 
		   		$is_deleted = $dao->is_deleted; 
		   		$uf_id = $dao->uf_id;
		   		$uf_email_id = $dao->uf_email_id ; 
		   		$age = $dao->age; 
		   		
		   		
		   		// Deal with removing any invalid characters that cannot be in a user name.
		   		// only allow A-Z, a-z, or 0-9
		   		$first_name_cleaned = preg_replace("/[^A-Za-z0-9]/", '', $first_name);
		   		$last_name_cleaned =  preg_replace("/[^A-Za-z0-9]/", '', $last_name);
		   		
		   		
		   		// pattern is first name, period, last name, then contact id. Such as sarah.gladstone2528
		   		$tmp_user_name = $first_name_cleaned.".".$last_name_cleaned.$cid ; 
		   		
		   		
		   	
		   		
		   		// do various validations to see if this contact should get a user created or not. 
		   		$existing_user = user_load(array('name' => $tmp_user_name));
		   		
		   		$valid_user = true; 
		   		$inavlid_user_msg = ""; 
		   		if( $contact_type <> 'Individual'){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid) because its not an Individual.";
		   		}else if( $is_deceased == "1"){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid) because contact is deceased"; 
		   		}else if( $is_deleted == "1" ){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid) because contact is deleted";
		   		}else if( strlen($first_name) == 0){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because first_name is empty";
		   		}else if( strlen($first_name_cleaned) == 0 ){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because first_name does not include any valid characters, that is A-Z, a-z, or 0-9";
		   		}else if( strlen($last_name) == 0){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because last_name is empty";
		   		}else if( strlen($last_name_cleaned) == 0 ){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because last_name does not include any valid characters, that is A-Z, a-z, or 0-9";
		   		}else if( strlen($tmp_user_email) == 0){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because email is empty";
		   		}else if( strlen($uf_id) > 0 ){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because user already exists";
		   		}else if( strlen( $uf_email_id) > 0 ){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because user already exists";
		   		}else if(filter_var($tmp_user_email, FILTER_VALIDATE_EMAIL) == false ){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because email address is invalid";
		   		}else if( $count_contacts_with_shared_email <> "0" ){
		   			//$valid_user = false; 
		   			//$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because email '$tmp_user_email' is used by $count_contacts_with_shared_email other contact(s)";
		   		
		   		}else if($existing_user->uid){
		   			$valid_user = false; 
		   			$inavlid_user_msg = "Skipped contact $tmp_sort_name (id: $cid, age: $age) because user name '$tmp_user_name' is taken";
		   		}
		   		
		   		
		   		if($valid_user) { 
		   			
		   		
			   		$tmp_init = "mass_created-".$cid."-".$tmp_user_email; 
			   	 	$tmp_user_pass = self::generate_password(); 
		    			 $userinfo = array(
					      'name' => $tmp_user_name ,
					      'init' => $tmp_init ,
					      'mail' => $tmp_user_email ,
					      'pass' => $tmp_user_pass ,
					      'status' => 1
					    );
					    
					if( $verification_only == "create" ){	
					
					
					    $account = user_save('', $userinfo);
			    
			    
			     
					    if (!$account) {
					      $status[] = "Error saving user account for $tmp_sort_name (id: $cid, age: $age) ";
					      $contacts_skipped = $contacts_skipped + 1; 
					    }else{
			    			$new_user_id = $account->uid; 
			    			
			    			// Make sure user is mapped to the correct contact ID, as its an issue if the 
					    	// email is used by more than one CiviCRM contact. 
					    	
			    			$sql_update = "UPDATE civicrm_uf_match set contact_id = '$cid' 
			    					WHERE uf_id = '$new_user_id' ";
			    					
			    			$params = array();
		
		   				$dao_update = CRM_Core_DAO::executeQuery($sql_update, $params);		
			    			$dao_update->free(); 
			    			
					    	$user_count = $user_count + 1; 
					    	$status[] = "User '$tmp_user_name' created for $tmp_sort_name (id: $cid, age: $age) "; 
					    	
					    	
					    	if($send_pwd_email_to_user){
					    	// Manually set the password so it appears in the e-mail.
			  				$account->password = $tmp_user_pass;
			 
							  // Send the e-mail through the user module.
							  $message = drupal_mail('user', 'register_admin_created', 
							  $tmp_user_email, NULL, array('account' => $account), 
							  variable_get('site_mail'));
							  
							  $users_emailed = $users_emailed + 1;
							   
							 //  print "<br><br>";
							 //  print_r( $message); 
						   }else{
						   	// $status[] = "No emails sent to users."; 
						   }
			   		 } 
			   		 
			   		 }else{
			   		 	$users_validated = $users_validated + 1; 
			   		 	$status[] = "User '$tmp_user_name' is valid to be created for $tmp_sort_name (id: $cid, age: $age) "; 
			   		 }
			    }else{
			    	$status[] = $inavlid_user_msg;
		   		$contacts_skipped = $contacts_skipped + 1;
			    }
		   	
		   	}else{
		   		$status[] = "Skipped contact ID $cid";
		   		 $contacts_skipped = $contacts_skipped + 1; 
		   	}
		   	
		   	$dao->free(); 
    			
    			
    			   
		          
                  	
        	
    	//	}
    		$status[] = "\n\n";
    		if( $contacts_skipped > 0 ){
	    	   $status[] = "Could not create $contacts_skipped users"; 
	    	}
	    	if( $verification_only == "create" ){
	    	  if( $send_pwd_email_to_user ){
	    	   $status[] = "Emails sent to $users_emailed new user(s)"; 
	    	   }
	    	     
	    	  $status[] = "Successfully created $user_count new user(s)";
	    	}else{
	    	 // in Validation mode, just report on number of contacts validated
	    	    if( $users_validated == 0){
	    	    	$status[] = "Could NOT validate ANY contacts; ie system would not not generate any users for these contacts";
	    	    	
	    	    }else{
	    		$status[] = "Successfully validated $users_validated contacts, ie system can generate users for these contacts";
	    		}
	    	}
	    	    
       // }   
        
        
         $status_str = implode( '<br/>', $status );
         
         $status_str_plaintext = implode( '\n', $status); 
         
          $session         = CRM_Core_Session::singleton();
    	  $current_user_contactID       = $session->get('userID');
         
        $recipientContacts = array();
        $recipientContacts[] = array('contact_id' => $current_user_contactID);      
        $site_name = variable_get('site_name' ); 
        $subject =  $site_name." - Summary of users created from CRM action"; 
        $from = variable_get('site_mail'); 
        
        $html_summary_message = $status_str; 
        $text_summary_message = $status_str; 
        // send email of results to the user who ran this action
        list($sent, $activityId) = CRM_Activity_BAO_Activity::sendEmail(
      $recipientContacts,
      $subject,
      $text_summary_message,
      $html_summary_message,
      NULL,
      NULL,
      $from ,
      $attachments,
      $cc,
      $bcc,
      "",
      $additionalDetails
    );

    if ($sent) {
      
      $status_str =  $status_str."<br>Summary of results were emailed to you, also recorded as an activity.";
    }
    
        
    	
        CRM_Core_Session::setStatus( $status_str );
}

}